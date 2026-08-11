<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un corte SIC sobre un reporte de produccion (P-M11-21): la foto de la
 * proyeccion en un slot del scheduler y si disparo aviso. Es la memoria del
 * escalamiento (racha de cortes bajo umbral) y el candado anti-spam (unique
 * [reporte_id, corte_slot]). Sin auditoria: lo escribe solo el cron y es
 * autoevidente (mismo criterio que ProduccionRegistro).
 */
class ProduccionCorte extends Model
{
    protected $table = 'produccion_cortes';

    protected $fillable = [
        'reporte_id',
        'corte_slot',
        'bajo_umbral',
        'proyeccion',
        'avisado',
        'urgente',
    ];

    protected function casts(): array
    {
        return [
            // GOTCHA vehiculo_avisos: el cast datetime ESCRIBE 'Y-m-d H:i:s';
            // buscar el slot con un string corto no encontraria la fila que el
            // propio comando escribio => pasar SIEMPRE un Carbon en la clave
            // del firstOrCreate (lectura y escritura con el mismo formato).
            'corte_slot' => 'datetime',
            'bajo_umbral' => 'boolean',
            'avisado' => 'boolean',
            'urgente' => 'boolean',
            'proyeccion' => 'integer',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ProduccionReporte::class, 'reporte_id');
    }

    /**
     * Cortes consecutivos BAJO umbral inmediatamente anteriores a $antesDe.
     * Un corte sobre umbral corta la racha: recuperarse y volver a caer es una
     * racha nueva (y por eso un aviso nuevo). Racha 0 => proximo aviso normal;
     * 1 => urgente; >=2 => silencio (ya se aviso dos veces sin cambio).
     */
    public static function rachaDe(int $reporteId, Carbon $antesDe): int
    {
        $racha = 0;

        $cortes = static::where('reporte_id', $reporteId)
            ->where('corte_slot', '<', $antesDe)
            ->orderByDesc('corte_slot')
            ->limit(24)
            ->get();

        foreach ($cortes as $corte) {
            if (! $corte->bajo_umbral) {
                break;
            }

            $racha++;
        }

        return $racha;
    }
}
