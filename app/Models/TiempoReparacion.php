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
     * Tope de horas de mano de obra de UNA orden. Regla del dueño (28-08-2026): «no quiero que
     * se sumen 5 horas […] cuando un dispensador se desarma completo más estos cambios máximo
     * puede ser dos horas, más de ahí no pasa».
     *
     * Por qué existe: el desarme se paga UNA vez. Si el técnico ya abrió el dispensador para
     * cambiar la caldera, cambiar además la llave no cuesta otra hora entera — sumar
     * linealmente cobraría de más. Lo edita jefatura desde «Costos generales de reparación»,
     * así que vive en `configuraciones` y no en `config/`: un número que se calibra con el uso
     * no puede pedir un deploy para cambiar.
     */
    public const CLAVE_TOPE_HORAS = 'st_tope_horas_mano_obra';

    public const TOPE_HORAS_DEFAULT = 2.0;

    public static function topeHoras(): float
    {
        return (float) Configuracion::get(self::CLAVE_TOPE_HORAS, self::TOPE_HORAS_DEFAULT);
    }

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

    /**
     * Horas a cobrar por un conjunto de trabajos: la SUMA, con el tope de arriba aplicado.
     *
     * OJO CON EL PISO, que no es un adorno: es `min(suma, max(tope, el mayor))`. Hoy el trabajo
     * más largo del catálogo es 1,5 h y el tope 2 h, así que el `max` interno no cambia nada.
     * Pero el día que jefatura cargue un trabajo de 3 h, un tope de 2 le cobraría MENOS que su
     * propio tiempo estándar, en silencio y sin que nadie lo note (el técnico ve un número
     * plausible). El piso hace que el tope solo pueda recortar la ACUMULACIÓN de trabajos,
     * nunca el tiempo de un trabajo individual.
     *
     * @param  iterable<float|string>  $horas  horas de cada trabajo marcado
     */
    public static function horasACobrar(iterable $horas, ?float $tope = null): float
    {
        $lista = [];
        foreach ($horas as $h) {
            $lista[] = (float) $h;
        }

        if ($lista === []) {
            return 0.0;
        }

        $tope ??= static::topeHoras();

        return min(array_sum($lista), max($tope, max($lista)));
    }

    /** Suma cruda, sin tope: se muestra al lado del tope para que el técnico vea de dónde sale el recorte. */
    public static function horasSumadas(iterable $horas): float
    {
        $total = 0.0;
        foreach ($horas as $h) {
            $total += (float) $h;
        }

        return $total;
    }

    /** Horas sin ceros sobrantes: 1.0 → "1", 1.5 → "1,5". */
    public function getHorasFmtAttribute(): string
    {
        return static::fmt((float) $this->horas);
    }

    /** Mismo formato, para un número que no viene de una fila (una suma, el tope). */
    public static function fmt(float $horas): string
    {
        return rtrim(rtrim(number_format($horas, 1, ',', ''), '0'), ',');
    }

    /**
     * El trabajo SIN su remate. El catálogo guarda las dos cosas pegadas («Cambio de caldera —
     * funciona normal») porque nació como una respuesta completa para UNA reparación. Al marcar
     * tres trabajos, repetir «funciona normal» tres veces sería absurdo: el remate se elige una
     * sola vez y va al final de la frase.
     *
     * Se separa AL MOSTRAR y no en la base a propósito: renombrar las 21 filas del catálogo
     * habría cortado el único vínculo que las órdenes históricas tienen con él (su texto), y el
     * resultado en pantalla es idéntico.
     */
    public function getTrabajoCortoAttribute(): string
    {
        return static::sinRemate((string) $this->trabajo);
    }

    public function getRemateAttribute(): ?string
    {
        $partes = preg_split('/\s+—\s+/u', (string) $this->trabajo, 2);

        return isset($partes[1]) ? trim($partes[1]) : null;
    }

    public static function sinRemate(string $trabajo): string
    {
        return trim(preg_split('/\s+—\s+/u', $trabajo, 2)[0]);
    }
}
