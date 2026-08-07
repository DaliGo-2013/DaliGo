<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Orden de traslado del wizard de baja de bodegas (M04-F2, P-M04-20): la
 * bodega que se quiere dar de baja tiene existencias y alguien decidió a
 * dónde van. Los items son una FOTO al momento de la orden. El traslado
 * físico hoy se ejecuta en Bsale (D-005 pendiente); la orden es el puente,
 * y el sync la COMPLETA solo cuando confirma stock 0 en el origen.
 *
 * Auditable: es un documento operativo de bajo volumen cuyo ciclo de vida
 * (pendiente → completado/anulado) importa reconstruir.
 */
class BodegaTraslado extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\BodegaTrasladoFactory> */
    use HasFactory, AuditableTrait;

    protected $table = 'bodega_traslados';

    public const PENDIENTE = 'pendiente';
    public const COMPLETADO = 'completado';
    public const ANULADO = 'anulado';

    public const ESTADOS = [self::PENDIENTE, self::COMPLETADO, self::ANULADO];

    protected $fillable = [
        'bodega_id', 'bodega_destino_id', 'estado',
        'solicitante_id', 'solicitante_nombre',
        'completado_at', 'anulado_at', 'aviso_stock_nuevo_at',
    ];

    protected function casts(): array
    {
        return [
            'completado_at' => 'datetime',
            'anulado_at' => 'datetime',
            'aviso_stock_nuevo_at' => 'datetime',
        ];
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::PENDIENTE);
    }

    public function esPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /** @return HasMany<BodegaTrasladoItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BodegaTrasladoItem::class);
    }
}
