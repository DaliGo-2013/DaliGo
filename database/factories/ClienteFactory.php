<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        // RUT sintetico con DV valido (modulo 11), unico por numero de cuerpo.
        $cuerpo = fake()->unique()->numberBetween(1000000, 25999999);

        return [
            'rut' => $cuerpo.'-'.Cliente::dvRut($cuerpo),
            'razon_social' => fake()->company(),
            'giro' => fake()->optional()->bs(),
            'email' => fake()->optional()->safeEmail(),
            'telefono' => fake()->optional()->phoneNumber(),
            'direccion' => fake()->optional()->streetAddress(),
            'ciudad' => fake()->optional()->city(),
            'comuna' => null,
            'es_empresa' => fake()->boolean(),
            'envio_factura_email' => false,
            'activo' => true,
            'segmento' => fake()->optional()->randomElement(Cliente::SEGMENTOS),
            'notas' => null,
            'vendedor_id' => null,
            'bsale_client_id' => null,
        ];
    }
}
