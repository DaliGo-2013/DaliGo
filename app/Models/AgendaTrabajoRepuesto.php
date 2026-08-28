<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Repuesto usado en un trabajo de la agenda de terreno (servicio industrial).
 *
 * SIN PRECIOS, y no por falta de tiempo: al técnico industrial le pagan por
 * arreglar e instalar, no por cobrarle al cliente (dueño 14-08-2026). La
 * cotización formal la hacen el vendedor y el jefe de ventas.
 *
 * Y SIN MOVIMIENTO DE STOCK: el descuento de inventario sale de la factura o
 * boleta del vendedor —Bsale ya descuenta al facturar— así que esta lista es un
 * AVISO. Descontar también desde acá sería consumir el repuesto dos veces.
 *
 * El `sku` está para que el vendedor arme esa factura sin volver a preguntarle
 * nada al técnico, y para que el informe pueda nombrar el repuesto sin
 * ambigüedad. Null = escrito a mano (no vino del catálogo).
 */
class AgendaTrabajoRepuesto extends Model
{
    protected $table = 'agenda_trabajo_repuestos';

    protected $fillable = [
        'agenda_trabajo_id',
        'nombre',
        'sku',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AgendaTrabajo, $this>
     */
    public function trabajo(): BelongsTo
    {
        return $this->belongsTo(AgendaTrabajo::class, 'agenda_trabajo_id');
    }
}
