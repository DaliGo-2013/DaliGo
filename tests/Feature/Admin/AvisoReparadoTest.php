<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aviso interno «equipo reparado» (decision del dueño 30-07-2026): cuando el
 * tecnico marca la orden como reparada, VENTAS tiene que llamar al cliente para
 * que la retire. Cerraba el hueco que dejo la auditoria de la ruta completa: el
 * cliente se enteraba por el portal, pero adentro nadie recibia nada.
 *
 * La regla del dueño tiene dos mitades y las dos las resuelve el MISMO filtro
 * (`OrdenServicio::esVisiblePara`, el que ya gobierna listado y ficha):
 *   1. El jefe de ventas recibe TODAS las ordenes —las de todos sus vendedores—
 *      porque tiene 'ver todo servicio tecnico'.
 *   2. Cada vendedor recibe SOLO las de su cartera (`clientes.vendedor_id`),
 *      porque no lo tiene.
 *
 * Que la mitad 2 este blindada HOY importa aunque hoy no haya carteras
 * asignadas: es lo que garantiza que el dia que se asignen, cada vendedor reciba
 * lo suyo sin tocar el codigo.
 */
class AvisoReparadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    private function jefeVentas(): User
    {
        return tap(User::factory()->create(['name' => 'Héctor Martínez']))->assignRole('jefe_ventas');
    }

    /** Vendedor con un cliente en su cartera (el enlace orden→cliente es por RUT). */
    private function vendedorConCartera(string $rut): User
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        Cliente::factory()->create(['rut' => $rut, 'vendedor_id' => $vendedor->id]);

        return $vendedor;
    }

    private function orden(array $extra = []): OrdenServicio
    {
        return OrdenServicio::factory()->create(array_merge([
            'estado' => 'en_revision',
            'facturacion' => 'reparacion',
            'cliente_rut' => '11111111-1',
            'cliente_telefono' => '+56 9 8765 4321',
            'tipo_equipo' => 'dispensador',
            'modelo' => 'Dispensador LB-16 blanco',
            'numero_serie' => 'EST20260100251',
        ], $extra));
    }

    /** Guarda el parte del tecnico con el estado pedido. */
    private function guardar(User $actor, OrdenServicio $orden, string $estado)
    {
        return $this->actingAs($actor)->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            [
                'estado' => $estado,
                'trabajo_realizado' => 'Cambio de caldera',
                // Obligatoria al cerrar como reparado/sin_solucion.
                'causa_falla' => 'uso_normal',
                'repuestos' => [],
            ],
        );
    }

    /** @return list<int> user_id de las filas de campanita del evento. */
    private function avisados(): array
    {
        return Notificacion::where('evento', 'taller.reparado')
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->pluck('user_id')->all();
    }

    // --- Mitad 1: jefatura recibe todo ---

    public function test_el_jefe_de_ventas_recibe_el_aviso_al_marcar_reparado(): void
    {
        $jefe = $this->jefeVentas();
        $orden = $this->orden();

        $this->guardar($this->tecnico(), $orden, 'reparado')->assertRedirect();

        $this->assertContains($jefe->id, $this->avisados());
        $this->assertSame('reparado', $orden->fresh()->estado);
    }

    /**
     * Recibe las de TODOS los vendedores, no solo las de clientes suyos: el
     * cliente de esta orden esta en la cartera de otro y aun asi le llega.
     */
    public function test_el_jefe_de_ventas_recibe_tambien_las_ordenes_de_otras_carteras(): void
    {
        $jefe = $this->jefeVentas();
        $this->vendedorConCartera('99999999-9');
        $orden = $this->orden(['cliente_rut' => '99999999-9']);

        $this->guardar($this->tecnico(), $orden, 'reparado');

        $this->assertContains($jefe->id, $this->avisados());
    }

    // --- Mitad 2: cada vendedor, solo su cartera ---

    public function test_el_vendedor_recibe_la_orden_de_su_cartera(): void
    {
        $vendedor = $this->vendedorConCartera('11111111-1');
        $orden = $this->orden(['cliente_rut' => '11111111-1']);

        $this->guardar($this->tecnico(), $orden, 'reparado');

        $this->assertContains($vendedor->id, $this->avisados());
    }

    public function test_el_vendedor_no_recibe_la_orden_de_otra_cartera(): void
    {
        $vendedorA = $this->vendedorConCartera('11111111-1');
        $vendedorB = $this->vendedorConCartera('22222222-2');
        $orden = $this->orden(['cliente_rut' => '22222222-2']);

        $this->guardar($this->tecnico(), $orden, 'reparado');

        $avisados = $this->avisados();
        $this->assertContains($vendedorB->id, $avisados);
        $this->assertNotContains($vendedorA->id, $avisados,
            'Un vendedor recibió el aviso de una orden que después no puede abrir (403 en la ficha).');
    }

    /**
     * Estado de HOY del negocio: sin carteras asignadas ningun vendedor recibe
     * nada (y no debe, porque tampoco puede abrir la ficha). El aviso llega solo
     * a jefatura. Este test documenta ese estado; el dia que se asignen carteras
     * lo cubre `test_el_vendedor_recibe_la_orden_de_su_cartera`.
     */
    public function test_sin_cartera_asignada_el_vendedor_no_recibe_nada(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $jefe = $this->jefeVentas();
        $orden = $this->orden();

        $this->guardar($this->tecnico(), $orden, 'reparado');

        $avisados = $this->avisados();
        $this->assertContains($jefe->id, $avisados);
        $this->assertNotContains($vendedor->id, $avisados);
    }

    /** Una jefatura de vendedores (users.jefe_id) suma la cartera de su equipo. */
    public function test_la_jefatura_de_un_vendedor_recibe_la_cartera_de_su_equipo(): void
    {
        $vendedor = $this->vendedorConCartera('11111111-1');
        $jefatura = tap(User::factory()->create())->assignRole('vendedor');
        $vendedor->update(['jefe_id' => $jefatura->id]);

        $this->guardar($this->tecnico(), $this->orden(['cliente_rut' => '11111111-1']), 'reparado');

        $this->assertContains($jefatura->id, $this->avisados());
    }

    // --- Cuándo se dispara (y cuándo no) ---

    /** El tecnico marca el estado: avisarle de su propia accion es ruido. */
    public function test_el_tecnico_no_recibe_el_aviso_de_su_propia_accion(): void
    {
        $tecnico = $this->tecnico();
        $this->jefeVentas();

        $this->guardar($tecnico, $this->orden(), 'reparado');

        $this->assertNotContains($tecnico->id, $this->avisados());
    }

    /** Y quien lo marca tampoco se autonotifica si SÍ está entre los roles avisados. */
    public function test_quien_marca_no_se_autonotifica_aunque_sea_de_ventas(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');
        $jefe = $this->jefeVentas();

        $this->guardar($admin, $this->orden(), 'reparado');

        $avisados = $this->avisados();
        $this->assertContains($jefe->id, $avisados);
        $this->assertNotContains($admin->id, $avisados);
    }

    /**
     * El aviso va en la TRANSICIÓN. El técnico re-guarda el parte varias veces
     * (agrega un repuesto, corrige el trabajo) y ventas no puede recibir un aviso
     * por cada guardado.
     */
    public function test_reguardar_una_orden_ya_reparada_no_avisa_de_nuevo(): void
    {
        $this->jefeVentas();
        $tecnico = $this->tecnico();
        $orden = $this->orden();

        $this->guardar($tecnico, $orden, 'reparado');
        $primera = count($this->avisados());

        $this->guardar($tecnico, $orden->fresh(), 'reparado');

        $this->assertSame($primera, count($this->avisados()), 'Se avisó dos veces por la misma reparación.');
        $this->assertGreaterThan(0, $primera);
    }

    public function test_un_estado_intermedio_no_avisa(): void
    {
        $this->jefeVentas();

        $this->guardar($this->tecnico(), $this->orden(), 'esperando_repuesto');

        $this->assertSame([], $this->avisados());
    }

    /** Volver a reparado despues de salir de ese estado SI vuelve a avisar. */
    public function test_volver_a_reparado_avisa_otra_vez(): void
    {
        $this->jefeVentas();
        $tecnico = $this->tecnico();
        $orden = $this->orden();

        $this->guardar($tecnico, $orden, 'reparado');
        $this->guardar($tecnico, $orden->fresh(), 'esperando_repuesto');
        $this->guardar($tecnico, $orden->fresh(), 'reparado');

        $this->assertCount(2, $this->avisados());
    }

    // --- El texto del aviso ---

    public function test_el_aviso_trae_folio_equipo_telefono_y_quien_lo_marco(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->jefeVentas();
        $tecnico = tap(User::factory()->create(['name' => 'Fernando Soto']))->assignRole('tecnico');
        $orden = $this->orden();

        $this->guardar($tecnico, $orden, 'reparado');

        $aviso = Notificacion::where('evento', 'taller.reparado')
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();

        $this->assertStringContainsString($orden->folio, $aviso->titulo);
        $this->assertStringContainsString('Fernando Soto', $aviso->cuerpo);
        $this->assertStringContainsString('EST20260100251', $aviso->cuerpo);
        $this->assertStringContainsString('Cambio de caldera', $aviso->cuerpo);
        // El teléfono va en el cuerpo: el destinatario tiene que LLAMAR, y sin él
        // el aviso obliga a abrir la ficha para conseguir el dato con el que actúa.
        $this->assertStringContainsString('+56 9 8765 4321', $aviso->cuerpo);
        // Ningún {placeholder} quedó crudo.
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $aviso->titulo.' '.$aviso->cuerpo);
    }

    /** Sin trabajo escrito ni telefono, el texto NO deja placeholders crudos. */
    public function test_el_aviso_no_deja_placeholders_crudos_con_datos_faltantes(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->jefeVentas();
        $orden = $this->orden(['cliente_telefono' => null]);

        $this->actingAs($this->tecnico())->put(
            route('admin.servicio-tecnico.reparacion.guardar', $orden),
            ['estado' => 'reparado', 'causa_falla' => 'uso_normal', 'repuestos' => []],
        );

        $aviso = Notificacion::where('evento', 'taller.reparado')
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();

        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $aviso->titulo.' '.$aviso->cuerpo);
        $this->assertStringContainsString('sin teléfono registrado', $aviso->cuerpo);
    }

    // --- La campanita aterriza donde se actúa ---

    public function test_la_campanita_lleva_a_la_ficha_de_la_orden(): void
    {
        $jefe = $this->jefeVentas();
        $orden = $this->orden();

        $this->guardar($this->tecnico(), $orden, 'reparado');

        $aviso = Notificacion::where('evento', 'taller.reparado')->where('user_id', $jefe->id)
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();

        $esperado = route('admin.servicio-tecnico.show', $orden->id);
        $this->assertSame($esperado, $aviso->urlDestino());
        $this->assertSame($esperado, $aviso->urlDestinoPara($jefe));
    }

    /**
     * Y NO lleva a ningun lado para quien no puede abrirla: si un vendedor pierde
     * la cartera despues de recibir el aviso, la fila queda sin enlace en vez de
     * mandarlo a un 403 (mismo criterio que cotizacion.*).
     */
    public function test_la_campanita_no_enlaza_para_quien_perdio_la_cartera(): void
    {
        $vendedor = $this->vendedorConCartera('11111111-1');
        $orden = $this->orden(['cliente_rut' => '11111111-1']);

        $this->guardar($this->tecnico(), $orden, 'reparado');

        $aviso = Notificacion::where('evento', 'taller.reparado')->where('user_id', $vendedor->id)
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();

        // Le quitan el cliente de la cartera.
        Cliente::where('rut', '11111111-1')->update(['vendedor_id' => null]);

        $this->assertNull($aviso->fresh()->load('notificable')->urlDestinoPara($vendedor->fresh()));
    }
}
