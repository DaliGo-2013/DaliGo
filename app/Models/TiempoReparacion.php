<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Tiempo estándar de reparación por trabajo ("Costos generales de reparación").
 * Jefatura fija las HORAS que lleva cada trabajo del taller; la mano de obra de
 * una orden se calcula sola (horas × valor hora) y el técnico no la edita.
 */
class TiempoReparacion extends Model implements AuditableContract
{
    use AuditableTrait, HasFactory;

    protected $table = 'tiempos_reparacion';

    protected $fillable = ['trabajo', 'horas', 'grupo', 'activo'];

    protected $casts = [
        'horas' => 'decimal:1',
        'activo' => 'boolean',
    ];

    /**
     * Horas estándar de un trabajo (null si no está en el catálogo o está
     * inactivo). Es la fuente para calcular la mano de obra bloqueada.
     */
    public static function horasDe(?string $trabajo): ?float
    {
        if (blank($trabajo)) {
            return null;
        }

        $fila = static::query()->where('activo', true)->where('trabajo', $trabajo)->first();

        return $fila ? (float) $fila->horas : null;
    }

    /** Horas sin ceros sobrantes: 1.0 → "1", 1.5 → "1,5". */
    public function getHorasFmtAttribute(): string
    {
        return rtrim(rtrim(number_format((float) $this->horas, 1, ',', ''), '0'), ',');
    }
}
