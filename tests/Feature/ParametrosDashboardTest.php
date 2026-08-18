<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\OrdenServicio;
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

    // --- DASH-2: cortes de antigüedad del taller + el desacople del flujo ---

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    /** Orden activa del taller con la edad pedida (estado FIJO: la factory lo sortea). */
    private function ordenActivaDeHace(int $dias): OrdenServicio
    {
        return OrdenServicio::factory()->create([
            'estado' => 'recibido',
            'fecha_ingreso' => now()->subDays($dias)->toDateString(),
        ]);
    }

    public function test_sin_claves_en_bd_el_taller_rinde_identico_al_historico(): void
    {
        // BD virgen (sin ConfiguracionSeeder): tramos 0-7/8-30/30+ y la
        // «última semana» en 7, exacto como antes de parametrizar.
        $this->freezeTime();
        $this->ordenActivaDeHace(3);
        $this->ordenActivaDeHace(10);
        $this->ordenActivaDeHace(40);

        $res = $this->actingAs($this->tecnico())->get('/dashboard')->assertOk();

        $pulso = $res->viewData('pulsoTaller');
        $this->assertSame(['d0_7' => 1, 'd8_30' => 1, 'd30' => 1], $pulso['aging']);
        $this->assertSame(7, $pulso['corteReciente']);
        $this->assertSame(30, $pulso['corteAntiguo']);
        $this->assertSame(1, $pulso['entradasSemana']);
        $res->assertSee('de 0-7 días')->assertSee('de 8-30')->assertSee('de 30+')
            ->assertSee('llevan 30+ días')->assertSee('Última semana');
    }

    public function test_mover_el_corte_reciente_mueve_el_bucket_y_no_la_ultima_semana(): void
    {
        $this->freezeTime();
        $this->seed(ConfiguracionSeeder::class);
        $this->ordenActivaDeHace(3);
        $this->ordenActivaDeHace(9);

        // Default: la de 9 días cae en el tramo medio y NO cuenta en la semana.
        $res = $this->actingAs($this->tecnico())->get('/dashboard');
        $this->assertSame(['d0_7' => 1, 'd8_30' => 1, 'd30' => 0], $res->viewData('pulsoTaller')['aging']);
        $this->assertSame(1, $res->viewData('pulsoTaller')['entradasSemana']);

        Configuracion::set('dashboard_corte_taller_reciente', 10);

        $res = $this->actingAs($this->tecnico())->get('/dashboard')->assertOk();
        // La orden de 9 días CAMBIA de bucket (la cifra, no solo el rótulo)…
        $this->assertSame(['d0_7' => 2, 'd8_30' => 0, 'd30' => 0], $res->viewData('pulsoTaller')['aging']);
        $res->assertSee('de 0-10 días')->assertSee('de 11-30');
        // …y la «última semana» NO se mueve: con el $d7 compartido de antes,
        // la de 9 días habría entrado a las entradas (el candado del desacople).
        $this->assertSame(1, $res->viewData('pulsoTaller')['entradasSemana']);
    }

    public function test_mover_el_corte_antiguo_mueve_el_bucket_y_sus_rotulos(): void
    {
        $this->freezeTime();
        $this->seed(ConfiguracionSeeder::class);
        $this->ordenActivaDeHace(35);
        $this->ordenActivaDeHace(40);
        $this->ordenActivaDeHace(50);

        // Default: las tres son «30+».
        $res = $this->actingAs($this->tecnico())->get('/dashboard');
        $this->assertSame(3, $res->viewData('pulsoTaller')['aging']['d30']);
        $res->assertSee('llevan 30+ días');

        Configuracion::set('dashboard_corte_taller_antiguo', 45);

        $res = $this->actingAs($this->tecnico())->get('/dashboard')->assertOk();
        // 35 y 40 bajan al tramo medio; solo la de 50 sigue antigua (cifra).
        $this->assertSame(['d0_7' => 0, 'd8_30' => 2, 'd30' => 1], $res->viewData('pulsoTaller')['aging']);
        $res->assertSee('de 8-45')->assertSee('de 45+')->assertSee('llevan 45+ días')
            ->assertSee('de 0-7 días'); // el corte reciente NO se movió
    }

    public function test_la_validacion_cruzada_rechaza_el_par_invertido_o_igual(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');
        $reciente = Configuracion::where('clave', 'dashboard_corte_taller_reciente')->firstOrFail();
        $antiguo = Configuracion::where('clave', 'dashboard_corte_taller_antiguo')->firstOrFail();

        // Igual (30 = 30) e invertido (31 > 30): rechazados, y el mensaje
        // nombra a la otra clave del par.
        foreach ([30, 31] as $malo) {
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $reciente), ['valor' => $malo])
                ->assertInvalid(['valor' => 'por debajo de «Dashboard Corte Taller Antiguo»']);
        }
        $this->assertSame(7, Configuracion::get('dashboard_corte_taller_reciente'));

        // La otra punta también valida: antiguo ≤ reciente se rechaza.
        $this->actingAs($admin)
            ->put(route('admin.configuracion.update', $antiguo), ['valor' => 7])
            ->assertInvalid(['valor' => 'por encima de «Dashboard Corte Taller Reciente»']);

        // El par sano pasa por las dos puntas.
        $this->actingAs($admin)
            ->put(route('admin.configuracion.update', $reciente), ['valor' => 10])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->put(route('admin.configuracion.update', $antiguo), ['valor' => 60])
            ->assertSessionHasNoErrors();
        $this->assertSame(10, Configuracion::get('dashboard_corte_taller_reciente'));
        $this->assertSame(60, Configuracion::get('dashboard_corte_taller_antiguo'));
    }
}
