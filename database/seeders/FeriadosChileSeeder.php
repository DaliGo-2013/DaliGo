<?php

namespace Database\Seeders;

use App\Models\AgendaCierre;
use Illuminate\Database\Seeder;

/**
 * FERIADOS LEGALES DE CHILE, cargados como cierres de la agenda.
 *
 * Pedido del dueño (13-08-2026): «los feriados no trabaja». Se cargan de una vez en lugar de
 * hacérselos tipear al jefe de ventas: son ~17 por año y olvidarse de uno significa ofrecerle
 * al cliente un día en que no hay nadie.
 *
 * FUENTE: feriados.cl (2026 y 2027), cruzado con la prensa para las fechas movibles —Semana
 * Santa, San Pedro y San Pablo, Encuentro de Dos Mundos— que cambian de día cada año por la
 * ley de feriados. NO se calculan acá: computar la Pascua para ahorrarse una tabla es la clase
 * de ingenio que falla en silencio un año cualquiera.
 *
 * SOLO LOS NACIONALES. Los regionales (Arica, Chillán) quedan afuera a propósito: la empresa
 * atiende desde Talca y un feriado de otra región cerraría la agenda sin motivo. Si algún día
 * hace falta, el jefe lo carga a mano.
 *
 * ES IDEMPOTENTE Y NO PISA LO CARGADO A MANO: busca por fecha + origen 'feriado', así que
 * correrlo en cada deploy no duplica nada, y las vacaciones que cargue el jefe (origen
 * 'manual') no se tocan ni aunque caigan el mismo día.
 */
class FeriadosChileSeeder extends Seeder
{
    /** fecha => nombre. Solo feriados NACIONALES. */
    public const FERIADOS = [
        // 2026
        '2026-01-01' => 'Año Nuevo',
        '2026-04-03' => 'Viernes Santo',
        '2026-04-04' => 'Sábado Santo',
        '2026-05-01' => 'Día Nacional del Trabajo',
        '2026-05-21' => 'Día de las Glorias Navales',
        '2026-06-21' => 'Día Nacional de los Pueblos Indígenas',
        '2026-06-29' => 'San Pedro y San Pablo',
        '2026-07-16' => 'Día de la Virgen del Carmen',
        '2026-08-15' => 'Asunción de la Virgen',
        '2026-09-18' => 'Independencia Nacional',
        '2026-09-19' => 'Día de las Glorias del Ejército',
        '2026-10-12' => 'Encuentro de Dos Mundos',
        '2026-10-31' => 'Día de las Iglesias Evangélicas y Protestantes',
        '2026-11-01' => 'Día de Todos los Santos',
        '2026-12-08' => 'Inmaculada Concepción',
        '2026-12-25' => 'Navidad',

        // 2027
        '2027-01-01' => 'Año Nuevo',
        '2027-03-26' => 'Viernes Santo',
        '2027-03-27' => 'Sábado Santo',
        '2027-05-01' => 'Día Nacional del Trabajo',
        '2027-05-21' => 'Día de las Glorias Navales',
        '2027-06-21' => 'Día Nacional de los Pueblos Indígenas',
        '2027-06-28' => 'San Pedro y San Pablo',
        '2027-07-16' => 'Día de la Virgen del Carmen',
        '2027-08-15' => 'Asunción de la Virgen',
        // El 18 cae SÁBADO en 2027 y la ley agrega el viernes 17.
        '2027-09-17' => 'Feriado adicional de Fiestas Patrias',
        '2027-09-18' => 'Independencia Nacional',
        '2027-09-19' => 'Día de las Glorias del Ejército',
        '2027-10-11' => 'Encuentro de Dos Mundos',
        '2027-10-31' => 'Día de las Iglesias Evangélicas y Protestantes',
        '2027-11-01' => 'Día de Todos los Santos',
        '2027-12-08' => 'Inmaculada Concepción',
        '2027-12-25' => 'Navidad',
    ];

    public function run(): void
    {
        foreach (self::FERIADOS as $fecha => $nombre) {
            // BÚSQUEDA CON `whereDate` Y NO CON `updateOrCreate`. La columna tiene cast
            // `date`, así que Eloquent la guarda como «2026-01-01 00:00:00»; un
            // `updateOrCreate(['fecha_desde' => '2026-01-01'])` compara contra el texto pelado,
            // no encuentra nada y CREA otra fila. En MySQL (columna DATE de verdad) coincide y
            // en SQLite no: el seeder duplicaba los 33 feriados en cada corrida y solo se veía
            // en los tests. `whereDate` lo traduce bien en los dos motores.
            $cierre = AgendaCierre::whereDate('fecha_desde', $fecha)
                ->where('origen', AgendaCierre::ORIGEN_FERIADO)
                ->first();

            $datos = [
                'fecha_desde' => $fecha,
                'fecha_hasta' => $fecha,
                'tipo' => AgendaCierre::TIPO_CERRADO,
                'motivo' => 'Feriado legal: '.$nombre,
                'origen' => AgendaCierre::ORIGEN_FERIADO,
            ];

            $cierre ? $cierre->update($datos) : AgendaCierre::create($datos);
        }
    }
}
