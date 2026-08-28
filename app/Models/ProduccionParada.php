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

    /**
     * Motivos vigentes: editables en Configuración (`produccion_motivos_parada`,
     * OPE-2 — UI una-por-línea); las constantes son el histórico y el fallback
     * con BD virgen (regla de oro). La lista sigue CERRADA para el operario
     * (Rule::in) — lo que cambia es quién la escribe. Quitar un motivo con
     * paradas históricas NO las rompe: motivo y clase quedaron PERSISTIDOS en
     * cada fila (el OEE de ayer no se reescribe).
     *
     * @return array<int, string>
     */
    public static function motivos(): array
    {
        return Configuracion::getLista('produccion_motivos_parada', self::MOTIVOS);
    }

    /**
     * Subconjunto planificado vigente (`produccion_motivos_planificados`).
     * La UI de Configuración valida el par planificados ⊆ motivos
     * (ConfiguracionController::PARES_SUBCONJUNTO).
     *
     * @return array<int, string>
     */
    public static function motivosPlanificados(): array
    {
        return Configuracion::getLista('produccion_motivos_planificados', self::MOTIVOS_PLANIFICADOS);
    }

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
     * Deriva de la lista VIGENTE (OPE-2) y se PERSISTE al crear la parada:
     * mover un motivo entre clases hoy solo afecta paradas futuras — el OEE
     * histórico lee la columna `clase`, no esta función.
     */
    public static function claseDe(string $motivo): string
    {
        return in_array($motivo, self::motivosPlanificados(), true)
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

        return self::minutosEntre($this->inicio_corta, $this->fin_corta);
    }

    /**
     * Cuanto LLEVA una parada abierta hasta una hora de pared dada (el panel
     * vivo le pasa FechaNegocio::ahora()->format('H:i')). Parametro explicito
     * a proposito: sin reloj oculto, testeable con horas fijas. Mismo modulo
     * 1440 que la duracion cerrada (las paradas son siempre del dia del
     * reporte, asi que la corrida real nunca supera las 24 h).
     */
    public function duracionMinutosHasta(?string $horaFin): ?int
    {
        if (! $this->inicio_corta || ! is_string($horaFin) || strlen($horaFin) < 5) {
            return null;
        }

        return self::minutosEntre($this->inicio_corta, substr($horaFin, 0, 5));
    }

    /**
     * Duracion legible: "45 min", "2 h", "2 h 15 min". Null si sigue abierta.
     */
    public function getDuracionLabelAttribute(): ?string
    {
        return self::labelDe($this->duracion_minutos);
    }

    /** Formatea minutos a "45 min" / "2 h" / "2 h 15 min" (null pasa de largo). */
    public static function labelDe(?int $minutos): ?string
    {
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

    /** Diferencia entre dos horas de pared H:i, envolviendo la medianoche. */
    private static function minutosEntre(string $inicio, string $fin): int
    {
        [$hi, $mi] = array_map('intval', explode(':', $inicio));
        [$hf, $mf] = array_map('intval', explode(':', $fin));

        return ((($hf * 60 + $mf) - ($hi * 60 + $mi)) + 1440) % 1440;
    }
}
