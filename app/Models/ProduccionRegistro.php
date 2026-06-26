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

    /**
     * Motivos de defecto (lista cerrada) que explican por que una tanda salio
     * de segunda o mala. Fuente unica para la validacion y el select del
     * operario; agregar/editar motivos = tocar solo este arreglo.
     */
    public const MOTIVOS_DEFECTO = [
        'Burbujas / aire',
        'Rebaba',
        'Cuello o rosca deforme',
        'Mal sellado',
        'Punto frío',
        'Contaminación / suciedad',
        'Material quemado',
        'Espesor irregular',
        'Rayas o marcas',
    ];

    protected $fillable = [
        'reporte_id',
        'maquina_id',
        'tipo_botellon_id',
        'primera',
        'segunda',
        'motivo_segunda',
        'malo',
        'motivo_malo',
        'danada',
    ];

    protected function casts(): array
    {
        return [
            'primera' => 'integer',
            'segunda' => 'integer',
            'malo' => 'integer',
            'danada' => 'integer',
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
