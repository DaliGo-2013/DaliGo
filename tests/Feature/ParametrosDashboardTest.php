<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de DASH-1 (PLAN-PARAMETRICOS): las dos ventanas del pulso del
 * Inicio como parámetros nivel 1. Este archivo FIJA EL MOLDE del proyecto —
 * mínimo dos candados por parámetro:
 *  1. Default idéntico: con la clave AUSENTE en BD, la pantalla rinde EXACTO
 *     como antes de parametrizar (regla de oro: parametrizar no cambia nada).
 *  2. Mover-el-parámetro-mueve-la-pantalla: sembrar otro valor mueve el
 *     cálculo Y el rótulo (que deriva del parámetro), y NO mueve la ventana
 *     hermana (independencia de claves — hallazgos #1 y #2 son ventanas
 *     distintas aunque ambas partan en 7).
 *  3. La UI de Configuración rechaza el rango roto («un 0 o un negativo no
 *     puede romper la operación») y acepta los dos bordes sanos.
 */
class ParametrosDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    /** Asignación + reporte mínimos para el pulso (mismo idioma que DashboardTest). */
    private function produccionDe(string $fecha, array $reporte = [], int $asignadas = 200): ProduccionReporte
    {
        $soplador = User::factory()->create();
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $asignadas,
        ]);

        return ProduccionReporte::create($reporte + [
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $fecha,
            'turno' => 'dia',
            'asignadas' => $asignadas,
            'estado' => ProduccionReporte::APROBADO,
        ]);
    }

    public function test_sin_claves_en_bd_el_pulso_rinde_identico_al_historico(): void
    {
        // A propósito SIN ConfiguracionSeeder: la BD virgen es el escenario de
        // la regla de oro — rige el fallback del controller y todo dice 7.
        $this->freezeTime();
        $this->produccionDe(now()->toDateString(), ['primera' => 100]);
        $this->produccionDe(now()->subDay()->toDateString(), ['primera' => 90, 'malo' => 10], 100);

        $res = $this->actingAs($this->jefe())->get('/dashboard')->assertOk();

        $pulso = $res->viewData('pulsoProduccion');
        $this->assertCount(7, $pulso['serie']);
        $this->assertSame(7, $pulso['diasSerie']);
        $this->assertSame(7, $pulso['diasMerma']);
        $this->assertSame(10, $pulso['mermaProm7']);
        $res->assertSee('Últimos 7 días')->assertSee('prom. 7 días 10%');
    }

    public function test_mover_la_serie_mueve_las_barras_y_su_rotulo_y_no_toca_la_merma(): void
    {
        $this->freezeTime();
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionDe(now()->toDateString(), ['primera' => 100]);
        $this->produccionDe(now()->subDay()->toDateString(), ['primera' => 90, 'malo' => 10], 100);

        Configuracion::set('dashboard_dias_serie_produccion', 14);

        $res = $this->actingAs($this->jefe())->get('/dashboard')->assertOk();

        $this->assertCount(14, $res->viewData('pulsoProduccion')['serie']);
        $res->assertSee('Últimos 14 días')
            ->assertDontSee('Últimos 7 días')
            ->assertSee('prom. 7 días'); // la ventana hermana NO se movió
    }

    public function test_mover_la_merma_mueve_su_promedio_y_su_rotulo_y_no_toca_la_serie(): void
    {
        $this->freezeTime();
        $this->seed(ConfiguracionSeeder::class);
        $this->produccionDe(now()->toDateString(), ['primera' => 100]);
        // Ayer: merma 10 de 100 — la única referencia dentro de la ventana de 7.
        $this->produccionDe(now()->subDay()->toDateString(), ['primera' => 90, 'malo' => 10], 100);
        // Hace 9 días: merma 50 de 100 — FUERA de la ventana de 7, DENTRO de la de 10.
        $this->produccionDe(now()->subDays(9)->toDateString(), ['primera' => 50, 'malo' => 50], 100);

        // Con el default, el día -9 no cuenta: prom = 10%.
        $res = $this->actingAs($this->jefe())->get('/dashboard');
        $this->assertSame(10, $res->viewData('pulsoProduccion')['mermaProm7']);

        Configuracion::set('dashboard_dias_referencia_merma', 10);

        $res = $this->actingAs($this->jefe())->get('/dashboard')->assertOk();
        // Con 10 días entra el día -9: (10+50) de 200 = 30%.
        $this->assertSame(30, $res->viewData('pulsoProduccion')['mermaProm7']);
        $this->assertCount(7, $res->viewData('pulsoProduccion')['serie']); // la serie NO se movió
        $res->assertSee('prom. 10 días 30%')->assertSee('Últimos 7 días');
    }

    public function test_la_ui_de_configuracion_valida_el_rango_2_a_31(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');
        $config = Configuracion::where('clave', 'dashboard_dias_serie_produccion')->firstOrFail();

        foreach ([1, 0, -5, 32, 'abc'] as $malo) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => $malo])
                ->assertSessionHasErrors('valor');
        }

        foreach ([2, 31] as $bueno) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => $bueno])
                ->assertSessionHasNoErrors();
            $this->assertSame($bueno, Configuracion::get('dashboard_dias_serie_produccion'));
        }
    }
}
