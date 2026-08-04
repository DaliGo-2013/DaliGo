<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso de vencimiento ya enviado (vehiculo × documento × hito × fecha).
 *
 * SIN AuditableTrait a proposito, igual que Notificacion: es una bitacora de
 * "esto ya se avisó", su propia fila es la traza y auditarla no agrega nada.
 */
class VehiculoAviso extends Model
{
    protected $table = 'vehiculo_avisos';

    /** No lleva timestamps: `avisado_at` ES su fecha. */
    public $timestamps = false;

    public const HITO_POR_VENCER = 'por_vencer';
    public const HITO_VENCIDO = 'vencido';

    protected $fillable = [
        'vehiculo_id',
        'documento',
        'hito',
        'vence',
        'avisado_at',
    ];

    protected function casts(): array
    {
        return [
            'vence' => 'date',
            'avisado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehiculo, VehiculoAviso> */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }
}
