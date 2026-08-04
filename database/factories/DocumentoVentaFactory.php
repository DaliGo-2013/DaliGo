<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\DocumentoVenta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Documento de venta ESPEJO, sintético (repo público: nada real). Nace
 * VIGENTE a propósito (cancellation_status = 0): los tests que necesiten uno
 * anulado usan el estado anulado() — así ningún assert depende del azar
 * (doctrina de la factory aleatoria, bitácora 2026-07-13).
 *
 * @extends Factory<DocumentoVenta>
 */
class DocumentoVentaFactory extends Factory
{
    protected $model = DocumentoVenta::class;

    public function definition(): array
    {
        return [
            'bsale_document_id' => fake()->unique()->numberBetween(100000, 999999),
            'folio' => fake()->unique()->numberBetween(1, 999999),
            'emitido_at' => now()->subDay(),
            'neto' => 100000,
            'iva' => 19000,
            'total' => 119000,
            'cancellation_status' => 0,
            'cliente_id' => Cliente::factory(),
        ];
    }

    /** Anulado en Bsale (para probar el rechazo de documentos no vigentes). */
    public function anulado(): static
    {
        return $this->state(fn () => [
            'cancellation_status' => 1,
            'cancellation_at' => now(),
        ]);
    }
}
