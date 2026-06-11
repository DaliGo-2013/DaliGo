<?php

namespace Database\Factories;

use App\Models\ListaPrecio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListaPrecio>
 */
class ListaPrecioFactory extends Factory
{
    protected $model = ListaPrecio::class;

    public function definition(): array
    {
        return [
            'nombre' => strtoupper(fake()->unique()->city()),
            'descripcion' => null,
            'bsale_coin_id' => ListaPrecio::COIN_CLP,
            'activa' => true,
            'canal' => null,
            'bsale_price_list_id' => fake()->unique()->numberBetween(1, 999999),
        ];
    }
}
