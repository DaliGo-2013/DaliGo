<?php

namespace Database\Factories;

use App\Models\AgendaTrabajo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgendaTrabajo>
 */
class AgendaTrabajoFactory extends Factory
{
    protected $model = AgendaTrabajo::class;

    public function definition(): array
    {
        return [
            'tipo' => fake()->randomElement(AgendaTrabajo::TIPOS),
            'fecha' => now()->toDateString(),
            'estado' => 'agendado',
            'servicio_terreno_id' => null,
            'cliente_id' => null,
            'cliente_nombre' => fake()->company(),
            'cliente_rut' => null,
            'cliente_telefono' => null,
            'cliente_email' => null,
            'direccion' => fake()->optional()->streetAddress(),
            'ciudad' => fake()->optional()->city(),
            'tecnico_id' => null,
            'descripcion' => fake()->optional()->sentence(),
            'creado_por' => null,
        ];
    }
}
