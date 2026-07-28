<?php

namespace App\Services\Dte;

/**
 * Forma de pago de un documento tributario, en NUESTRO vocabulario.
 *
 * Existe porque Contabilidad definió (28-jul-2026) que **el pago se registra en
 * el momento de emitir**, no después: el documento sale con su pago o no sale.
 * Un documento emitido sin pago queda descuadrado en el cierre de caja del
 * emisor, y ese descuadre lo descubre alguien al final del día sin saber de
 * dónde viene.
 *
 * Deliberadamente NO reutiliza los valores de `OrdenServicioCotizacion::MEDIOS`
 * (`sala_ventas`, `al_retiro`…), que describen DÓNDE y CUÁNDO paga el cliente —
 * información comercial. Acá interesa CON QUÉ paga, que es lo que el emisor
 * necesita para cuadrar la caja. Un "paga al retiro" termina siendo efectivo o
 * transferencia, y esa es la traducción que hace quien emite.
 */
final class FormaPago
{
    public const EFECTIVO = 'efectivo';

    public const TRANSFERENCIA = 'transferencia';

    public const TARJETA_DEBITO = 'tarjeta_debito';

    public const TARJETA_CREDITO = 'tarjeta_credito';

    public const CHEQUE = 'cheque';

    /** Venta a crédito: el documento se emite y el pago se registra después. */
    public const CREDITO = 'credito';

    public const TODAS = [
        self::EFECTIVO,
        self::TRANSFERENCIA,
        self::TARJETA_DEBITO,
        self::TARJETA_CREDITO,
        self::CHEQUE,
        self::CREDITO,
    ];

    public const ETIQUETAS = [
        self::EFECTIVO => 'Efectivo',
        self::TRANSFERENCIA => 'Transferencia',
        self::TARJETA_DEBITO => 'Tarjeta de débito',
        self::TARJETA_CREDITO => 'Tarjeta de crédito',
        self::CHEQUE => 'Cheque',
        self::CREDITO => 'Crédito (paga después)',
    ];

    public static function existe(?string $forma): bool
    {
        return $forma !== null && in_array($forma, self::TODAS, true);
    }

    public static function etiqueta(string $forma): string
    {
        return self::ETIQUETAS[$forma] ?? $forma;
    }

    /**
     * ¿Esta forma de pago se registra junto con el documento?
     *
     * El crédito es la única que no: el documento sale sin pago porque el pago
     * todavía no existe. Distinguirlas evita mandar al emisor un pago de $0 que
     * después nadie entiende en el cierre.
     */
    public static function seRegistraAlEmitir(string $forma): bool
    {
        return $forma !== self::CREDITO;
    }
}
