<?php

namespace Database\Factories;

use App\Models\Bodega;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bodega>
 */
class BodegaFactory extends Factory
{
    protected $model = Bodega::class;

    public function definition(): array
    {
        return [
            'nombre' => strtoupper(fake()->unique()->streetName()),
            'direccion' => fake()->optional()->streetAddress(),
            'comuna' => fake()->optional()->city(),
            'ciudad' => 'Santiago',
            'email' => fake()->optional()->companyEmail(),
            'es_virtual' => false,
            'activa' => true,
            'bsale_default_price_list_id' => null,
            'bsale_office_id' => fake()->unique()->numberBetween(1, 999999),
        ];
    }
}
