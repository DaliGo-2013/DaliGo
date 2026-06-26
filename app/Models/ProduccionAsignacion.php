<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProduccionAsignacion extends Model
{
    protected $table = 'produccion_asignaciones';

    protected $fillable = [
        'soplador_id',
        'fecha',
        'turno',
        'asignadas',
        'preforma_id',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'asignadas' => 'integer',
        ];
    }

    public function soplador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soplador_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Preforma del turno (producto del catalogo). Nullable: asignaciones
     * historicas o sin preforma elegida.
     */
    public function preforma(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'preforma_id');
    }

    public function reporte(): HasOne
    {
        return $this->hasOne(ProduccionReporte::class, 'asignacion_id');
    }
}
