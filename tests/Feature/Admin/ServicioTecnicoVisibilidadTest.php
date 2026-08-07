<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visibilidad por vendedor en Servicio Técnico (regla #2: la gestión es por
 * vendedor). Quien NO tiene 'ver todo servicio tecnico' (el vendedor) solo ve
 * las órdenes de SU cartera (clientes con su vendedor_id), y una jefatura suma
 * la cartera de los vendedores a su cargo (users.jefe_id). Técnico, jefe de
 * ventas, jefe de bodega y admin ven todo. El enlace orden→cliente es por RUT.
 */
class ServicioTecnicoVisibilidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Vendedor con un cliente en su cartera y una orden de ese cliente. */
    private function vendedorConOrden(string $rut): array
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        Cliente::factory()->create(['rut' => $rut, 'vendedor_id' => $vendedor->id]);
        $orden = OrdenServicio::factory()->create(['cliente_rut' => $rut]);

        return [$vendedor, $orden];
    }

    /**
     * Orden de la cartera de OTRO vendedor — o sea genuinamente ajena.
     *
     * Tiene que existir la ficha del cliente CON vendedor asignado: desde la regla
     * del 07-08, un cliente que nadie tiene asignado NO es ajeno, es de sala de
     * ventas y lo ve todo vendedor. Antes estos tests usaban un RUT sin ficha como
     * atajo para «ajena», y ese atajo dejó de significar eso.
     */
    private function ordenDeOtraCartera(string $rut = '99999999-9'): OrdenServicio
    {
        $otro = tap(User::factory()->create())->assignRole('vendedor');
        Cliente::factory()->create(['rut' => $rut, 'vendedor_id' => $otro->id]);

        return OrdenServicio::factory()->create(['cliente_rut' => $rut]);
    }

    /** @return list<int> IDs de las órdenes que el listado le muestra al usuario. */
    private function idsDelListado($response): array
    {
        return $response->viewData('ordenes')->getCollection()->modelKeys();
    }

    public function test_el_vendedor_solo_ve_su_cartera_en_el_listado(): void
    {
        [$vendedorA, $ordenA] = $this->vendedorConOrden('11111111-1');

        // Cartera y orden de OTRO vendedor.
        $vendedorB = tap(User::factory()->create())->assignRole('vendedor');
        Cliente::factory()->create(['rut' => '22222222-2', 'vendedor_id' => $vendedorB->id]);
        $ordenB = OrdenServicio::factory()->create(['cliente_rut' => '22222222-2']);

        $res = $this->actingAs($vendedorA)->get('/admin/servicio-tecnico')->assertOk();

        $ids = $this->idsDelListado($res);
        $this->assertContains($ordenA->id, $ids);
        $this->assertNotContains($ordenB->id, $ids);
        $this->assertTrue($res->viewData('soloMiCartera'));
    }

    public function test_el_vendedor_no_puede_abrir_una_orden_fuera_de_su_cartera(): void
    {
        [$vendedorA, $ordenA] = $this->vendedorConOrden('11111111-1');
        $ordenAjena = $this->ordenDeOtraCartera();

        $this->actingAs($vendedorA)->get(route('admin.servicio-tecnico.show', $ordenA))->assertOk();
        $this->actingAs($vendedorA)->get(route('admin.servicio-tecnico.show', $ordenAjena))->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
    }

    public function test_la_jefatura_ve_su_cartera_y_la_de_sus_vendedores(): void
    {
        [$vendedor, $ordenVendedor] = $this->vendedorConOrden('11111111-1');

        // Jefatura: un usuario con el vendedor anterior a su cargo (jefe_id).
        $jefatura = tap(User::factory()->create())->assignRole('vendedor');
        $vendedor->update(['jefe_id' => $jefatura->id]);
        Cliente::factory()->create(['rut' => '33333333-3', 'vendedor_id' => $jefatura->id]);
        $ordenPropia = OrdenServicio::factory()->create(['cliente_rut' => '33333333-3']);

        // Orden de un tercero (fuera de su equipo).
        $ordenAjena = $this->ordenDeOtraCartera('44444444-4');

        $ids = $this->idsDelListado($this->actingAs($jefatura)->get('/admin/servicio-tecnico')->assertOk());

        $this->assertContains($ordenPropia->id, $ids);    // la suya
        $this->assertContains($ordenVendedor->id, $ids);  // la de su vendedor
        $this->assertNotContains($ordenAjena->id, $ids);  // ajena, no
    }

    public function test_los_roles_con_ver_todo_ven_todas_las_ordenes(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        Cliente::factory()->create(['rut' => '11111111-1', 'vendedor_id' => $vendedor->id]);
        $orden = OrdenServicio::factory()->create(['cliente_rut' => '11111111-1']);

        foreach (['tecnico', 'jefe_ventas', 'jefe_bodega', 'admin'] as $rol) {
            $user = tap(User::factory()->create())->assignRole($rol);
            $res = $this->actingAs($user)->get('/admin/servicio-tecnico')->assertOk();

            $this->assertContains($orden->id, $this->idsDelListado($res), "falló el listado para {$rol}");
            $this->assertFalse($res->viewData('soloMiCartera'), "soloMiCartera debería ser false para {$rol}");
            $this->actingAs($user)->get(route('admin.servicio-tecnico.show', $orden))->assertOk();
        }
    }

    public function test_la_foto_de_una_orden_ajena_no_es_accesible(): void
    {
        [$vendedorA] = $this->vendedorConOrden('11111111-1');
        $ordenAjena = $this->ordenDeOtraCartera();
        $foto = $ordenAjena->fotos()->create(['ruta' => 'ordenes-servicio/fotos/'.$ordenAjena->id.'/x.jpg']);

        // Este endpoint se consume como <img src> (_fotos.blade.php:12), no como
        // navegacion: el navegador lo pide con Sec-Fetch-Mode: no-cors, asi que
        // conserva su 403 en vez de redirigir (redirigir devolveria HTML donde el
        // navegador espera una imagen). Se manda el header para probar el contrato
        // REAL y no el de un GET de navegacion (gate R-31, 2026-07-24).
        $this->actingAs($vendedorA)
            ->get(route('admin.servicio-tecnico.foto', $foto), ['Sec-Fetch-Mode' => 'no-cors'])
            ->assertForbidden();
    }

    // --- Sala de ventas: lo que nadie tiene asignado (regla del dueño 07-08-2026) ---

    /**
     * El cliente que nadie tiene asignado es de SALA DE VENTAS: se atiende a todo
     * público y ellas lo monitorean hasta que se le asigne un vendedor. Así que
     * cualquier vendedor lo ve en el listado Y puede abrir la ficha — sin esto, el
     * aviso de la campanita llegaría sin link y la ficha daría 403.
     */
    public function test_un_cliente_sin_vendedor_asignado_lo_ve_sala_de_ventas(): void
    {
        [$vendedorA] = $this->vendedorConOrden('11111111-1');

        // Cliente con ficha pero sin asignar (el caso de HOY: el sync de Bsale no
        // llena vendedor_id) y cliente sin ficha alguna.
        Cliente::factory()->create(['rut' => '55555555-5', 'vendedor_id' => null]);
        $sinAsignar = OrdenServicio::factory()->create(['cliente_rut' => '55555555-5']);
        $sinFicha = OrdenServicio::factory()->create(['cliente_rut' => '66666666-6']);

        $ids = $this->idsDelListado($this->actingAs($vendedorA)->get('/admin/servicio-tecnico')->assertOk());
        $this->assertContains($sinAsignar->id, $ids, 'Sala de ventas no ve al cliente sin asignar.');
        $this->assertContains($sinFicha->id, $ids, 'Sala de ventas no ve la orden sin ficha de cliente.');

        // Y las puede abrir: un aviso que termina en 403 es un aviso muerto.
        $this->actingAs($vendedorA)->get(route('admin.servicio-tecnico.show', $sinAsignar))->assertOk();
        $this->actingAs($vendedorA)->get(route('admin.servicio-tecnico.show', $sinFicha))->assertOk();
    }

    /** Asignarle el cliente a alguien lo saca de sala de ventas: deja de ser de todos. */
    public function test_al_asignarle_vendedor_el_cliente_sale_de_sala_de_ventas(): void
    {
        [$vendedorA] = $this->vendedorConOrden('11111111-1');
        $cliente = Cliente::factory()->create(['rut' => '55555555-5', 'vendedor_id' => null]);
        $orden = OrdenServicio::factory()->create(['cliente_rut' => '55555555-5']);

        $this->assertTrue($orden->esVisiblePara($vendedorA));   // sala de ventas

        $otro = tap(User::factory()->create())->assignRole('vendedor');
        $cliente->update(['vendedor_id' => $otro->id]);

        $this->assertFalse($orden->fresh()->esVisiblePara($vendedorA), 'Ya tiene dueño: deja de ser de sala.');
        $this->assertTrue($orden->fresh()->esVisiblePara($otro));
    }

    /**
     * La regla vive DOS veces: `scopeVisiblePara` en SQL (listado) y
     * `esVisiblePara` en PHP (ficha, fotos, comprobante y el link de la campanita).
     * Si divergen, la ficha deja entrar a algo que el listado esconde — o peor, la
     * campanita enlaza a un 403. Este candado las compara caso por caso.
     */
    public function test_el_listado_y_la_ficha_no_pueden_discrepar(): void
    {
        [$vendedorA] = $this->vendedorConOrden('11111111-1');

        Cliente::factory()->create(['rut' => '55555555-5', 'vendedor_id' => null]);
        $casos = [
            'de su cartera' => OrdenServicio::factory()->create(['cliente_rut' => '11111111-1']),
            'de otra cartera' => $this->ordenDeOtraCartera(),
            'sin vendedor asignado' => OrdenServicio::factory()->create(['cliente_rut' => '55555555-5']),
            'sin ficha de cliente' => OrdenServicio::factory()->create(['cliente_rut' => '66666666-6']),
            'sin RUT' => OrdenServicio::factory()->create(['cliente_rut' => null]),
        ];

        $delListado = OrdenServicio::visiblePara($vendedorA)->pluck('id')->all();

        foreach ($casos as $caso => $orden) {
            $this->assertSame(
                in_array($orden->id, $delListado, true),
                $orden->esVisiblePara($vendedorA),
                "El listado y la ficha discrepan en el caso «{$caso}»."
            );
        }
    }
}
