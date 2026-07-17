<?php

namespace Database\Factories;

use App\Models\Instalacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instalacion>
 */
class InstalacionFactory extends Factory
{
    protected $model = Instalacion::class;

    public function definition(): array
    {
        return [
            'fecha' => now()->toDateString(),
            'cliente_nombre' => $this->faker->company(),
            'cliente_rut' => null,
            'comuna_region' => $this->faker->city(),
            'categoria' => $this->faker->randomElement(Instalacion::CATEGORIAS),
            'producto' => 'LAVADORA BOTELLON 20L-220V',
            'instalacion' => true,
            'puesta_en_marcha' => false,
            'dias' => $this->faker->numberBetween(1, 3),
            'vendedor' => $this->faker->randomElement(Instalacion::VENDEDORES_SUGERIDOS),
            'n_factura' => (string) $this->faker->numberBetween(250000, 259999),
            'forma_pago' => $this->faker->randomElement(Instalacion::FORMAS_PAGO),
            'creado_por' => 'Test',
        ];
    }
}
