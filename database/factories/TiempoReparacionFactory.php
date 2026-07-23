<?php

namespace Database\Factories;

use App\Models\TiempoReparacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TiempoReparacion>
 */
class TiempoReparacionFactory extends Factory
{
    protected $model = TiempoReparacion::class;

    public function definition(): array
    {
        return [
            'trabajo' => 'Trabajo '.$this->faker->unique()->numberBetween(1, 100000),
            'horas' => $this->faker->randomElement([0.5, 1.0, 1.5, 2.0]),
            'grupo' => 'Reparada',
            'activo' => true,
        ];
    }
}
