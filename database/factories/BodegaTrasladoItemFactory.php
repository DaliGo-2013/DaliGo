<?php

namespace Database\Factories;

use App\Models\BodegaTraslado;
use App\Models\BodegaTrasladoItem;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BodegaTrasladoItem>
 */
class BodegaTrasladoItemFactory extends Factory
{
    protected $model = BodegaTrasladoItem::class;

    public function definition(): array
    {
        return [
            'bodega_traslado_id' => BodegaTraslado::factory(),
            'producto_id' => Producto::factory(),
            'nombre' => fake()->words(3, true),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'cantidad' => fake()->numberBetween(1, 200),
        ];
    }
}
