<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento del kardex LOCAL de devoluciones (M13, PLAN-M13 §1.2).
 *
 * NUNCA toca `stocks`/`bodegas` (espejo read-only de Bsale) — mismo contrato
 * que ProduccionMovimiento (HANDOFF §8d): es la verdad local, lista para
 * empujar a Bsale (receptions) cuando exista M04/D-005. `bodega_destino` es
 * texto libre a propósito: la estructura de bodegas es D-003 y está EN CURSO.
 *
 * Sin AuditableTrait: se escribe solo al resolver (una vez, bajo lock) y su
 * fila es su propia traza (mismo criterio que ProduccionMovimiento).
 */
class DevolucionMovimiento extends Model
{
    use HasFactory;

    protected $table = 'devolucion_movimientos';

    public const REINGRESO = 'reingreso';
    public const MERMA = 'merma';

    protected $fillable = [
        'devolucion_id', 'devolucion_item_id', 'producto_id',
        'cantidad', 'tipo', 'bodega_destino', 'observacion',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:4'];
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(DevolucionItem::class, 'devolucion_item_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
