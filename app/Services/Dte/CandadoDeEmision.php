<?php

namespace App\Services\Dte;

/**
 * Candado que decide si este proceso tiene permiso de EMITIR (M05 · B4).
 *
 * Existe por un hecho de Bsale que no se puede cambiar y que es el riesgo número
 * uno del módulo: **el ambiente lo define ÚNICAMENTE la credencial.** La
 * dirección de la API es la misma (`api.bsale.io/v1`) para prueba y para
 * producción, así que nada en el código, en la URL o en la respuesta indica si lo
 * que se está por crear es un documento de mentira o una factura real con folio
 * del SII. Un token de producción en el `.env` de un notebook convierte
 * cualquier prueba en un documento tributario verdadero.
 *
 * La protección no puede ser "acordarse": tiene que ser una barrera. Son dos
 * condiciones, y las dos se piden a la vez:
 *
 *   1. `dte.emision_habilitada` debe estar en true. Arranca APAGADO. Es el
 *      interruptor que representa la autorización de Gerencia para la primera
 *      emisión real (Fase 4 del informe).
 *   2. Si la credencial se declara de `produccion`, el proceso tiene que estar
 *      corriendo en el servidor de producción. Un token declarado de producción
 *      en local no emite, ni por error de código ni por un comando mal tipeado.
 *
 * LO QUE ESTE CANDADO NO BLOQUEA: **leer**. Las consultas (catálogo, precios,
 * documentos ya emitidos, tipos de documento, oficinas, medios de pago) siguen
 * funcionando con cualquier credencial, porque el plan acordado con el dueño es
 * justamente recabar todos esos datos de la cuenta real ANTES de emitir nada.
 * Bloquear el arranque completo —como se había propuesto en un principio— habría
 * impedido esa etapa, que es la que permite llegar preparado al día de la prueba.
 */
final class CandadoDeEmision
{
    public const AMBIENTE_PRUEBA = 'prueba';

    public const AMBIENTE_PRODUCCION = 'produccion';

    public const AMBIENTES = [self::AMBIENTE_PRUEBA, self::AMBIENTE_PRODUCCION];

    /**
     * Lanza si este proceso no puede emitir. Se llama antes de CADA escritura
     * (emisión y nota de crédito), no una vez al arrancar: el estado del
     * interruptor puede cambiar entre peticiones.
     *
     * @throws EmisionBloqueadaException
     */
    public static function verificar(): void
    {
        $ambiente = self::ambiente();

        if ($ambiente === self::AMBIENTE_PRODUCCION && ! app()->environment('production')) {
            throw new EmisionBloqueadaException(
                'La credencial de Bsale está declarada como PRODUCCIÓN pero este proceso no corre en el '
                .'servidor de producción (entorno actual: '.app()->environment().'). No se emitió nada. '
                .'Emitir acá crearía un documento tributario real: usá una credencial de prueba '
                .'(BSALE_AMBIENTE=prueba).'
            );
        }

        if (! config('dte.emision_habilitada')) {
            throw new EmisionBloqueadaException(
                'La emisión de documentos tributarios está DESHABILITADA (dte.emision_habilitada). '
                .'Es el estado normal: se habilita a propósito, con autorización de Gerencia, para la '
                .'primera emisión real. No se emitió nada.'
            );
        }
    }

    /** ¿Se puede emitir ahora mismo? Para mostrarlo en pantalla sin provocar el error. */
    public static function permitido(): bool
    {
        try {
            self::verificar();

            return true;
        } catch (EmisionBloqueadaException) {
            return false;
        }
    }

    /** Motivo del bloqueo, o null si se puede emitir. Para avisos en la interfaz. */
    public static function motivoDelBloqueo(): ?string
    {
        try {
            self::verificar();

            return null;
        } catch (EmisionBloqueadaException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Ambiente declarado de la credencial.
     *
     * Un valor desconocido, vacío o AUSENTE se trata como PRODUCCIÓN. No es
     * paranoia: el default del config también es 'produccion', así que hay que
     * declarar `BSALE_AMBIENTE=prueba` para poder emitir en pruebas. Ante la duda,
     * el sistema asume la credencial más peligrosa — el lado seguro es el que no
     * emite, y una credencial sin etiquetar es exactamente el caso dudoso.
     */
    public static function ambiente(): string
    {
        $ambiente = (string) config('dte.ambiente');

        return in_array($ambiente, self::AMBIENTES, true) ? $ambiente : self::AMBIENTE_PRODUCCION;
    }

    public static function esProduccion(): bool
    {
        return self::ambiente() === self::AMBIENTE_PRODUCCION;
    }
}
