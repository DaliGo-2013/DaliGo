<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ítem de la orden de traslado (M04-F2): la FOTO de cuánto había de un
 * producto al momento de pedir la baja — nombre y sku denormalizados a
 * propósito para que el documento no cambie si el catálogo renombra.
 * Sin auditoría: la orden (BodegaTraslado) es la unidad auditable; sus
 * items nacen con ella y no se editan.
 */
class BodegaTrasladoItem extends Model
{
    /** @use HasFactory<\Database\Factories\BodegaTrasladoItemFactory> */
    use HasFactory;

    protected $table = 'bodega_traslado_items';

    protected $fillable = [
        'bodega_traslado_id', 'producto_id', 'nombre', 'sku', 'cantidad',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:4'];
    }

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(BodegaTraslado::class, 'bodega_traslado_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
