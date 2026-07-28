<?php

namespace App\Services\Dte;

use App\Support\FechaNegocio;

/**
 * Lo que DaliGo quiere emitir, en términos del NEGOCIO y no del emisor.
 *
 * Es el input del puerto EmisorDte: el mismo objeto sirve para que lo emita
 * Bsale hoy, un proveedor de DTE mañana o emisión propia algún día. Por eso acá
 * no hay `documentTypeId`, `officeId` ni `priceListId` de Bsale: esos son
 * detalles de un emisor concreto y los traduce su implementación.
 *
 * `salesId` es la clave de idempotencia y es OBLIGATORIA. Se deriva del origen
 * (ej. "ST-1234" para la orden de servicio 1234), de modo que reintentar la
 * emisión del mismo origen no puede producir dos documentos. Emitir dos veces
 * no es un bug de interfaz: es un problema tributario que se arregla con nota
 * de crédito.
 */
class DocumentoTributario
{
    /** Códigos de DTE del SII que este módulo maneja hoy. */
    public const FACTURA_AFECTA = 33;

    public const FACTURA_EXENTA = 34;

    public const BOLETA = 39;

    public const BOLETA_EXENTA = 41;

    public const GUIA_DESPACHO = 52;

    public const NOTA_DEBITO = 56;

    public const NOTA_CREDITO = 61;

    /**
     * RUT genérico del SII para boletas sin identificación del receptor
     * ("consumidor final"). Se usa cuando la venta de mostrador no pide datos.
     */
    public const RUT_CONSUMIDOR_FINAL = '66666666-6';

    /**
     * @param  int  $tipoDte  Código del SII (ver constantes).
     * @param  string  $salesId  Clave de idempotencia derivada del origen. Obligatoria.
     * @param  list<LineaDocumento>  $lineas
     * @param  string|null  $receptorRut  Normalizado (12345678-9). Null en boleta a consumidor final.
     * @param  bool  $declararAlSii  false solo para documentos internos que NO son tributarios.
     * @param  bool  $rebajaStock  Si el emisor debe descontar stock (Bsale: `dispatch`).
     * @param  bool  $enviarCorreoAlCliente  Si el emisor manda el documento por correo.
     * @param  array<string, mixed>  $origen  Referencias en DaliGo: orden_servicio_id, sucursal_id, emitido_por.
     */
    public function __construct(
        public int $tipoDte,
        public string $salesId,
        public array $lineas,
        public ?string $receptorRut = null,
        public ?string $receptorNombre = null,
        public ?string $receptorGiro = null,
        public ?string $receptorDireccion = null,
        public ?string $receptorComuna = null,
        public ?string $receptorCiudad = null,
        public ?string $receptorEmail = null,
        public ?string $fechaEmision = null,
        public ?string $observacion = null,
        public bool $declararAlSii = true,
        public bool $rebajaStock = false,
        public bool $enviarCorreoAlCliente = false,
        public array $origen = [],
    ) {}

    /**
     * Fecha de emisión efectiva. Usa el día de NEGOCIO (no `now()`): una venta
     * nocturna quedaría fechada mañana (mismo criterio que P-TZ-01 en el resto
     * del proyecto).
     */
    public function fechaEmisionEfectiva(): string
    {
        return $this->fechaEmision ?? FechaNegocio::hoy();
    }

    /** Suma de los netos de las líneas (pesos enteros). */
    public function neto(): int
    {
        $total = 0;
        foreach ($this->lineas as $linea) {
            $total += $linea->netoLinea();
        }

        return $total;
    }

    /** ¿Es una boleta? Cambia el tratamiento del receptor y del envío al SII. */
    public function esBoleta(): bool
    {
        return in_array($this->tipoDte, [self::BOLETA, self::BOLETA_EXENTA], true);
    }

    /** ¿El documento va exento de IVA? */
    public function esExento(): bool
    {
        return in_array($this->tipoDte, [self::FACTURA_EXENTA, self::BOLETA_EXENTA], true);
    }
}
