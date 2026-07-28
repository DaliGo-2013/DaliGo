<?php

namespace App\Services\Dte;

/**
 * Estado de un documento tributario ante el SII, en NUESTRO vocabulario.
 *
 * Existe para no dejar entrar a la aplicación la semántica del emisor. Bsale
 * responde un entero `informedSii` cuya escala es CONTRAINTUITIVA — su propia
 * documentación dice literalmente: "0 es correcto, 1 es enviado, 2 es
 * rechazado". O sea que el 0, que en cualquier otro campo se leería como "nada
 * pasó todavía", aquí significa ACEPTADO. Un `if (! $informedSii)` o un switch
 * que asuma "0 = pendiente" invierte el estado tributario del documento y
 * mostraría como pendiente algo que el SII ya aceptó.
 *
 * Por eso el mapeo vive en UN solo lugar (desdeBsale) y el resto del proyecto
 * trabaja siempre con estas constantes.
 */
final class EstadoSii
{
    /** La fila se creó para reservar el sales_id, pero aún no hubo respuesta. */
    public const PENDIENTE = 'pendiente';

    /** El SII lo recibió y lo aceptó. Estado final feliz. */
    public const ACEPTADO = 'aceptado';

    /** Enviado al SII, todavía sin veredicto. No es final: hay que reconsultar. */
    public const ENVIADO = 'enviado';

    /** El SII lo rechazó. Estado final: se corrige con nota de crédito. */
    public const RECHAZADO = 'rechazado';

    /** Documento creado a propósito SIN declarar al SII (declareSii = 0). */
    public const NO_DECLARADO = 'no_declarado';

    /** Falló la llamada al emisor (red, 500, caja cerrada): el SII no opinó. */
    public const ERROR = 'error';

    public const TODOS = [
        self::PENDIENTE,
        self::ACEPTADO,
        self::ENVIADO,
        self::RECHAZADO,
        self::NO_DECLARADO,
        self::ERROR,
    ];

    /** Rótulo para la interfaz. */
    public const ETIQUETAS = [
        self::PENDIENTE => 'Pendiente',
        self::ACEPTADO => 'Aceptado por el SII',
        self::ENVIADO => 'Enviado al SII',
        self::RECHAZADO => 'Rechazado por el SII',
        self::NO_DECLARADO => 'No declarado',
        self::ERROR => 'Error al emitir',
    ];

    /**
     * Variante de x-badge por estado. OJO: x-badge solo define
     * brand|neutral|danger (paleta del design system) y cualquier otro valor
     * cae en brand sin avisar. Se espeja el criterio de AgendaTrabajo y del
     * taller: en curso = brand, cerrado-bien = neutral (como 'entregado'),
     * cerrado-mal = danger.
     */
    public const VARIANTES = [
        self::PENDIENTE => 'brand',
        self::ACEPTADO => 'neutral',
        self::ENVIADO => 'brand',
        self::RECHAZADO => 'danger',
        self::NO_DECLARADO => 'neutral',
        self::ERROR => 'danger',
    ];

    /**
     * Traduce el `informedSii` de Bsale. OJO con la escala invertida descrita
     * arriba: 0 = correcto (aceptado), 1 = enviado, 2 = rechazado.
     *
     * Un valor nulo o desconocido queda PENDIENTE (nunca se adivina un estado
     * tributario): lo resuelve la reconsulta.
     */
    public static function desdeBsale(?int $informedSii): string
    {
        return match ($informedSii) {
            0 => self::ACEPTADO,
            1 => self::ENVIADO,
            2 => self::RECHAZADO,
            default => self::PENDIENTE,
        };
    }

    /**
     * ¿El estado ya no va a cambiar solo? Los no finales (pendiente/enviado)
     * son los que el cron debe reconsultar.
     */
    public static function esFinal(string $estado): bool
    {
        return in_array($estado, [self::ACEPTADO, self::RECHAZADO, self::NO_DECLARADO], true);
    }

    public static function etiqueta(string $estado): string
    {
        return self::ETIQUETAS[$estado] ?? $estado;
    }

    public static function variante(string $estado): string
    {
        return self::VARIANTES[$estado] ?? 'neutral';
    }
}
