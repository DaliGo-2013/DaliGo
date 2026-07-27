<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Conductor (chofer) que retira máquinas en ruta para el ingreso por lote.
 * Administrable desde la app; alimenta el selector "Conductor" del lote.
 */
class Conductor extends Model
{
    protected $table = 'conductores';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Solo los conductores activos (los que aparecen en el selector del lote).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Conductor>  $query
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
