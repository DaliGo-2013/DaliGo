<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Un tramo en que la agenda del técnico NO está disponible, o lo está a medias.
 *
 * Feriados, vacaciones y días a media jornada son la misma cosa acá: un rango de fechas con
 * un tipo. Ver la migración para por qué es una sola tabla.
 *
 * LO QUE SALE AL PÚBLICO Y LO QUE NO: hacia afuera solo se dice **que** ese día no se puede
 * (o hasta qué hora), nunca **por qué** — decisión del dueño (13-08-2026): «no es tan
 * importante que la gente sepa que está de vacaciones, simplemente no está disponible». El
 * `motivo` es para el jefe de ventas, que sí necesita saber qué cerró cada día.
 */
class AgendaCierre extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'agenda_cierres';

    public const TIPO_CERRADO = 'cerrado';

    public const TIPO_MEDIA_JORNADA = 'media_jornada';

    public const TIPOS = [
        self::TIPO_CERRADO => 'Cerrado (no se atiende)',
        self::TIPO_MEDIA_JORNADA => 'Media jornada (hasta cierta hora)',
    ];

    /** Los que siembra el sistema (feriados) vs. los que carga el jefe de ventas. */
    public const ORIGEN_FERIADO = 'feriado';

    public const ORIGEN_MANUAL = 'manual';

    protected $fillable = [
        'fecha_desde', 'fecha_hasta', 'tipo', 'hora_hasta', 'motivo', 'origen', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Los cierres que TOCAN el rango [$desde, $hasta].
     *
     * Mismo patrón de solape que `AgendaTrabajo::conflictos()` y por la misma razón: portable
     * entre MySQL 5.7 y SQLite, sin funciones de fecha crudas que se comporten distinto en
     * cada motor.
     *
     * @param  Builder<AgendaCierre>  $query
     */
    public function scopeEnRango($query, string $desde, string $hasta)
    {
        return $query->whereDate('fecha_desde', '<=', $hasta)
            ->whereDate('fecha_hasta', '>=', $desde);
    }

    /** Hora de corte "HH:MM" (la columna `time` viene "HH:MM:SS"). */
    public function getHoraCortaAttribute(): ?string
    {
        return $this->hora_hasta ? substr((string) $this->hora_hasta, 0, 5) : null;
    }

    /** ¿Abarca más de un día? (unas vacaciones, contra un feriado suelto) */
    public function getEsRangoAttribute(): bool
    {
        return ! $this->fecha_hasta->isSameDay($this->fecha_desde);
    }

    /** Etiqueta para el staff: «del 7 al 18 de septiembre» o «25 de diciembre». */
    public function getRangoLabelAttribute(): string
    {
        $desde = $this->fecha_desde->locale('es');
        $hasta = $this->fecha_hasta->locale('es');

        if (! $this->es_rango) {
            return $desde->translatedFormat('j \d\e F \d\e Y');
        }

        return $desde->month === $hasta->month
            ? 'del '.$desde->translatedFormat('j').' al '.$hasta->translatedFormat('j \d\e F \d\e Y')
            : 'del '.$desde->translatedFormat('j \d\e F').' al '.$hasta->translatedFormat('j \d\e F \d\e Y');
    }

    /**
     * Los días sueltos que cubre este tramo, como 'Y-m-d' => el cierre.
     *
     * @return array<string, static>
     */
    public function dias(): array
    {
        $dias = [];

        for ($d = $this->fecha_desde->copy(); $d->lessThanOrEqualTo($this->fecha_hasta); $d->addDay()) {
            $dias[$d->toDateString()] = $this;
        }

        return $dias;
    }

    /**
     * El mapa 'Y-m-d' => cierre para una ventana, en UNA consulta.
     *
     * Si dos cierres se pisan en un día manda el que CIERRA: media jornada sobre un día
     * cerrado no lo reabre. Es la lectura conservadora, la misma del resto del módulo — antes
     * prometer de menos que prometer un día que no existe.
     *
     * @return array<string, static>
     */
    public static function mapaDeDias(string $desde, string $hasta): array
    {
        $mapa = [];

        foreach (static::enRango($desde, $hasta)->get() as $cierre) {
            foreach ($cierre->dias() as $dia => $c) {
                if (isset($mapa[$dia]) && $mapa[$dia]->tipo === self::TIPO_CERRADO) {
                    continue;
                }
                $mapa[$dia] = $c;
            }
        }

        return $mapa;
    }

    /** Normaliza el orden de las fechas: un rango al revés es un error de tipeo, no un rango. */
    public static function ordenar(string $desde, string $hasta): array
    {
        return Carbon::parse($desde)->greaterThan(Carbon::parse($hasta))
            ? [$hasta, $desde]
            : [$desde, $hasta];
    }
}
