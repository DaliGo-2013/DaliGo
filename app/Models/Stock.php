<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock de un producto en una bodega, espejado desde Bsale (M04).
 *
 * Solo lectura en DaliGo (el stock se mueve en Bsale). Sin auditoría: es espejo
 * masivo (decenas de miles de filas por sync).
 */
class Stock extends Model
{
    /** @use HasFactory<\Database\Factories\StockFactory> */
    use HasFactory;

    protected $table = 'stocks';

    protected $fillable = [
        'bodega_id',
        'producto_id',
        'stock_real',
        'stock_reservado',
        'stock_disponible',
        'bsale_stock_id',
    ];

    protected function casts(): array
    {
        return [
            'stock_real' => 'decimal:4',
            'stock_reservado' => 'decimal:4',
            'stock_disponible' => 'decimal:4',
        ];
    }

    /**
     * Cantidad legible: sin decimales si es entero (lo normal en stock), con
     * hasta 4 si es fraccionado. Miles con punto (formato chileno).
     */
    public static function formatear(?string $valor): string
    {
        $n = (float) ($valor ?? 0);

        return number_format($n, fmod($n, 1) == 0.0 ? 0 : 4, ',', '.');
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
