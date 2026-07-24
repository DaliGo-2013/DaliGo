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
        $ordenAjena = OrdenServicio::factory()->create(['cliente_rut' => '99999999-9']);

        $this->actingAs($vendedorA)->get(route('admin.servicio-tecnico.show', $ordenA))->assertOk();
        $this->actingAs($vendedorA)->get(route('admin.servicio-tecnico.show', $ordenAjena))->assertForbidden();
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
        $ordenAjena = OrdenServicio::factory()->create(['cliente_rut' => '44444444-4']);

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
        $ordenAjena = OrdenServicio::factory()->create(['cliente_rut' => '99999999-9']);
        $foto = $ordenAjena->fotos()->create(['ruta' => 'ordenes-servicio/fotos/'.$ordenAjena->id.'/x.jpg']);

        $this->actingAs($vendedorA)->get(route('admin.servicio-tecnico.foto', $foto))->assertForbidden();
    }
}
