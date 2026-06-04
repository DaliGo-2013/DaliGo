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

    public function reporte(): HasOne
    {
        return $this->hasOne(ProduccionReporte::class, 'asignacion_id');
    }
}
