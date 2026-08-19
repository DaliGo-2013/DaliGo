<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de OPE-1 (PLAN-PARAMETRICOS §5.3 #1): las ventanas por defecto del
 * panel del jefe y de los dos informes de rendimiento como parámetros nivel 1,
 * con el molde DASH-1 (default idéntico con BD virgen · mover cada clave mueve
 * SU pantalla —serie, cifra y rótulo— y NO las hermanas · la UI valida por los
 * dos bordes). Lo propio de este módulo: acá también se fija el contrato del
 * helper `rango()` — la clave es el DEFAULT de la ventana, y un rango pedido
 * por URL (?desde/?hasta) SIEMPRE le gana a la clave.
 */
class ParametrosOperacionTest extends TestCase
{
    use RefreshDatabase;

    private Maquina $maquina;

    private TipoBotellon $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SucursalSeeder::class);
        $this->freezeTime();

        $this->maquina = Maquina::create(['nombre' => 'SOPLA-1', 'sucursal_id' => Sucursal::first()->id, 'activa' => true]);
        $this->tipo = TipoBotellon::create(['codigo' => 'B20', 'nombre' => 'Botellón 20L', 'activo' => true]);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    /**
     * Reporte APROBADO con UNA tanda (la máquina/tipo del setUp): el panel
     * agrega desde los totales del reporte y los informes desde las tandas —
     * la misma producción alimenta las tres pantallas.
     */
    private function produccionDe(string $fecha, int $primera): void
    {
        $soplador = User::factory()->create();
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $primera,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $primera,
            'estado' => ProduccionReporte::APROBADO,
        ]);
        ProduccionRegistro::create([
            'reporte_id' => $reporte->id,
            'maquina_id' => $this->maquina->id,
            'tipo_botellon_id' => $this->tipo->id,
            'primera' => $primera,
            'segunda' => 0,
            'malo' => 0,
            'danada' => 0,
        ]);
        $reporte->recalcularDesdeRegistros();
    }

    /**
     * Tres producciones en los BORDES de las ventanas: hoy (dentro de todas),
     * hace 7 días (fuera del panel de 7, dentro de 14 y de los informes de 30)
     * y hace 34 días (fuera de los informes de 30, dentro de 45 y de 60).
     */
    private function produccionEnLosBordes(): void
    {
        $this->produccionDe(now()->toDateString(), 100);
        $this->produccionDe(now()->subDays(7)->toDateString(), 40);
        $this->produccionDe(now()->subDays(34)->toDateString(), 50);
    }

    public function test_sin_claves_en_bd_las_tres_pantallas_rinden_identico_al_historico(): void
    {
        // A propósito SIN ConfiguracionSeeder: la BD virgen es el escenario de
        // la regla de oro — rigen los fallbacks del controller (7 y 30/30).
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk();
        $this->assertSame(7, $panel->viewData('diasPanel'));
        $this->assertCount(7, $panel->viewData('periodo')['dias']);
        $this->assertSame(100, $panel->viewData('periodo')['totales']['producido']); // el día -7 queda FUERA
        $panel->assertSee('Últimos 7 días');

        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', $this->maquina))->assertOk();
        $this->assertSame(30, $maquina->viewData('diasInforme'));
        $this->assertCount(30, $maquina->viewData('tendencia')['dias']);
        $this->assertSame(140, $maquina->viewData('tendencia')['totales']['producido']); // el día -34 queda FUERA
        $maquina->assertSee('· últimos 30 días');

        $tipo = $this->actingAs($jefe)->get(route('admin.produccion.tipo', $this->tipo))->assertOk();
        $this->assertSame(30, $tipo->viewData('diasInforme'));
        $this->assertCount(30, $tipo->viewData('tendencia')['dias']);
        $this->assertSame(140, $tipo->viewData('tendencia')['totales']['producido']);
        $tipo->assertSee('· últimos 30 días');
    }

    public function test_mover_la_ventana_del_panel_mueve_su_serie_y_cifra_y_no_los_informes(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        Configuracion::set('produccion_dias_panel', 14);

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk();
        $this->assertCount(14, $panel->viewData('periodo')['dias']);
        $this->assertSame(140, $panel->viewData('periodo')['totales']['producido']); // el día -7 ENTRA
        $panel->assertSee('Últimos 14 días')->assertDontSee('Últimos 7 días');

        // Las ventanas hermanas NO se movieron.
        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', $this->maquina))->assertOk();
        $this->assertCount(30, $maquina->viewData('tendencia')['dias']);
        $maquina->assertSee('· últimos 30 días');

        $tipo = $this->actingAs($jefe)->get(route('admin.produccion.tipo', $this->tipo))->assertOk();
        $this->assertCount(30, $tipo->viewData('tendencia')['dias']);
        $tipo->assertSee('· últimos 30 días');
    }

    public function test_mover_el_informe_de_maquina_mueve_su_ventana_y_no_el_panel_ni_el_de_tipo(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        Configuracion::set('produccion_dias_informe_maquina', 45);

        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', $this->maquina))->assertOk();
        $this->assertCount(45, $maquina->viewData('tendencia')['dias']);
        $this->assertSame(190, $maquina->viewData('tendencia')['totales']['producido']); // el día -34 ENTRA
        $maquina->assertSee('· últimos 45 días')->assertDontSee('· últimos 30 días');

        $tipo = $this->actingAs($jefe)->get(route('admin.produccion.tipo', $this->tipo))->assertOk();
        $this->assertCount(30, $tipo->viewData('tendencia')['dias']);
        $this->assertSame(140, $tipo->viewData('tendencia')['totales']['producido']);
        $tipo->assertSee('· últimos 30 días');

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk();
        $this->assertCount(7, $panel->viewData('periodo')['dias']);
        $panel->assertSee('Últimos 7 días');
    }

    public function test_mover_el_informe_de_tipo_mueve_su_ventana_y_no_el_panel_ni_el_de_maquina(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        Configuracion::set('produccion_dias_informe_tipo', 60);

        $tipo = $this->actingAs($jefe)->get(route('admin.produccion.tipo', $this->tipo))->assertOk();
        $this->assertCount(60, $tipo->viewData('tendencia')['dias']);
        $this->assertSame(190, $tipo->viewData('tendencia')['totales']['producido']); // el día -34 ENTRA
        $tipo->assertSee('· últimos 60 días')->assertDontSee('· últimos 30 días');

        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', $this->maquina))->assertOk();
        $this->assertCount(30, $maquina->viewData('tendencia')['dias']);
        $this->assertSame(140, $maquina->viewData('tendencia')['totales']['producido']);
        $maquina->assertSee('· últimos 30 días');

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index'))->assertOk();
        $this->assertCount(7, $panel->viewData('periodo')['dias']);
        $panel->assertSee('Últimos 7 días');
    }

    public function test_el_rango_pedido_por_url_le_gana_a_la_clave(): void
    {
        // El OJO del dictado v77: la clave es el DEFAULT del rango, no un tope.
        // Con las claves movidas, un ?desde/?hasta explícito manda igual.
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionEnLosBordes();
        $jefe = $this->jefe();

        Configuracion::set('produccion_dias_panel', 14);
        Configuracion::set('produccion_dias_informe_maquina', 45);

        $query = ['desde' => now()->subDays(2)->toDateString(), 'hasta' => now()->toDateString()];

        $panel = $this->actingAs($jefe)->get(route('admin.produccion.index', $query))->assertOk();
        $this->assertFalse($panel->viewData('periodo')['esDefault']);
        $this->assertCount(3, $panel->viewData('periodo')['dias']); // los 3 pedidos, no los 14 de la clave
        $this->assertSame(100, $panel->viewData('periodo')['totales']['producido']);
        $panel->assertDontSee('Últimos 14 días');

        $maquina = $this->actingAs($jefe)->get(route('admin.produccion.maquina', array_merge(['maquina' => $this->maquina->id], $query)))->assertOk();
        $this->assertFalse($maquina->viewData('esDefault'));
        $this->assertCount(3, $maquina->viewData('tendencia')['dias']);
        $maquina->assertDontSee('· últimos 45 días');
    }

    public function test_la_ui_de_configuracion_valida_el_rango_del_panel_2_a_31(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');
        $config = Configuracion::where('clave', 'produccion_dias_panel')->firstOrFail();

        foreach ([1, 0, -5, 32, 'abc'] as $malo) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => $malo])
                ->assertSessionHasErrors('valor');
        }

        foreach ([2, 31] as $bueno) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => $bueno])
                ->assertSessionHasNoErrors();
            $this->assertSame($bueno, Configuracion::get('produccion_dias_panel'));
        }
    }

    public function test_la_ui_de_configuracion_valida_el_rango_de_los_informes_7_a_90(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');

        foreach (['produccion_dias_informe_maquina', 'produccion_dias_informe_tipo'] as $clave) {
            $config = Configuracion::where('clave', $clave)->firstOrFail();

            foreach ([6, 0, -5, 91, 'abc'] as $malo) {
                $this->actingAs($admin)
                    ->put(route('admin.configuracion.update', $config), ['valor' => $malo])
                    ->assertSessionHasErrors('valor');
            }

            foreach ([7, 90] as $bueno) {
                $this->actingAs($admin)
                    ->put(route('admin.configuracion.update', $config), ['valor' => $bueno])
                    ->assertSessionHasNoErrors();
                $this->assertSame($bueno, Configuracion::get($clave));
            }
        }
    }
}
