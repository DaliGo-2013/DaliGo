<?php

namespace Tests\Feature;

use App\Models\PlanExtra;
use App\Models\User;
use App\Support\PlanProyecto;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Candados de la página «Plan del proyecto» (/plan).
 *
 * Los tests del parser corren contra los archivos REALES del repo
 * (docs/RUTA-MAESTRA.md §10 y docs/DECISIONES.md §2) a propósito: la CI corre
 * en cada push, así que un cambio de formato en esas tablas pone la suite
 * roja ANTES de que el deploy rompa la página en producción. Los asserts son
 * invariantes AUTO-consistentes (la suma de pesos == el TOTAL de la propia
 * tabla), no números copiados que se desactualicen.
 */
class PlanProyectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuarioQueVe(): User
    {
        return tap(User::factory()->create())->givePermissionTo('ver plan proyecto');
    }

    private function usuarioQueGestiona(): User
    {
        return tap(User::factory()->create())
            ->givePermissionTo(['ver plan proyecto', 'gestionar plan proyecto']);
    }

    // --- Parser del tracker §10 (contra el archivo real) ---

    public function test_tracker_parsea_el_archivo_real_y_es_autoconsistente(): void
    {
        $tracker = PlanProyecto::tracker();

        $this->assertGreaterThanOrEqual(15, count($tracker['filas']), 'El tracker §10 debe traer los módulos M01…M17 + F3…F5.');
        $this->assertNotNull($tracker['total'], 'La fila TOTAL del tracker §10 no se pudo parsear.');

        // Invariantes auto-consistentes: la tabla debe cuadrar consigo misma.
        $this->assertSame(
            $tracker['total']['peso'],
            array_sum(array_column($tracker['filas'], 'peso')),
            'La suma de pesos de las filas no cuadra con el TOTAL del tracker.'
        );
        $this->assertEqualsWithDelta(
            $tracker['total']['aporta'],
            array_sum(array_column($tracker['filas'], 'aporta')),
            0.05,
            'La suma de aportes de las filas no cuadra con el TOTAL del tracker.'
        );

        foreach ($tracker['filas'] as $key => $fila) {
            $this->assertGreaterThanOrEqual(0, $fila['pct'], "El % de [{$key}] quedó fuera de rango.");
            $this->assertLessThanOrEqual(100, $fila['pct'], "El % de [{$key}] quedó fuera de rango.");
            $this->assertGreaterThan(0, $fila['peso'], "El peso de [{$key}] no se parseó.");
        }

        $this->assertGreaterThan(0, $tracker['pct_global']);
        $this->assertLessThan(100, $tracker['pct_global']);
    }

    public function test_modulos_del_gantt_y_tracker_son_biyectivos(): void
    {
        // Anti-drift: si el tracker gana un ítem (como entró M17 el 26-07),
        // este test obliga a agregar su línea de fechas en el MISMO push — y
        // si un ítem sale del tracker, obliga a retirarla.
        $tracker = array_keys(PlanProyecto::tracker()['filas']);
        $modulos = array_keys(PlanProyecto::MODULOS);
        sort($tracker);
        sort($modulos);

        $this->assertSame($tracker, $modulos,
            'PlanProyecto::MODULOS y el tracker §10 de RUTA-MAESTRA divergieron: alinéalos en el mismo push.');
    }

    public function test_fechas_de_modulos_validas_y_dentro_del_span(): void
    {
        $inicio = Carbon::parse(PlanProyecto::INICIO_PROYECTO);
        $fin = Carbon::parse(PlanProyecto::FIN_PROYECTO);

        foreach (PlanProyecto::MODULOS as $key => $modulo) {
            $desde = Carbon::parse($modulo['inicio']);
            $hasta = Carbon::parse($modulo['fin']);
            $this->assertTrue($desde->lte($hasta), "En [{$key}] el inicio es posterior al fin.");
            $this->assertTrue($desde->gte($inicio) && $hasta->lte($fin),
                "La ventana de [{$key}] se sale del span del proyecto (ajusta INICIO/FIN_PROYECTO o la fecha).");
        }
    }

    public function test_estado_se_deriva_del_porcentaje(): void
    {
        $this->assertSame('no_iniciada', PlanProyecto::estadoDe(0));
        $this->assertSame('en_curso', PlanProyecto::estadoDe(1));
        $this->assertSame('en_curso', PlanProyecto::estadoDe(99));
        $this->assertSame('finalizada', PlanProyecto::estadoDe(100));
    }

    public function test_decisiones_parsea_el_semaforo_real(): void
    {
        $decisiones = PlanProyecto::decisiones();

        $this->assertGreaterThanOrEqual(10, count($decisiones), 'El semáforo §2 de DECISIONES.md debe traer las D-0NN.');

        foreach ($decisiones as $decision) {
            $this->assertContains($decision['estado'], ['abierta', 'aplazada', 'tomada', 'descartada'],
                "La decisión [{$decision['id']}] quedó con un estado no reconocido.");
        }

        // D-001 (nombre del sistema) es estable: tomada desde julio 2026.
        $porId = array_column($decisiones, 'estado', 'id');
        $this->assertSame('tomada', $porId['D-001'] ?? null);
    }

    // --- La página ---

    public function test_la_pagina_exige_su_permiso(): void
    {
        $this->get(route('plan.index'))->assertRedirect(route('login'));

        $sinPermiso = User::factory()->create();
        $this->actingAs($sinPermiso)->get(route('plan.index'))
            ->assertRedirect(route('dashboard'));
        $this->assertTrue(session()->has('aviso'));
    }

    public function test_la_pagina_carga_con_permiso_y_arma_el_gantt(): void
    {
        $response = $this->actingAs($this->usuarioQueVe())->get(route('plan.index'));

        $response->assertOk();
        $this->assertCount(count(PlanProyecto::MODULOS), $response->viewData('gantt'));

        $avance = $response->viewData('avanceGlobal');
        $this->assertGreaterThan(0, $avance);
        $this->assertLessThan(100, $avance);

        // Toda barra debe caber en el dibujo (left+width ≤ 100, con margen de redondeo).
        foreach ($response->viewData('gantt') as $key => $fila) {
            $this->assertLessThanOrEqual(100.5, $fila['left'] + $fila['width'], "La barra de [{$key}] se sale del gantt.");
        }
    }

    public function test_el_menu_muestra_el_item_solo_con_permiso(): void
    {
        // Por RUTA, no por texto pegado (doctrina anti verde-engañoso).
        $this->actingAs($this->usuarioQueVe())->get(route('dashboard'))
            ->assertSee(route('plan.index'));

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertDontSee(route('plan.index'));
    }

    // --- Extras en paralelo (lo único editable) ---

    public function test_crear_extra_exige_el_permiso_de_gestion(): void
    {
        // Con solo 'ver plan proyecto' NO se puede escribir. Las ACCIONES
        // (no-GET) conservan su 403 (D-014: solo la navegación GET se
        // redirige al Inicio con aviso).
        $this->actingAs($this->usuarioQueVe())
            ->post(route('plan.extras.store'), ['titulo' => 'Feature colada', 'estado' => 'en_curso', 'avance' => 10])
            ->assertForbidden();
        $this->assertDatabaseCount('plan_extras', 0);
    }

    public function test_crear_editar_y_eliminar_un_extra(): void
    {
        $gestor = $this->usuarioQueGestiona();

        $this->actingAs($gestor)->post(route('plan.extras.store'), [
            'titulo' => 'Modo Personalizar del dashboard',
            'descripcion' => 'Colores por usuario, pedido del jefe',
            'estado' => 'en_curso',
            'avance' => 40,
            'responsable' => 'Claude',
        ])->assertRedirect(route('plan.index'));

        $extra = PlanExtra::firstOrFail();
        $this->assertSame('en_curso', $extra->estado);

        $this->actingAs($gestor)->patch(route('plan.extras.update', $extra), [
            'titulo' => 'Modo Personalizar del dashboard',
            'estado' => 'finalizada',
            'avance' => 100,
        ])->assertRedirect(route('plan.index'));
        $this->assertSame('finalizada', $extra->fresh()->estado);
        $this->assertSame(100, $extra->fresh()->avance);

        $this->actingAs($gestor)->delete(route('plan.extras.destroy', $extra))
            ->assertRedirect(route('plan.index'));
        $this->assertDatabaseCount('plan_extras', 0);
    }

    public function test_extra_valida_estado_y_avance(): void
    {
        $gestor = $this->usuarioQueGestiona();

        $this->actingAs($gestor)->post(route('plan.extras.store'), [
            'titulo' => 'X', 'estado' => 'terminado', 'avance' => 10,
        ])->assertSessionHasErrors('estado');

        $this->actingAs($gestor)->post(route('plan.extras.store'), [
            'titulo' => 'X', 'estado' => 'en_curso', 'avance' => 101,
        ])->assertSessionHasErrors('avance');

        $this->assertDatabaseCount('plan_extras', 0);
    }

    public function test_los_extras_se_listan_en_la_pagina(): void
    {
        PlanExtra::create(['titulo' => 'Cola offline de tandas', 'estado' => 'finalizada', 'avance' => 100]);

        $this->actingAs($this->usuarioQueVe())->get(route('plan.index'))
            ->assertOk()
            ->assertSee('Cola offline de tandas');
    }
}
