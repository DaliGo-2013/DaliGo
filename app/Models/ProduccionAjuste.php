<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un cambio del jefe sobre UN campo de un reporte de producción (dueño
 * 09-09): quién lo pidió, qué ítem, cuánto había y cuánto quedó. Lo escribe
 * AjusteReporteProduccion::aplicar dentro de la misma transacción que pisa
 * los totales; una fila por campo que cambió de verdad (antes ≠ después).
 *
 * Las filas PERSISTEN como historia aunque el soplador vuelva a reportar
 * (recalcularDesdeRegistros, solo en devuelto) y los totales se muevan otra
 * vez: cada una lleva su fecha y el detalle las muestra en orden.
 */
class ProduccionAjuste extends Model
{
    protected $table = 'produccion_ajustes';

    /**
     * Campos ajustables y su etiqueta humana. FUENTE ÚNICA: la usan el
     * handler (qué campos mirar), las pantallas del soplador y del jefe, la
     * bandeja de aprobaciones y el {cambio} de las notificaciones.
     */
    public const ETIQUETAS = [
        'asignadas' => 'Asignadas',
        'primera' => '1ª',
        'segunda' => '2ª',
        'malo' => 'Malos',
        'danada' => 'Dañadas',
    ];

    protected $fillable = [
        'reporte_id',
        'aprobacion_id',
        'responsable_id',
        'autorizado_por',
        'campo',
        'antes',
        'despues',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'antes' => 'integer',
            'despues' => 'integer',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ProduccionReporte::class, 'reporte_id');
    }

    /** Quien pidió el cambio (el jefe que editó). */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** Quien lo autorizó, solo si fue otro (ajuste sobre el umbral). */
    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    public function getEtiquetaAttribute(): string
    {
        return self::ETIQUETAS[$this->campo] ?? $this->campo;
    }

    /**
     * Registra los cambios reales de un ajuste: compara anterior vs nuevo
     * campo por campo (solo los de ETIQUETAS) y crea una fila por diferencia.
     * Un ajuste que no cambia ninguna cantidad no deja filas — y por eso no
     * marca «Modificado».
     *
     * @return int cuántas filas se crearon
     */
    public static function registrar(ProduccionReporte $reporte, array $anterior, array $nuevo, Aprobacion $aprobacion): int
    {
        $creadas = 0;
        $motivo = is_scalar($nuevo['motivo_ajuste'] ?? null) ? (string) $nuevo['motivo_ajuste'] : null;
        $autorizadoPor = $aprobacion->resuelto_por && $aprobacion->resuelto_por !== $aprobacion->solicitante_id
            ? $aprobacion->resuelto_por
            : null;

        foreach (array_keys(self::ETIQUETAS) as $campo) {
            if (! array_key_exists($campo, $nuevo)) {
                continue;
            }
            $antes = (int) ($anterior[$campo] ?? 0);
            $despues = (int) $nuevo[$campo];
            if ($antes === $despues) {
                continue;
            }
            static::create([
                'reporte_id' => $reporte->id,
                'aprobacion_id' => $aprobacion->id,
                'responsable_id' => $aprobacion->solicitante_id,
                'autorizado_por' => $autorizadoPor,
                'campo' => $campo,
                'antes' => $antes,
                'despues' => $despues,
                'motivo' => $motivo,
            ]);
            $creadas++;
        }

        return $creadas;
    }
}
