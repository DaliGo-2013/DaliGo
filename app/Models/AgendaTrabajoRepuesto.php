<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Repuesto usado en un trabajo de la agenda de terreno (servicio industrial).
 * Sin precios: al informe industrial le interesa el USO en números (unidades).
 */
class AgendaTrabajoRepuesto extends Model
{
    protected $table = 'agenda_trabajo_repuestos';

    protected $fillable = [
        'agenda_trabajo_id',
        'nombre',
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
