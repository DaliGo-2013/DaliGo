<?php

namespace App\Models;

use Database\Factories\AgendaTrabajoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Trabajo agendado del técnico industrial (servicio en terreno): lo agenda el
 * jefe o un vendedor con el cliente, la fecha, el servicio del catálogo (o un
 * detalle libre) y el técnico asignado; el técnico lo marca realizado.
 */
class AgendaTrabajo extends Model implements AuditableContract
{
    /** @use HasFactory<AgendaTrabajoFactory> */
    use AuditableTrait, HasFactory;

    protected $table = 'agenda_trabajos';

    // 'visita_tecnica' PRIMERO a propósito (pedido del dueño): el técnico va
    // donde el cliente, diagnostica y cotiza; después vienen los trabajos.
    public const TIPOS = ['visita_tecnica', 'mantencion', 'reparacion', 'instalacion'];

    public const TIPO_ETIQUETAS = [
        'visita_tecnica' => 'Visita técnica',
        'mantencion' => 'Mantención',
        'reparacion' => 'Reparación',
        'instalacion' => 'Instalación',
    ];

    // 'solicitado' = lo pidió el CLIENTE por el QR y espera coordinación
    // (sin fecha); al coordinar pasa a 'agendado' con fecha y técnico.
    public const ESTADOS = ['solicitado', 'agendado', 'realizado', 'cancelado'];

    // Variante de x-badge por estado. OJO: x-badge solo define brand|neutral|
    // danger (paleta del design system); espeja al taller: cerrado-bien =
    // neutral (como 'entregado'), cerrado-mal = danger (como 'sin_solucion').
    public const ESTADO_VARIANTES = [
        'solicitado' => 'brand',
        'agendado' => 'brand',
        'realizado' => 'neutral',
        'cancelado' => 'danger',
    ];

    protected $fillable = [
        'tipo',
        'fecha',
        'fecha_fin',
        'hora',
        'hora_fin',
        'fecha_preferida',
        'estado',
        'servicio_terreno_id',
        'cliente_id',
        'cliente_nombre',
        'cliente_rut',
        'cliente_telefono',
        'cliente_email',
        'direccion',
        'ciudad',
        'tecnico_id',
        'descripcion',
        'notas_tecnico',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_fin' => 'date',
            'fecha_preferida' => 'date',
        ];
    }

    /**
     * Hora en formato corto "HH:MM" para la vista calendario (la columna `time`
     * viene como "HH:MM:SS"). Null si el trabajo aún no tiene hora asignada.
     */
    public function getHoraCortaAttribute(): ?string
    {
        return $this->hora ? substr((string) $this->hora, 0, 5) : null;
    }

    /**
     * Franja horaria de 2 horas a la que cae el trabajo en la agenda del técnico
     * (08:00, 10:00, 12:00, 14:00, 16:00, 18:00 …): la hora redondeada hacia abajo
     * al bloque par. Deja holgura para viajar entre trabajos. Null si no tiene hora.
     */
    public function getFranjaAttribute(): ?string
    {
        if (! $this->hora_corta) {
            return null;
        }

        $h = (int) substr($this->hora_corta, 0, 2);

        return sprintf('%02d:00', $h - ($h % 2));
    }

    /** Hora de término "HH:MM" (columna `time` viene "HH:MM:SS"). Null si no hay. */
    public function getHoraFinCortaAttribute(): ?string
    {
        return $this->hora_fin ? substr((string) $this->hora_fin, 0, 5) : null;
    }

    /** ¿El trabajo abarca más de un día (viaje)? */
    public function getAbarcaVariosDiasAttribute(): bool
    {
        return $this->fecha && $this->fecha_fin && ! $this->fecha_fin->isSameDay($this->fecha);
    }

    /** Etiqueta del rango de fechas: "7 al 10 de septiembre" o el día suelto. */
    public function getRangoFechasLabelAttribute(): ?string
    {
        if (! $this->fecha) {
            return null;
        }
        if (! $this->abarca_varios_dias) {
            return $this->fecha->translatedFormat('d \d\e F');
        }

        $mismoMes = $this->fecha->month === $this->fecha_fin->month;

        return $mismoMes
            ? $this->fecha->translatedFormat('d').' al '.$this->fecha_fin->translatedFormat('d \d\e F')
            : $this->fecha->translatedFormat('d \d\e F').' al '.$this->fecha_fin->translatedFormat('d \d\e F');
    }

    /** Etiqueta del rango de horas: "08:00 a 18:00", "08:00", o null si sin hora. */
    public function getRangoHorasLabelAttribute(): ?string
    {
        if (! $this->hora_corta) {
            return null;
        }

        return $this->hora_fin_corta && $this->hora_fin_corta !== $this->hora_corta
            ? $this->hora_corta.' a '.$this->hora_fin_corta
            : $this->hora_corta;
    }

    /**
     * Trabajos YA comprometidos (agendado/realizado, con fecha) que se solapan con
     * el rango [$desde, $hasta] — para bloquear que se agende encima cuando el
     * técnico está ocupado/de viaje. El solape considera fecha_fin (o la fecha si
     * es de un día). Portable MySQL 5.7 / SQLite (sin funciones de fecha crudas).
     *
     * @return \Illuminate\Support\Collection<int, AgendaTrabajo>
     */
    public static function conflictos(string $desde, string $hasta, ?int $exceptId = null): \Illuminate\Support\Collection
    {
        return static::query()
            ->whereIn('estado', ['agendado', 'realizado'])
            ->whereNotNull('fecha')
            ->when($exceptId, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->whereDate('fecha', '<=', $hasta)
            ->where(function (Builder $q) use ($desde) {
                $q->where(fn (Builder $w) => $w->whereNotNull('fecha_fin')->whereDate('fecha_fin', '>=', $desde))
                    ->orWhere(fn (Builder $w) => $w->whereNull('fecha_fin')->whereDate('fecha', '>=', $desde));
            })
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Solicitudes del cliente (QR) que esperan coordinación: sin fecha real
     * todavía. Aparecen en el bloque "Por coordinar" de la agenda.
     *
     * @param  Builder<AgendaTrabajo>  $query
     */
    public function scopePorCoordinar($query)
    {
        return $query->where('estado', 'solicitado')->orderBy('id');
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_ETIQUETAS[$this->tipo] ?? ucfirst((string) $this->tipo);
    }

    public function getEstadoVarianteAttribute(): string
    {
        return self::ESTADO_VARIANTES[$this->estado] ?? 'brand';
    }

    /**
     * Trabajos de un mes calendario, en orden de agenda (fecha ascendente).
     * whereDate en ambos bordes: portable (MySQL 5.7 / SQLite) y usa el índice.
     *
     * @param  Builder<AgendaTrabajo>  $query
     */
    public function scopeDelMes($query, int $anio, int $mes)
    {
        $desde = Carbon::create($anio, $mes, 1);

        return $query
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $desde->copy()->endOfMonth()->toDateString())
            ->orderBy('fecha')->orderBy('id');
    }

    /** @return BelongsTo<ServicioTerreno, $this> */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioTerreno::class, 'servicio_terreno_id');
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<User, $this> */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    /**
     * Repuestos usados en el trabajo (los registra el técnico al cerrar).
     *
     * @return HasMany<AgendaTrabajoRepuesto, $this>
     */
    public function repuestos(): HasMany
    {
        return $this->hasMany(AgendaTrabajoRepuesto::class);
    }
}
