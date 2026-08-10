<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Receta paramétrica del botellón (P-M11-10): cuántos componentes consume
 * UNA unidad del producto terminado. La lee el backflush del kardex al
 * aprobar (ProduccionMovimiento::planParaReporte). Editable por UI con
 * `manage production`; la hipótesis del seeder (1 preforma + 1 tapa) nace
 * `confirmada=false` y guardar desde la UI confirma (patrón D-003).
 *
 * `confirmada` NO es gate: la receta aplica al backflush confirmada o no
 * (igual que la clasificación de bodegas opera sin confirmar). Gobierna el
 * badge «por confirmar» y nada más — el seeder jamás pisa una fila existente.
 */
class Receta extends Model implements AuditableContract
{
    use AuditableTrait;

    public const ROL_PREFORMA = 'preforma';
    public const ROL_TAPA = 'tapa';

    public const ROLES = [self::ROL_PREFORMA, self::ROL_TAPA];

    public const ETIQUETAS_ROL = [
        self::ROL_PREFORMA => 'Preforma',
        self::ROL_TAPA => 'Tapa',
    ];

    protected $fillable = [
        'producto_id',
        'rol',
        'componente_id',
        'cantidad',
        'confirmada',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'confirmada' => 'boolean',
        ];
    }

    /** El botellón (producto terminado) dueño de la receta. */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** El producto componente (preforma/tapa); null = sin enlazar aún. */
    public function componente(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'componente_id');
    }

    /** Filas de un producto, mapeadas rol => fila (para el form de edición). */
    public static function paraProducto(int $productoId): Collection
    {
        return static::where('producto_id', $productoId)->get()->keyBy('rol');
    }
}
