<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Devolucion>
 *
 * `estado` NO es aleatorio a propósito (lección bitácora 2026-07-13: una
 * factory con estado al azar hizo flaky un test que assertaba sobre él).
 * El default es el caso normal (recién solicitada); los demás son estados
 * con nombre.
 */
class DevolucionFactory extends Factory
{
    protected $model = Devolucion::class;

    public function definition(): array
    {
        $cuerpo = fake()->unique()->numberBetween(1000000, 25999999);

        return [
            'folio' => Devolucion::generarFolioUnico(),
            'token' => Str::random(64),
            'estado' => Devolucion::SOLICITADA,
            'canal' => 'mercado_libre',
            'causa' => null,
            'cliente_id' => null,
            'cliente_rut' => $cuerpo.'-'.Cliente::dvRut($cuerpo),
            'cliente_nombre' => fake()->name(),
            'cliente_email' => fake()->safeEmail(),
            'cliente_telefono' => '+569'.fake()->numerify('########'),
            'documento_venta_id' => null,
            'folio_referencia' => fake()->optional()->numerify('######'),
            'monto_reembolso' => null,
            'motivo' => fake()->sentence(),
            'sucursal_id' => Sucursal::query()->value('id') ?? Sucursal::factory(),
            'ip' => fake()->ipv4(),
            'user_agent' => 'FactoryAgent/1.0',
        ];
    }

    public function recibida(): static
    {
        return $this->state(fn () => [
            'estado' => Devolucion::RECIBIDA,
            'recibida_at' => now(),
        ]);
    }

    public function evaluada(string $causa = 'fabrica'): static
    {
        return $this->state(fn () => [
            'estado' => Devolucion::EVALUADA,
            'causa' => $causa,
            'recibida_at' => now(),
        ]);
    }
}
