<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\LoteServicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoteServicio>
 */
class LoteServicioFactory extends Factory
{
    protected $model = LoteServicio::class;

    public function definition(): array
    {
        $cuerpo = fake()->unique()->numberBetween(60000000, 76999999);

        return [
            'cliente_id' => Cliente::factory(),
            'cliente_nombre' => fake()->company(),
            'cliente_rut' => $cuerpo.'-'.Cliente::dvRut($cuerpo),
            'cliente_email' => fake()->companyEmail(),
            'cliente_telefono' => null,
            'origen_ciudad' => fake()->randomElement(['Los Andes', 'Curicó', 'Talca']),
            'sucursal_id' => null,
            'conductor_id' => null,
            'fecha_ingreso' => now()->toDateString(),
            'tipo_default' => 'dispensador',
            'facturacion_default' => 'reparacion',
            'falla_default' => null,
            'total_ordenes' => 0,
        ];
    }
}
