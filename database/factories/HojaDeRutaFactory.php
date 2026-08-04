<?php

namespace Database\Factories;

use App\Models\HojaDeRuta;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hoja de ruta sintética. El folio va con secuencia única de faker (los
 * tests del correlativo real NO usan la factory: llaman a
 * HojaRutaService::crear(), que es quien asigna folios en producción).
 * Estado por defecto FIJO en borrador; las transiciones se prueban por el
 * service, no seteando estado a mano.
 *
 * @extends Factory<HojaDeRuta>
 */
class HojaDeRutaFactory extends Factory
{
    protected $model = HojaDeRuta::class;

    public function definition(): array
    {
        return [
            'folio' => fake()->unique()->numberBetween(1000, 99999),
            'sucursal_id' => Sucursal::factory(),
            'zona_id' => Zona::factory(),
            'vehiculo_id' => null,
            'vehiculo' => 'Camión 3/4 sintético',
            'patente' => strtoupper(fake()->unique()->bothify('TT??##')),
            'conductor_id' => User::factory(),
            'peoneta_nombre' => null,
            'estado' => HojaDeRuta::BORRADOR,
        ];
    }

    /** En la calle: el estado que habilita al conductor (scoping P-DSP-08). */
    public function enRuta(): static
    {
        return $this->state(fn () => [
            'estado' => HojaDeRuta::EN_RUTA,
            'pagos_ok_at' => now()->subHours(4),
            'ruta_autorizada_at' => now()->subHours(3),
            'cargada_at' => now()->subHours(2),
            'en_ruta_at' => now()->subHour(),
        ]);
    }
}
