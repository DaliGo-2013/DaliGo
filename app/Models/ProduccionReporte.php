<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduccionReporte extends Model
{
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
