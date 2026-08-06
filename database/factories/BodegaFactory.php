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
        // El default es "recién llegada del sync": sin clasificar
        // (proposito null, clasificacion_confirmada false, en_operacion true),
        // igual que los defaults de la migración.
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

    /** Clasificada y confirmada desde la UI (el estado "en régimen"). */
    public function clasificada(string $proposito = 'fisica'): static
    {
        return $this->state(fn () => [
            'proposito' => $proposito,
            'clasificacion_confirmada' => true,
        ]);
    }

    /** Fuera de las pantallas operativas (las 6 muertas de D-003). */
    public function fueraDeOperacion(): static
    {
        return $this->state(fn () => [
            'proposito' => 'cerrada',
            'en_operacion' => false,
            'clasificacion_confirmada' => true,
        ]);
    }
}
