<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Repuesto usado en la reparacion de una orden de Servicio Tecnico. Una orden
 * puede tener 0..N repuestos. Montos en pesos chilenos (enteros).
 */
class OrdenServicioRepuesto extends Model
{
    protected $table = 'orden_servicio_repuestos';

    protected $fillable = [
        'orden_servicio_id',
        'nombre',
        'cantidad',
        'precio_unitario',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'integer',
        ];
    }

    /**
     * Subtotal del repuesto: cantidad x precio unitario.
     */
    public function getSubtotalAttribute(): int
    {
        return (int) $this->cantidad * (int) $this->precio_unitario;
    }

    /**
     * @return BelongsTo<OrdenServicio, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }
}
