<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\BodegaTraslado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BodegaTraslado>
 */
class BodegaTrasladoFactory extends Factory
{
    protected $model = BodegaTraslado::class;

    public function definition(): array
    {
        return [
            'bodega_id' => Bodega::factory(),
            'bodega_destino_id' => Bodega::factory()->clasificada(),
            'estado' => BodegaTraslado::PENDIENTE,
            'solicitante_id' => User::factory(),
            'solicitante_nombre' => fake()->name(),
        ];
    }
}
