<?php

namespace Tests\Feature\Admin;

use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionMovimiento;
use App\Models\ProduccionReporte;
use App\Models\Receta;
use App\Models\TipoBotellon;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backflush por receta (P-M11-10): al aprobar, el kardex descuenta
 * componentes = (buenos + merma) × receta. Los candados del dictado v40:
 * la merma TAMBIÉN consume (mutar la fórmula a solo-buenos → rojo), devolver
 * jamás genera movimiento, re-aprobar no duplica, y una receta editada solo
 * afecta aprobaciones futuras. El contrato SIN receta vive intacto en
 * ProduccionKardexTest (fallback = receta implícita {preforma: 1}).
 */
class RecetaBackflushTest extends TestCase
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

    private function producto(string $sku, string $categoria = 'Botellones'): Producto
    {
        return Producto::create(['sku' => $sku, 'nombre' => $sku, 'categoria' => $categoria, 'activo' => true]);
    }

    /**
     * Reporte ENVIADO con tandas [tipo => cantidades], preforma asignada
     * (o null), listo para aprobar. Mismo idioma que ProduccionKardexTest.
     */
    private function reporteEnviado(?Producto $preforma, array $tandas): ProduccionReporte
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $total = collect($tandas)->flatten()->sum();

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => $total,
            'preforma_id' => $preforma?->id,
        ]);

        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => $total,
            'estado' => ProduccionReporte::ENVIADO,
            'enviado_at' => now(),
        ]);

        foreach ($tandas as $tipoId => $c) {
            $reporte->registros()->create([
                'tipo_botellon_id' => $tipoId,
                'primera' => $c['primera'], 'segunda' => $c['segunda'],
                'malo' => $c['malo'], 'danada' => $c['danada'],
            ]);
        }
        $reporte->recalcularDesdeRegistros();

        return $reporte->refresh();
    }

    private function tipoConProducto(string $codigo): array
    {
        $botellon = $this->producto('BOT-'.$codigo);
        $tipo = TipoBotellon::create(['codigo' => $codigo, 'nombre' => 'Tipo '.$codigo, 'producto_id' => $botellon->id, 'activo' => true]);

        return [$tipo, $botellon];
    }

    // --- Candado 1 del dictado: (buenos + merma) × receta ---

    public function test_el_consumo_por_receta_incluye_la_merma(): void
    {
        $preforma = $this->producto('PRE-1', 'Preformas');
        $tapaProducto = $this->producto('TAPA-1', 'Tapas');
        [$tipo, $botellon] = $this->tipoConProducto('T1');

        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'cantidad' => 1, 'confirmada' => true]);
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'componente_id' => $tapaProducto->id, 'cantidad' => 2, 'confirmada' => true]);

        // buenos = 90 (80 + 10), merma = 10 (5 + 5) → base 100.
        $reporte = $this->reporteEnviado($preforma, [
            $tipo->id => ['primera' => 80, 'segunda' => 10, 'malo' => 5, 'danada' => 5],
        ]);

        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.reporte.aprobar', $reporte))
            ->assertRedirect(route('admin.produccion.index'));

        // Preforma: 100 × 1 contra la preforma de la ASIGNACIÓN; tapa: 100 × 2
        // contra el componente de la receta. Con solo-buenos serían 90 y 180:
        // este assert es el candado mutable del dictado.
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $preforma->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 100]);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $tapaProducto->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_TAPA, 'cantidad' => 200]);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $botellon->id, 'tipo' => ProduccionMovimiento::TIPO_PRODUCCION_PRIMERA, 'cantidad' => 80]);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $botellon->id, 'tipo' => ProduccionMovimiento::TIPO_PRODUCCION_SEGUNDA, 'cantidad' => 10]);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $botellon->id, 'tipo' => ProduccionMovimiento::TIPO_MERMA, 'cantidad' => 10]);

        // Conteo EXACTO: consumo preforma + consumo tapa + 1ª + 2ª + merma.
        $this->assertSame(5, $reporte->movimientos()->count());
    }

    // --- Candado 2: devolver jamás genera; re-aprobar no duplica ---

    public function test_devolver_no_genera_y_aprobar_tras_devolucion_no_duplica(): void
    {
        $preforma = $this->producto('PRE-1', 'Preformas');
        [$tipo, $botellon] = $this->tipoConProducto('T1');
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'cantidad' => 1, 'confirmada' => true]);

        $reporte = $this->reporteEnviado($preforma, [
            $tipo->id => ['primera' => 50, 'segunda' => 0, 'malo' => 0, 'danada' => 0],
        ]);
        $jefe = $this->jefe();

        // Devolver con receta presente: CERO movimientos.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.devolver', $reporte), [
            'devuelto_motivo' => 'Revisa la segunda tanda',
        ]);
        $this->assertSame(0, ProduccionMovimiento::where('reporte_id', $reporte->id)->count());

        // El soplador corrige y re-envía; aprobar genera UN solo set.
        $reporte->fresh()->update(['estado' => ProduccionReporte::ENVIADO, 'enviado_at' => now()]);
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));
        $movimientos = ProduccionMovimiento::where('reporte_id', $reporte->id)->count();
        $this->assertGreaterThan(0, $movimientos);

        // Doble submit de aprobar: mismo set, sin duplicar.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));
        $this->assertSame($movimientos, ProduccionMovimiento::where('reporte_id', $reporte->id)->count());
    }

    // --- Candado 3: la receta editada no reescribe el kardex pasado ---

    public function test_editar_la_receta_no_reescribe_movimientos_pasados(): void
    {
        $preforma = $this->producto('PRE-1', 'Preformas');
        $tapaProducto = $this->producto('TAPA-1', 'Tapas');
        [$tipo, $botellon] = $this->tipoConProducto('T1');
        $receta = Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'componente_id' => $tapaProducto->id, 'cantidad' => 2, 'confirmada' => true]);

        $reporte = $this->reporteEnviado($preforma, [
            $tipo->id => ['primera' => 40, 'segunda' => 0, 'malo' => 10, 'danada' => 0],
        ]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        $antes = ProduccionMovimiento::where('reporte_id', $reporte->id)
            ->orderBy('id')->get(['tipo', 'producto_id', 'cantidad'])->toArray();

        // Editar la receta DESPUÉS de aprobar (cantidad y componente).
        $otraTapa = $this->producto('TAPA-2', 'Tapas');
        $receta->update(['cantidad' => 5, 'componente_id' => $otraTapa->id]);

        $despues = ProduccionMovimiento::where('reporte_id', $reporte->id)
            ->orderBy('id')->get(['tipo', 'producto_id', 'cantidad'])->toArray();

        // El kardex es snapshot: byte a byte lo mismo.
        $this->assertSame($antes, $despues);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_TAPA, 'cantidad' => 100]);
    }

    // --- Reporte mixto: cada tanda por su rama, sin doble conteo ---

    public function test_reporte_mixto_resuelve_por_tanda_sin_doble_conteo(): void
    {
        $preforma = $this->producto('PRE-1', 'Preformas');
        $tapaProducto = $this->producto('TAPA-1', 'Tapas');
        [$tipoA, $botellonA] = $this->tipoConProducto('TA');
        [$tipoB] = $this->tipoConProducto('TB');    // SIN receta: rama implícita.

        Receta::create(['producto_id' => $botellonA->id, 'rol' => Receta::ROL_TAPA, 'componente_id' => $tapaProducto->id, 'cantidad' => 1, 'confirmada' => true]);

        $reporte = $this->reporteEnviado($preforma, [
            $tipoA->id => ['primera' => 40, 'segunda' => 0, 'malo' => 10, 'danada' => 0],   // 50 unidades
            $tipoB->id => ['primera' => 30, 'segunda' => 0, 'malo' => 0, 'danada' => 0],    // 30 unidades
        ]);

        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        // UN solo consumo de preforma con TODO (50 × 1 + 30 × 1 implícita = 80)
        // y la tapa SOLO de la tanda con receta (50).
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 80]);
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_TAPA, 'cantidad' => 50]);

        // Conteo exacto: preforma + tapa + (1ª y merma de A) + (1ª de B) = 5.
        $this->assertSame(5, $reporte->movimientos()->count());
    }

    // --- El producto del consumo de preforma ---

    public function test_la_preforma_de_la_asignacion_gana_al_componente_de_la_receta(): void
    {
        $preformaTurno = $this->producto('PRE-600', 'Preformas');
        $preformaReceta = $this->producto('PRE-750', 'Preformas');
        [$tipo, $botellon] = $this->tipoConProducto('T1');
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'componente_id' => $preformaReceta->id, 'cantidad' => 1, 'confirmada' => true]);

        $reporte = $this->reporteEnviado($preformaTurno, [
            $tipo->id => ['primera' => 20, 'segunda' => 0, 'malo' => 0, 'danada' => 0],
        ]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        // La asignación es la verdad física del turno.
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $preformaTurno->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 20]);
        $this->assertDatabaseMissing('produccion_movimientos', ['producto_id' => $preformaReceta->id]);
    }

    public function test_sin_preforma_asignada_cae_al_componente_de_la_receta(): void
    {
        $preformaReceta = $this->producto('PRE-750', 'Preformas');
        [$tipo, $botellon] = $this->tipoConProducto('T1');
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'componente_id' => $preformaReceta->id, 'cantidad' => 1, 'confirmada' => true]);

        $reporte = $this->reporteEnviado(null, [
            $tipo->id => ['primera' => 20, 'segunda' => 0, 'malo' => 0, 'danada' => 0],
        ]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => $preformaReceta->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 20]);
    }

    public function test_tapa_sin_componente_registra_movimiento_sin_producto(): void
    {
        $preforma = $this->producto('PRE-1', 'Preformas');
        [$tipo, $botellon] = $this->tipoConProducto('T1');
        // La hipótesis del seeder: tapa declarada, producto aún sin enlazar.
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'cantidad' => 1, 'confirmada' => false]);

        $reporte = $this->reporteEnviado($preforma, [
            $tipo->id => ['primera' => 25, 'segunda' => 0, 'malo' => 0, 'danada' => 0],
        ]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        // Degradación con gracia (patrón del kardex) — y de paso fija que
        // `confirmada=false` NO es gate: la hipótesis opera igual.
        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'producto_id' => null, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_TAPA, 'cantidad' => 25]);
    }

    // --- Redondeo: uno solo, sobre el agregado ---

    public function test_redondeo_half_up_sobre_el_agregado(): void
    {
        $preforma = $this->producto('PRE-1', 'Preformas');
        [$tipo, $botellon] = $this->tipoConProducto('T1');
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_PREFORMA, 'cantidad' => 1.5, 'confirmada' => true]);

        // 5 unidades × 1.5 = 7.5 → 8 (half-up). Un (int) pelado truncaría a 7.
        $reporte = $this->reporteEnviado($preforma, [
            $tipo->id => ['primera' => 3, 'segunda' => 1, 'malo' => 1, 'danada' => 0],
        ]);
        $this->actingAs($this->jefe())->post(route('admin.produccion.reporte.aprobar', $reporte));

        $this->assertDatabaseHas('produccion_movimientos', ['reporte_id' => $reporte->id, 'tipo' => ProduccionMovimiento::TIPO_CONSUMO_PREFORMA, 'cantidad' => 8]);
    }

    // --- Preview == kardex (doctrina M-1 extendida) ---

    public function test_el_preview_muestra_lo_que_generara_no_los_totales_ajustados(): void
    {
        $preforma = $this->producto('PRE-1', 'Preformas');
        $tapaProducto = $this->producto('TAPA-1', 'Tapas');
        [$tipo, $botellon] = $this->tipoConProducto('T1');
        Receta::create(['producto_id' => $botellon->id, 'rol' => Receta::ROL_TAPA, 'componente_id' => $tapaProducto->id, 'cantidad' => 2, 'confirmada' => true]);

        $reporte = $this->reporteEnviado($preforma, [
            $tipo->id => ['primera' => 80, 'segunda' => 10, 'malo' => 5, 'danada' => 5],
        ]);

        // El jefe pisa los totales SIN tocar las tandas (ajustar): el preview
        // debe seguir mostrando lo que el kardex escribirá (las tandas), no el
        // total denormalizado — antes de P-M11-10 el preview usaba el total.
        $reporte->update(['total' => 500, 'motivo_ajuste' => 'dedazo corregido']);

        $html = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Al aprobar se registrará en el kardex', $html);
        $this->assertStringContainsString('Consumo de tapa', $html);
        $this->assertStringContainsString('−100', $html);   // preforma: tandas, no 500
        $this->assertStringContainsString('−200', $html);   // tapa: 100 × 2
        $this->assertStringNotContainsString('−500', $html);
    }
}
