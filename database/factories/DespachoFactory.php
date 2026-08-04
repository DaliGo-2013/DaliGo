<?php

namespace Database\Factories;

use App\Models\Despacho;
use App\Models\DocumentoVenta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Reemplaza el helper despacho() que vivía TRIPLICADO en los tests de
 * despachos (cada copia con su contador anti-unique). El estado por defecto
 * es FIJO (preparado, sin conductor): todo test que asserte sobre estado o
 * conductor los fija en el create — doctrina bitácora 2026-07-13.
 *
 * @extends Factory<Despacho>
 */
class DespachoFactory extends Factory
{
    protected $model = Despacho::class;

    public function definition(): array
    {
        return [
            // codigo lo autogenera el booted() del modelo (DSP- impredecible).
            'documento_venta_id' => DocumentoVenta::factory(),
            'zona_id' => null,
            'estado' => Despacho::PREPARADO,
            'conductor_id' => null,
        ];
    }

    /** Ya salió de bodega (lo que la PWA del conductor lista y entrega). */
    public function retirado(): static
    {
        return $this->state(fn () => [
            'estado' => Despacho::RETIRADO,
            'retirado_at' => now()->subHour(),
        ]);
    }
}
