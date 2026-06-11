<?php

namespace Database\Factories;

use App\Models\ListaPrecio;
use App\Models\Precio;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Precio>
 */
class PrecioFactory extends Factory
{
    protected $model = Precio::class;

    public function definition(): array
    {
        $bruto = fake()->numberBetween(500, 500000);

        return [
            'lista_precio_id' => ListaPrecio::factory(),
            'producto_id' => Producto::factory(),
            'precio_neto' => round($bruto / 1.19, 4),
            'precio_con_iva' => $bruto,
            'bsale_detail_id' => fake()->unique()->numberBetween(1, 999999),
        ];
    }
}
