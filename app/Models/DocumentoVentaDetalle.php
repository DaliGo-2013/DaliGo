<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de detalle del espejo de documentos de venta (solo la escribe
 * DocumentSync). `descuento` guarda el monto total de descuento de la línea
 * (totalDiscount de Bsale); `precio_neto` usa netUnitValueRaw (float preciso)
 * con fallback a netUnitValue. `descripcion` es un fallback de lectura: si hay
 * producto espejado, la verdad es productos.nombre.
 */
class DocumentoVentaDetalle extends Model
{
    protected $table = 'documento_venta_detalles';

    protected $fillable = [
        'documento_venta_id',
        'bsale_detail_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'precio_neto',
        'descuento',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_neto' => 'decimal:4',
            'descuento' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<DocumentoVenta, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_venta_id');
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
