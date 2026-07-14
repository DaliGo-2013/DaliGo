<?php

namespace Database\Factories;

use App\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zona>
 */
class ZonaFactory extends Factory
{
    protected $model = Zona::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Zona '.$this->faker->unique()->word(),
            'descripcion' => $this->faker->words(3, true),
            'activa' => true,
        ];
    }
}
