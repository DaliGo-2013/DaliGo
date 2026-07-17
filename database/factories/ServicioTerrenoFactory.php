<?php

namespace Database\Factories;

use App\Models\ServicioTerreno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServicioTerreno>
 */
class ServicioTerrenoFactory extends Factory
{
    protected $model = ServicioTerreno::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Servicio '.fake()->unique()->word(),
            'valor_uf' => fake()->randomElement([1, 1.5, 2, 2.5, 3]),
            'duracion' => fake()->randomElement(['1 día', '1/2 día']),
            'incluye' => fake()->optional()->sentence(),
            'observaciones' => null,
            'activo' => true,
        ];
    }
}
