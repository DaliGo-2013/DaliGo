<?php

namespace Tests\Feature\Admin;

use App\Models\Maquina;
use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionParada;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Receta;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use App\Services\Produccion\Oee;
use App\Support\AvisosError;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OEE + Pareto de paradas (P-M11-11, dictado v42). Los candados:
 *  1. Las paradas PLANIFICADAS no descuentan disponibilidad (MUTADO).
 *  2. Ciclo ideal NULL → «sin ciclo cargado», jamás un rendimiento falso.
 *  3. Rendimiento sobre 100 % → aviso de ciclo mal cargado, nunca la cifra.
 *  4. El Pareto cuadra: Σ filas == Σ paradas cerradas del período.
 *  5. Una parada que cruza medianoche aporta su duración real (410 min).
 *  6. El soplador no ve el informe.
 */
class ProduccionOeeTest extends TestCase
{
    use RefreshDatabase;

    private Maquina $maquina;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'MIRADOR'],
            ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true],
        );
        $this->maquina = Maquina::create(['nombre' => 'Sopladora 1', 'sucursal_id' => $sucursal->id, 'activa' => true]);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    /** Botellón enlazado (tipo → producto), opcionalmente con ciclo ideal. */
    private function tipoConCiclo(?int $cicloSeg, string $codigo = 'T1'): TipoBotellon
    {
        $producto = Producto::create(['sku' => 'BOT-'.$codigo, 'nombre' => 'Botellón '.$codigo, 'categoria' => 'Botellones', 'activo' => true]);

        if ($cicloSeg !== null) {
            Receta::create(['producto_id' => $producto->id, 'rol' => Receta::ROL_PREFORMA, 'cantidad' => 1, 'ciclo_ideal_seg' => $cicloSeg]);
        }

        return TipoBotellon::create(['codigo' => $codigo, 'nombre' => 'Tipo '.$codigo, 'producto_id' => $producto->id, 'activo' => true]);
    }

    /** Un turno trabajado: reporte del día con una tanda en la máquina. */
    private function turnoConTanda(?TipoBotellon $tipo, array $c = ['primera' => 100, 'segunda' => 0, 'malo' => 0, 'danada' => 0], ?int $cavidades = null): ProduccionReporte
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => array_sum($c),
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => array_sum($c),
            'estado' => ProduccionReporte::ENVIADO,
            'cavidades_activas' => $cavidades,
        ]);
        $reporte->registros()->create([
            'maquina_id' => $this->maquina->id,
            'tipo_botellon_id' => $tipo?->id,
            'primera' => $c['primera'], 'segunda' => $c['segunda'],
            'malo' => $c['malo'], 'danada' => $c['danada'],
        ]);

        return $reporte;
    }

    private function parada(ProduccionReporte $reporte, string $motivo, string $inicio, ?string $fin, ?int $maquinaId = 0): ProduccionParada
    {
        return ProduccionParada::create([
            'reporte_id' => $reporte->id,
            'maquina_id' => $maquinaId === 0 ? $this->maquina->id : $maquinaId,
            'motivo' => $motivo,
            'clase' => ProduccionParada::claseDe($motivo),
            'origen' => 'maquina',
            'inicio' => $inicio,
            'fin' => $fin,
        ]);
    }

    private function rango(): array
    {
        return [now()->toDateString(), now()->toDateString()];
    }

    // --- Candado 1 (MUTADO): planificadas dentro del plan, no descuentan ---

    public function test_paradas_planificadas_no_descuentan_disponibilidad(): void
    {
        $reporte = $this->turnoConTanda($this->tipoConCiclo(null));
        $this->parada($reporte, 'Falla de máquina', '08:00', '10:00');     // no planificada, 120 min
        $this->parada($reporte, 'Cambio de molde', '10:00', '11:00');      // planificada, 60 min

        [$desde, $hasta] = $this->rango();
        $oee = app(Oee::class)->paraMaquina($this->maquina, $desde, $hasta);

        // 1 turno × 720 min − SOLO los 120 no planificados = 600/720 = 83.3 %.
        // Si las planificadas descontaran, saldría 75.0 — el candado del dictado.
        $this->assertSame(1, $oee['slots']);
        $this->assertSame(120, $oee['minutosNoPlanificadas']);
        $this->assertSame(60, $oee['minutosPlanificadas']);
        $this->assertSame(83.3, $oee['disponibilidad']);
    }

    // --- Candado 2: sin ciclo cargado se DECLARA ---

    public function test_sin_ciclo_cargado_el_rendimiento_se_declara_no_se_inventa(): void
    {
        $this->turnoConTanda($this->tipoConCiclo(null), ['primera' => 80, 'segunda' => 10, 'malo' => 5, 'danada' => 5]);

        [$desde, $hasta] = $this->rango();
        $oee = app(Oee::class)->paraMaquina($this->maquina, $desde, $hasta);

        $this->assertNull($oee['rendimiento']);
        $this->assertNull($oee['oee']);
        $this->assertSame(['Tipo T1'], $oee['sinCiclo']);
        // La disponibilidad y la calidad sí existen (los datos están).
        $this->assertSame(100.0, $oee['disponibilidad']);
        $this->assertSame(90.0, $oee['calidad']);

        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.maquina', $this->maquina))
            ->assertOk()
            ->assertSee('Sin ciclo cargado para: Tipo T1');
    }

    // --- Candado 3: sobre 100 % se avisa, jamás se muestra la cifra ---

    public function test_rendimiento_imposible_avisa_ciclo_mal_cargado_y_no_muestra_cifra(): void
    {
        // Ciclo absurdo: 600 s/unidad × 200 unidades = 2000 min ideales en un
        // turno de 720 — el rendimiento saldría 277 %: señal de dato malo.
        $this->turnoConTanda($this->tipoConCiclo(600), ['primera' => 200, 'segunda' => 0, 'malo' => 0, 'danada' => 0]);

        [$desde, $hasta] = $this->rango();
        $oee = app(Oee::class)->paraMaquina($this->maquina, $desde, $hasta);

        $this->assertTrue($oee['cicloSospechoso']);
        $this->assertNull($oee['rendimiento']);
        $this->assertNull($oee['oee']);

        $html = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.maquina', $this->maquina))
            ->assertOk()
            ->assertSee('revisa el ciclo ideal cargado en la receta')
            ->getContent();
        // El valor concreto y distinguible que tendría la cifra imposible
        // (277,8 %) — jamás un dígito suelto, que colisiona con tokens
        // aleatorios del HTML (lección assertDontSee de la bitácora 29-07).
        $this->assertStringNotContainsString('277.8', $html);
        $this->assertStringNotContainsString('277,8', $html);
    }

    // --- Rendimiento y cavidades ---

    public function test_rendimiento_usa_el_ciclo_y_las_cavidades_escalan_la_teorica(): void
    {
        // 100 unidades × 24 s = 40 min ideales / 720 disponibles = 5.6 %.
        $tipo = $this->tipoConCiclo(24);
        $this->turnoConTanda($tipo);

        [$desde, $hasta] = $this->rango();
        $this->assertSame(5.6, app(Oee::class)->paraMaquina($this->maquina, $desde, $hasta)['rendimiento']);

        // Con 2 cavidades activas cada ciclo saca 2 unidades: el tiempo ideal
        // del mismo output se parte en dos → 20 min / 720 = 2.8 %.
        ProduccionRegistro::query()->delete();
        ProduccionReporte::query()->delete();
        ProduccionAsignacion::query()->delete();
        $this->turnoConTanda($tipo, ['primera' => 100, 'segunda' => 0, 'malo' => 0, 'danada' => 0], cavidades: 2);

        $this->assertSame(2.8, app(Oee::class)->paraMaquina($this->maquina, $desde, $hasta)['rendimiento']);
    }

    // --- Candado 4: el Pareto cuadra ---

    public function test_pareto_cuadra_con_las_paradas_cerradas_del_periodo(): void
    {
        $reporte = $this->turnoConTanda($this->tipoConCiclo(null));
        $this->parada($reporte, 'Falla de máquina', '08:00', '09:40');     // 100
        $this->parada($reporte, 'Falla de máquina', '14:00', '14:50');     // 50
        $this->parada($reporte, 'Cambio de molde', '10:00', '10:30');      // 30
        $this->parada($reporte, 'Corte de luz', '16:00', null);            // ABIERTA: sin duración, fuera
        $otra = Maquina::create(['nombre' => 'Sopladora 2', 'sucursal_id' => $this->maquina->sucursal_id, 'activa' => true]);
        $this->parada($reporte, 'Molde dañado', '11:00', '12:00', $otra->id);  // de OTRA máquina

        [$desde, $hasta] = $this->rango();
        $pareto = app(Oee::class)->pareto($desde, $hasta, $this->maquina->id);

        // Σ filas == total == solo las cerradas de ESTA máquina (100+50+30).
        $this->assertSame(180, $pareto['totalMinutos']);
        $this->assertSame(180, (int) collect($pareto['motivos'])->sum('minutos'));
        $this->assertSame(3, $pareto['totalEventos']);

        // Ordenado por minutos, con clase y % acumulado que cierra en 100.
        $this->assertSame('Falla de máquina', $pareto['motivos'][0]['motivo']);
        $this->assertSame(ProduccionParada::CLASE_NO_PLANIFICADA, $pareto['motivos'][0]['clase']);
        $this->assertSame(150, $pareto['motivos'][0]['minutos']);
        $this->assertSame(2, $pareto['motivos'][0]['eventos']);
        $this->assertSame(100.0, end($pareto['motivos'])['pctAcum']);

        // El Pareto global (sin máquina) incluye la de la otra máquina.
        $this->assertSame(240, app(Oee::class)->pareto($desde, $hasta)['totalMinutos']);
    }

    // --- Candado 5: medianoche ---

    public function test_parada_que_cruza_medianoche_aporta_su_duracion_real(): void
    {
        $reporte = $this->turnoConTanda($this->tipoConCiclo(null));
        $this->parada($reporte, 'Falla de máquina', '23:40', '06:30');     // 410 min, no negativos

        [$desde, $hasta] = $this->rango();
        $oee = app(Oee::class)->paraMaquina($this->maquina, $desde, $hasta);

        $this->assertSame(410, $oee['minutosNoPlanificadas']);
        $this->assertSame(43.1, $oee['disponibilidad']);    // (720−410)/720
        $this->assertSame(410, app(Oee::class)->pareto($desde, $hasta, $this->maquina->id)['totalMinutos']);
    }

    // --- Candado 6: el soplador no ve el informe ---

    public function test_el_soplador_no_ve_el_informe_de_maquina(): void
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        $this->actingAs($soplador)
            ->get(route('admin.produccion.maquina', $this->maquina))
            ->assertRedirect(route('dashboard'));
        $this->assertSame(AvisosError::SIN_PERMISO, session('aviso'));

        $this->actingAs($soplador)
            ->get(route('admin.produccion.index'))
            ->assertRedirect(route('dashboard'));
    }

    // --- Merma con scrap de arranque separado ---

    public function test_la_merma_separa_el_scrap_de_arranque(): void
    {
        $reporte = $this->turnoConTanda($this->tipoConCiclo(null), ['primera' => 80, 'segunda' => 0, 'malo' => 10, 'danada' => 5]);
        $reporte->registros()->first()->update(['motivo_malo' => 'Scrap de arranque']);
        // Otra tanda con malos por otro motivo (misma máquina, mismo reporte).
        $reporte->registros()->create([
            'maquina_id' => $this->maquina->id,
            'primera' => 0, 'segunda' => 0, 'malo' => 5, 'danada' => 0,
            'motivo_malo' => 'Rebaba',
        ]);

        [$desde, $hasta] = $this->rango();
        $oee = app(Oee::class)->paraMaquina($this->maquina, $desde, $hasta);

        // merma = 10+5+5 = 20; scrap = SOLO los 10 malos con el motivo exacto.
        $this->assertSame(20, $oee['merma']);
        $this->assertSame(10, $oee['scrap']);
        $this->assertSame(50.0, $oee['scrapPct']);

        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.maquina', $this->maquina))
            ->assertOk()
            ->assertSee('de arranque:');
    }

    // --- El panel compara contra la meta ---

    public function test_el_panel_lista_el_oee_por_maquina_contra_su_meta(): void
    {
        $this->maquina->update(['oee_target' => 85]);
        $this->turnoConTanda($this->tipoConCiclo(24));

        $this->actingAs($this->jefe())
            ->get(route('admin.produccion.index'))
            ->assertOk()
            ->assertSee('OEE por máquina · periodo')
            ->assertSee('Sopladora 1')
            ->assertSee('85%');
    }

    // --- Los datos nuevos se guardan desde sus forms ---

    public function test_la_receta_guarda_el_ciclo_ideal_y_lo_borra_cuando_llega_vacio(): void
    {
        $tipo = $this->tipoConCiclo(null);

        $this->actingAs($this->jefe())->put(route('admin.recetas.update', $tipo->producto_id), [
            'cantidad_preforma' => 1,
            'ciclo_ideal_seg' => 25,
        ])->assertRedirect(route('admin.recetas.index'));
        $this->assertDatabaseHas('recetas', ['producto_id' => $tipo->producto_id, 'rol' => Receta::ROL_PREFORMA, 'ciclo_ideal_seg' => 25]);

        // El navegador manda la clave presente pero VACÍA (gotcha 2026-07-06).
        $this->actingAs($this->jefe())->put(route('admin.recetas.update', $tipo->producto_id), [
            'cantidad_preforma' => 1,
            'ciclo_ideal_seg' => '',
        ])->assertRedirect(route('admin.recetas.index'));
        $this->assertDatabaseHas('recetas', ['producto_id' => $tipo->producto_id, 'rol' => Receta::ROL_PREFORMA, 'ciclo_ideal_seg' => null]);
    }

    public function test_la_maquina_guarda_su_meta_de_oee(): void
    {
        $this->actingAs($this->jefe())->put(route('admin.maquinas.update', $this->maquina), [
            'nombre' => $this->maquina->nombre,
            'sucursal_id' => $this->maquina->sucursal_id,
            'activa' => '1',
            'oee_target' => 85,
        ]);
        $this->assertSame(85, $this->maquina->fresh()->oee_target);

        // Fuera de rango → rechazada.
        $this->actingAs($this->jefe())->put(route('admin.maquinas.update', $this->maquina), [
            'nombre' => $this->maquina->nombre,
            'sucursal_id' => $this->maquina->sucursal_id,
            'activa' => '1',
            'oee_target' => 150,
        ])->assertSessionHasErrors('oee_target');
    }
}
