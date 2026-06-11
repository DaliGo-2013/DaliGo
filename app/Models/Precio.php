<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio de un producto en una lista, espejado desde Bsale (M02.2).
 *
 * Solo lectura en DaliGo (los valores se editan en Bsale). Sin auditoria:
 * es espejo masivo (miles de filas por sync) y auditarlo seria puro ruido.
 */
class Precio extends Model
{
    /** @use HasFactory<\Database\Factories\PrecioFactory> */
    use HasFactory;

    protected $table = 'precios';

    protected $fillable = [
        'lista_precio_id',
        'producto_id',
        'precio_neto',
        'precio_con_iva',
        'bsale_detail_id',
    ];

    protected function casts(): array
    {
        return [
            'precio_neto' => 'decimal:4',
            'precio_con_iva' => 'decimal:4',
        ];
    }

    /**
     * Formato chileno: miles con punto, sin decimales si es entero (CLP).
     * Los valores raros de Bsale (netos tipo 0,8403) conservan 2 decimales.
     */
    public static function formatear(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $f = (float) $valor;

        return number_format($f, fmod($f, 1) == 0.0 ? 0 : 2, ',', '.');
    }

    /** @return BelongsTo<ListaPrecio, $this> */
    public function lista(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
