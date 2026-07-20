<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * El "día de negocio" de la operación (PLAN-TIMEZONE opción C, P-TZ-01).
 *
 * DaliGo guarda todo en UTC (app.timezone intacto), pero la planta vive en
 * hora chilena: desde las 20:00/21:00 de Chile, now()->toDateString() ya es
 * MAÑANA — el turno noche dejaba de ver su producción, la cola del jefe se
 * vaciaba y la visita pública rechazaba el "hoy" del cliente. Todo "hoy"
 * OPERATIVO sale de aquí; jamás de now() directo. El motor (colas, grilla,
 * backoff, deltas) NO usa esta clase: sigue en UTC por diseño.
 */
class FechaNegocio
{
    /** Ahora, en el reloj del negocio (config daligo.tz_negocio). */
    public static function ahora(): Carbon
    {
        return Carbon::now(config('daligo.tz_negocio'));
    }

    /** El día de negocio de hoy (Y-m-d). */
    public static function hoy(): string
    {
        return static::ahora()->toDateString();
    }

    /** ¿Esta fecha (columna date / Carbon / string) es el día de negocio de hoy? */
    public static function esHoy(CarbonInterface|string $fecha): bool
    {
        $dia = $fecha instanceof CarbonInterface ? $fecha->toDateString() : Carbon::parse($fecha)->toDateString();

        return $dia === static::hoy();
    }
}
