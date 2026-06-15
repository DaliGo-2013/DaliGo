<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanda de produccion dentro de un reporte: maquina + tipo de botellon +
 * cantidades. Append-only: cada "Agregar" del soplador crea una fila nueva
 * (los timestamps por tanda alimentan futuras metricas de ritmo por maquina).
 * Sin auditoria: alto volumen y autoevidente; la traza de totales vive en
 * el audit del reporte.
 */
class ProduccionRegistro extends Model
{
    protected $table = 'produccion_registros';

    protected $fillable = [
        'reporte_id',
        'maquina_id',
        'tipo_botellon_id',
        'primera',
        'segunda',
        'malo',
    ];

    protected function casts(): array
    {
        return [
            'primera' => 'integer',
            'segunda' => 'integer',
            'malo' => 'integer',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ProduccionReporte::class, 'reporte_id');
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class, 'maquina_id');
    }

    public function tipoBotellon(): BelongsTo
    {
        return $this->belongsTo(TipoBotellon::class, 'tipo_botellon_id');
    }
}
