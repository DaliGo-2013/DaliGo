<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parada de produccion dentro de un reporte: que detuvo la produccion, de que
 * recurso (maquina u operario) y entre que horas del turno. Es el dato crudo
 * que desbloquea OEE, MTBF y el Pareto de motivos (P-M11-11, F2).
 * Append-only como las tandas y sin auditoria (mismo criterio de
 * ProduccionRegistro: alto volumen y autoevidente).
 *
 * Las horas son "reloj de pared" del turno (columnas TIME, sin timezone): el
 * reporte ya fija la fecha; convertirlas a UTC no aporta y complica el render
 * (precedente: hora/hora_fin de AgendaTrabajo).
 */
class ProduccionParada extends Model
{
    protected $table = 'produccion_paradas';

    // Clases de parada (el split que OEE necesita): una detencion agendada
    // (cambio de molde, mantencion) no es una falla.
    public const CLASE_PLANIFICADA = 'planificada';

    public const CLASE_NO_PLANIFICADA = 'no_planificada';

    // Origen de la detencion: se detuvo la maquina, o fue el operario quien
    // no pudo operar (patron "No Machine"). Etiquetas facticas, no culposas.
    public const ORIGENES = ['maquina', 'operario'];

    /**
     * Motivos de parada (lista CERRADA, Rule::in): el Pareto y el OEE de F2
     * necesitan una tipificacion estable, por eso NO se deriva de
     * MOTIVOS_DIFERENCIA (lista abierta con "Otro" y otro proposito).
     * Los 5 del dictado v19 + "Corte de luz" (detencion real de la casa; sin
     * el, la lista cerrada obliga a mentir con "Falla de maquina") +
     * "Scrap de arranque" (los botellones malos post cambio de molde son una
     * perdida distinta). "Preformas defectuosas" queda FUERA a proposito:
     * es perdida de calidad, no detencion (ya se captura por tanda).
     */
    public const MOTIVOS = [
        'Faltaron preformas',
        'Falla de máquina',
        'Mantención de máquina',
        'Cambio de molde',
        'Molde dañado',
        'Corte de luz',
        'Scrap de arranque',
    ];

    // Subconjunto de MOTIVOS que constituye parada PLANIFICADA (dictado v19:
    // planificada = cambio de molde / mantencion).
    public const MOTIVOS_PLANIFICADOS = [
        'Mantención de máquina',
        'Cambio de molde',
    ];

    protected $fillable = [
        'reporte_id',
        'cliente_uuid',
        'maquina_id',
        'motivo',
        'clase',
        'origen',
        'inicio',
        'fin',
        'cerrada_al_envio',
    ];

    protected function casts(): array
    {
        return [
            'cerrada_al_envio' => 'boolean',
        ];
    }

    /**
     * Clase derivada del motivo, SIEMPRE en el servidor: el soplador no
     * clasifica (menos toques, cero sesgo) y el request jamas la impone.
     */
    public static function claseDe(string $motivo): string
    {
        return in_array($motivo, self::MOTIVOS_PLANIFICADOS, true)
            ? self::CLASE_PLANIFICADA
            : self::CLASE_NO_PLANIFICADA;
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ProduccionReporte::class, 'reporte_id');
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class, 'maquina_id');
    }

    // --- Horas. No existe cast 'time' en Eloquent: MySQL devuelve "H:i:s" y
    // SQLite lo que se inserto; SIEMPRE rebanar a H:i antes de comparar o
    // mostrar (mismo patron que AgendaTrabajo::hora_corta). ---

    public function getInicioCortaAttribute(): ?string
    {
        return $this->inicio ? substr((string) $this->inicio, 0, 5) : null;
    }

    public function getFinCortaAttribute(): ?string
    {
        return $this->fin ? substr((string) $this->fin, 0, 5) : null;
    }

    /**
     * Duracion en minutos; null si la parada sigue abierta. Modulo 1440: una
     * parada abierta en turno noche (23:40) que se cierra al enviar de
     * madrugada (06:30) cruza la medianoche y el modulo la endereza sin
     * clamps (un clamp falsificaria el dato). El input manual no puede
     * producir fin < inicio (after_or_equal en la validacion del endpoint).
     */
    public function getDuracionMinutosAttribute(): ?int
    {
        if (! $this->inicio_corta || ! $this->fin_corta) {
            return null;
        }

        [$hi, $mi] = array_map('intval', explode(':', $this->inicio_corta));
        [$hf, $mf] = array_map('intval', explode(':', $this->fin_corta));

        return ((($hf * 60 + $mf) - ($hi * 60 + $mi)) + 1440) % 1440;
    }

    /**
     * Duracion legible: "45 min", "2 h", "2 h 15 min". Null si sigue abierta.
     */
    public function getDuracionLabelAttribute(): ?string
    {
        $minutos = $this->duracion_minutos;

        if ($minutos === null) {
            return null;
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        if ($horas === 0) {
            return "{$resto} min";
        }

        return $resto === 0 ? "{$horas} h" : "{$horas} h {$resto} min";
    }
}
