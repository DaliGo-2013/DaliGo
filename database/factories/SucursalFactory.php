<?php

namespace Database\Factories;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sucursal>
 */
class SucursalFactory extends Factory
{
    protected $model = Sucursal::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->city(),
            'codigo' => strtoupper(fake()->unique()->bothify('SUC-####')),
            'ciudad' => fake()->city(),
            'direccion' => fake()->streetAddress(),
            'es_central' => false,
            'activa' => true,
        ];
    }
}
