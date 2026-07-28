<?php

namespace Tests\Feature\Dte;

use App\Models\DteEmitido;
use App\Models\OrdenServicio;
use App\Services\Dte\EstadoSii;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registro local de documentos tributarios emitidos (M05): la tabla y sus
 * garantías. La más importante es la UNICIDAD de sales_id, que es lo que impide
 * emitir dos veces el mismo documento.
 */
class DteEmitidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_sales_id_es_unico_para_que_no_se_emita_dos_veces(): void
    {
        DteEmitido::create($this->fila(['sales_id' => 'ST-1234']));

        // Un doble clic, un reintento o un timeout no pueden crear un segundo
        // documento para el mismo origen: la BD lo impide.
        $this->expectException(QueryException::class);
        DteEmitido::create($this->fila(['sales_id' => 'ST-1234']));
    }

    public function test_nace_pendiente_y_sin_folio(): void
    {
        // La fila se crea ANTES de llamar al emisor (para reservar el sales_id),
        // así que todavía no hay folio ni veredicto del SII.
        $dte = DteEmitido::create($this->fila());

        $this->assertSame(EstadoSii::PENDIENTE, $dte->estado_sii);
        $this->assertNull($dte->folio);
        $this->assertSame('sin folio', $dte->folio_label);
        $this->assertSame('bsale', $dte->emisor);
    }

    public function test_expone_etiquetas_legibles_del_tipo_y_del_estado(): void
    {
        $dte = DteEmitido::create($this->fila([
            'tipo_dte' => 33,
            'estado_sii' => EstadoSii::ACEPTADO,
            'folio' => 273480,
        ]));

        $this->assertSame('Factura electrónica', $dte->tipo_label);
        $this->assertSame('Aceptado por el SII', $dte->estado_label);
        $this->assertSame('273480', $dte->folio_label);
        // Cerrado-bien = neutral, igual que 'entregado' en el taller.
        $this->assertSame('neutral', $dte->estado_variante);
    }

    public function test_por_reconsultar_trae_solo_los_que_esperan_veredicto(): void
    {
        $pendiente = DteEmitido::create($this->fila([
            'sales_id' => 'ST-1', 'estado_sii' => EstadoSii::PENDIENTE, 'documento_externo_id' => '10',
        ]));
        $enviado = DteEmitido::create($this->fila([
            'sales_id' => 'ST-2', 'estado_sii' => EstadoSii::ENVIADO, 'documento_externo_id' => '11',
        ]));
        // Finales: no se reconsultan.
        DteEmitido::create($this->fila(['sales_id' => 'ST-3', 'estado_sii' => EstadoSii::ACEPTADO, 'documento_externo_id' => '12']));
        DteEmitido::create($this->fila(['sales_id' => 'ST-4', 'estado_sii' => EstadoSii::RECHAZADO, 'documento_externo_id' => '13']));
        // Sin id externo no hay nada que consultar (la emisión ni salió).
        DteEmitido::create($this->fila(['sales_id' => 'ST-5', 'estado_sii' => EstadoSii::PENDIENTE, 'documento_externo_id' => null]));

        $ids = DteEmitido::porReconsultar()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pendiente->id, $enviado->id], $ids);
    }

    public function test_rechazados_los_aisla_para_corregirlos(): void
    {
        $rechazado = DteEmitido::create($this->fila(['sales_id' => 'ST-9', 'estado_sii' => EstadoSii::RECHAZADO]));
        DteEmitido::create($this->fila(['sales_id' => 'ST-10', 'estado_sii' => EstadoSii::ACEPTADO]));

        $this->assertSame([$rechazado->id], DteEmitido::rechazados()->pluck('id')->all());
    }

    public function test_se_puede_enlazar_a_su_orden_de_origen(): void
    {
        $orden = OrdenServicio::factory()->create();
        $dte = DteEmitido::create($this->fila(['orden_servicio_id' => $orden->id]));

        $this->assertSame($orden->id, $dte->ordenServicio->id);
    }

    public function test_los_montos_quedan_como_enteros(): void
    {
        $dte = DteEmitido::create($this->fila(['neto' => 59_244, 'iva' => 11_256, 'total' => 70_500]));

        $this->assertSame(59_244, $dte->fresh()->neto);
        $this->assertSame(11_256, $dte->fresh()->iva);
        $this->assertSame(70_500, $dte->fresh()->total);
    }

    /** @return array<string, mixed> */
    private function fila(array $overrides = []): array
    {
        return array_merge([
            'tipo_dte' => 33,
            'sales_id' => 'ST-'.fake()->unique()->numberBetween(1, 999999),
            'receptor_rut' => '14468660-8',
            'receptor_nombre' => 'Nahim Daniel Escudero Moll',
        ], $overrides);
    }
}
