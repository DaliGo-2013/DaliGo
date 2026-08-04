<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una devolución (M13). `producto_id` nullable a propósito: el
 * cliente describe en texto libre y el enlace al espejo M02 lo pone bodega
 * al evaluar. `estado_producto` (apto|danado|incompleto) decide si la línea
 * puede reingresar (kardex local) o va a merma.
 *
 * Sin AuditableTrait: la traza vive en el agregado Devolucion (mismo criterio
 * que OrdenServicioFoto).
 */
class DevolucionItem extends Model
{
    use HasFactory;

    protected $table = 'devolucion_items';

    public const APTO = 'apto';
    public const DANADO = 'danado';
    public const INCOMPLETO = 'incompleto';

    public const ESTADOS_PRODUCTO = [
        self::APTO => 'Apto para reingreso',
        self::DANADO => 'Dañado',
        self::INCOMPLETO => 'Incompleto',
    ];

    protected $fillable = ['devolucion_id', 'producto_id', 'descripcion', 'cantidad', 'estado_producto'];

    protected function casts(): array
    {
        return ['cantidad' => 'integer'];
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
