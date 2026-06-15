<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ProduccionReporte extends Model implements AuditableContract
{
    // Auditable: tres escritores tocan los mismos totales (soplador via
    // recalculo, jefe via ajuste, y el recalculo pisando ajustes); la traza
    // queda en el visor de auditoria.
    use AuditableTrait;

    protected $table = 'produccion_reportes';

    // Estados del flujo.
    public const BORRADOR = 'borrador';
    public const ENVIADO = 'enviado';
    public const APROBADO = 'aprobado';
    public const DEVUELTO = 'devuelto';

    protected $fillable = [
        'asignacion_id',
        'soplador_id',
        'fecha',
        'turno',
        'asignadas',
        'primera',
        'segunda',
        'malo',
        'motivo',
        'obs',
        'estado',
        'enviado_at',
        'revisado_por',
        'revisado_at',
        'motivo_ajuste',
        'devuelto_motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'asignadas' => 'integer',
            'primera' => 'integer',
            'segunda' => 'integer',
            'malo' => 'integer',
            'enviado_at' => 'datetime',
            'revisado_at' => 'datetime',
        ];
    }

    // --- Relaciones ---

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(ProduccionAsignacion::class, 'asignacion_id');
    }

    public function soplador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soplador_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(ProduccionRegistro::class, 'reporte_id');
    }

    // --- Derivados ---

    public function getTotalAttribute(): int
    {
        return (int) $this->primera + (int) $this->segunda + (int) $this->malo;
    }

    public function getDiferenciaAttribute(): int
    {
        return (int) $this->asignadas - $this->total;
    }

    public function getTasaPrimeraAttribute(): int
    {
        return $this->total > 0 ? (int) round($this->primera / $this->total * 100) : 0;
    }

    /**
     * Sincroniza los totales denormalizados desde los registros (tandas).
     * Llamar dentro de la misma transaccion que crea/borra el registro.
     * Limpia motivo_ajuste: un ajuste del jefe pierde sentido si el detalle
     * cambio despues (solo puede pasar en estado devuelto = re-reportar).
     */
    public function recalcularDesdeRegistros(): void
    {
        $sumas = $this->registros()
            ->selectRaw('COALESCE(SUM(primera), 0) AS t_primera, COALESCE(SUM(segunda), 0) AS t_segunda, COALESCE(SUM(malo), 0) AS t_malo')
            ->first();

        $this->primera = (int) $sumas->t_primera;
        $this->segunda = (int) $sumas->t_segunda;
        $this->malo = (int) $sumas->t_malo;
        $this->motivo_ajuste = null;
        $this->save();
    }

    // --- Helpers de estado ---

    public function editablePorSoplador(): bool
    {
        return in_array($this->estado, [self::BORRADOR, self::DEVUELTO], true);
    }

    public function esPendienteDeRevision(): bool
    {
        return $this->estado === self::ENVIADO;
    }

    // --- Scopes ---

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::ENVIADO);
    }

    public function scopeDelDia(Builder $query, $fecha = null): Builder
    {
        return $query->whereDate('fecha', $fecha ?? now()->toDateString());
    }
}
