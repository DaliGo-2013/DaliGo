<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        $real = fake()->numberBetween(0, 500);
        $reservado = fake()->numberBetween(0, min($real, 20));

        return [
            'bodega_id' => Bodega::factory(),
            'producto_id' => Producto::factory(),
            'stock_real' => $real,
            'stock_reservado' => $reservado,
            'stock_disponible' => $real - $reservado,
            'bsale_stock_id' => fake()->unique()->numberBetween(1, 999999),
        ];
    }
}
