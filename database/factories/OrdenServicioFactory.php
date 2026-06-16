<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrdenServicio>
 */
class OrdenServicioFactory extends Factory
{
    protected $model = OrdenServicio::class;

    public function definition(): array
    {
        $cuerpo = fake()->unique()->numberBetween(1000000, 25999999);

        return [
            'cliente_id' => Cliente::factory(),
            'cliente_nombre' => fake()->name(),
            'cliente_rut' => $cuerpo.'-'.Cliente::dvRut($cuerpo),
            'producto_id' => null,
            'sucursal_id' => null,
            'fecha_ingreso' => now()->toDateString(),
            'tipo_equipo' => fake()->randomElement(OrdenServicio::TIPOS),
            'modelo' => fake()->optional()->bothify('Mod-###'),
            'numero_serie' => fake()->optional()->bothify('SN-#######'),
            'falla_reportada' => fake()->optional()->sentence(),
            'estado' => fake()->randomElement(OrdenServicio::ESTADOS),
            'facturacion' => fake()->randomElement(OrdenServicio::FACTURACION),
            'observaciones' => null,
            'fecha_entrega' => null,
            'fuente' => 'mostrador',
        ];
    }
}
