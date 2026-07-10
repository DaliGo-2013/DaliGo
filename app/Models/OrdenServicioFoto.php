<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto de respaldo del estado fisico del equipo al ingresarlo (rayones/golpes).
 * Una orden tiene 0..N fotos. `ruta` es la ruta relativa en el disco PRIVADO
 * `local` (se sirve solo con sesion, nunca por URL publica).
 */
class OrdenServicioFoto extends Model
{
    protected $table = 'orden_servicio_fotos';

    protected $fillable = [
        'orden_servicio_id',
        'ruta',
    ];

    /**
     * @return BelongsTo<OrdenServicio, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }
}
