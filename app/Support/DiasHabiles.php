<?php

namespace App\Support;

use App\Models\Configuracion;
use Illuminate\Support\Carbon;

/**
 * Días hábiles para citar al cliente (dueño 07-08: «que retire su dispensador
 * al día siguiente que sea hábil — si es feriado o fin de semana que no lo
 * tome, para que no llegue por error»).
 *
 * Hábil = lunes a viernes que no esté en la lista `feriados_chile` de
 * Configuración (fechas YYYY-MM-DD, editable). La lista se siembra con los
 * feriados conocidos y HAY QUE RENOVARLA cada año con los movibles
 * (feriados.cl); si un año no se carga, el cálculo degrada a solo saltar
 * fines de semana — cita de más, nunca rompe.
 */
class DiasHabiles
{
    public static function esHabil(Carbon $dia): bool
    {
        return ! $dia->isWeekend()
            && ! in_array($dia->toDateString(), self::feriados(), true);
    }

    /** El primer día hábil DESPUÉS de $desde (por defecto: después de hoy). */
    public static function siguiente(?Carbon $desde = null): Carbon
    {
        $dia = ($desde ?? Carbon::now())->copy()->startOfDay();

        do {
            $dia->addDay();
        } while (! self::esHabil($dia));

        return $dia;
    }

    /** «lunes 10-08-2026» — para cartas y campanitas. */
    public static function rotulo(Carbon $dia): string
    {
        return $dia->locale('es')->isoFormat('dddd DD-MM-YYYY');
    }

    /** @return list<string> fechas YYYY-MM-DD */
    private static function feriados(): array
    {
        $lista = Configuracion::get('feriados_chile', []);

        return is_array($lista) ? array_values(array_filter($lista, 'is_string')) : [];
    }
}
