<?php

namespace App\Models;

use Database\Factories\AgendaTrabajoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'fecha_preferida' => 'date',
        ];
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
        $desde = \Illuminate\Support\Carbon::create($anio, $mes, 1);

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
