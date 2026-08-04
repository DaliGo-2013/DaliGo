<?php

namespace Database\Factories;

use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehiculo>
 */
class VehiculoFactory extends Factory
{
    protected $model = Vehiculo::class;

    public function definition(): array
    {
        return [
            // Formato de patente chilena nueva (4 letras + 2 dígitos).
            'ppu' => strtoupper(fake()->unique()->bothify('????##')),
            'alias' => fake()->randomElement(['RAM Pedro', 'HD35 Coquimbo', 'Colorado Mirador', 'Maxus Herramientas']),
            'marca' => fake()->randomElement(['Hyundai', 'RAM', 'Chevrolet', 'Maxus', 'Hino']),
            'modelo' => fake()->bothify('Modelo ##'),
            'anio' => fake()->numberBetween(2018, 2025),
            'tipo' => 'camioneta',
            'combustible' => 'diesel',
            'base' => fake()->randomElement(Vehiculo::BASES),
            'conductor_nombre' => fake()->name(),
            'estado' => Vehiculo::ESTADO_ACTIVO,
            // Sin fechas de documentos por defecto: cada test pone las que
            // necesita para su escenario de semáforo.
        ];
    }

    /** Todos los documentos al día (vencen dentro de un año). */
    public function alDia(): static
    {
        return $this->state(fn () => [
            'rt_vence' => now()->addYear()->toDateString(),
            'emisiones_vence' => now()->addYear()->toDateString(),
            'permiso_circulacion_vence' => now()->addMonths(8)->toDateString(),
            'soap_vence' => now()->addMonths(8)->toDateString(),
            'extintor_vence' => now()->addMonths(6)->toDateString(),
        ]);
    }
}
