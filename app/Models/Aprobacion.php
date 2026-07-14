<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Solicitud de aprobación (M14): histórico completo de quién pidió qué, quién
 * resolvió y con qué resultado — incluidas las AUTO-aprobadas (sin humano).
 * El payload de la acción diferida vive en `datos`; lo aplica el handler del
 * tipo (Aprobaciones::HANDLERS, P-M14-02) dentro de la transacción de
 * aprobación. Volumen bajo → auditable (a diferencia de `notificaciones`).
 */
class Aprobacion extends Model implements AuditableContract
{
    use AuditableTrait;

    protected $table = 'aprobaciones';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_RECHAZADA = 'rechazada';

    public const ESTADO_AUTO_APROBADA = 'auto_aprobada';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_APROBADA,
        self::ESTADO_RECHAZADA,
        self::ESTADO_AUTO_APROBADA,
    ];

    public const ACCION_AJUSTE_REPORTE = 'produccion.ajuste_reporte';

    /**
     * Catálogo de tipos de acción => etiqueta legible (patrón
     * Notificacion::EVENTOS). Los consumidores futuros (M04/M05/M07/M13)
     * agregan aquí su tipo al integrarse, junto con su handler en
     * Aprobaciones::HANDLERS y su regla en ReglasAprobacionSeeder.
     */
    public const TIPOS_ACCION = [
        self::ACCION_AJUSTE_REPORTE => 'Ajuste de reporte de producción',
    ];

    /**
     * Motivos frecuentes de rechazo para la bandeja (chips tocables; el
     * aprobador resuelve desde el celular). Fuente unica para la vista
     * (<x-reason-chips>) y de referencia — el texto libre via "Otro" sigue
     * permitido (sin Rule::in, misma salida de escape que produccion).
     */
    public const MOTIVOS_RECHAZO = [
        'Los datos no cuadran',
        'Falta respaldo del motivo',
        'Corresponde pedirlo de nuevo',
    ];

    protected $fillable = [
        'tipo_accion',
        'regla_id',
        'aprobable_type',
        'aprobable_id',
        'solicitante_id',
        'estado',
        'monto',
        'motivo',
        'descripcion',
        'datos',
        'rol_aprobador',
        'nivel_escalamiento',
        'escalada_at',
        'resuelto_por',
        'resuelta_at',
        'resultado_motivo',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'monto' => 'integer',
            'nivel_escalamiento' => 'integer',
            'escalada_at' => 'datetime',
            'resuelta_at' => 'datetime',
        ];
    }

    public function aprobable(): MorphTo
    {
        return $this->morphTo();
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaAprobacion::class, 'regla_id');
    }

    /**
     * Pendientes cuyo rol VIGENTE es $rol (la bandeja del aprobador filtra por
     * aquí; tras escalar, la solicitud aparece en la bandeja del rol nuevo).
     */
    public function scopeParaRol(Builder $query, string $rol): Builder
    {
        return $query->where('estado', self::ESTADO_PENDIENTE)
            ->where('rol_aprobador', $rol);
    }

    public function esPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS_ACCION[$this->tipo_accion] ?? $this->tipo_accion;
    }
}
