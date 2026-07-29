<?php

namespace App\Services\Bsale;

use App\Models\Sucursal;
use App\Services\Dte\CajaCerradaException;
use App\Services\Dte\CandadoDeEmision;
use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\EmisionException;
use App\Services\Dte\EmisorDte;
use App\Services\Dte\EstadoSii;
use App\Services\Dte\FoliosDisponibles;
use App\Services\Dte\FormaPago;
use App\Services\Dte\LineaDocumento;
use App\Services\Dte\ResultadoEmision;

/**
 * Implementación del puerto EmisorDte contra Bsale (M05 · B3).
 *
 * Es un TRADUCTOR y nada más: toma un DocumentoTributario expresado en el
 * vocabulario del negocio y lo convierte al formulario que espera
 * `POST /v1/documents.json`, después lee la respuesta de vuelta a
 * ResultadoEmision. No decide qué emitir, no escribe en la base de datos y no
 * conoce las pantallas — de eso se encargan EmisionDte y sus llamadores.
 *
 * Todo lo que Bsale necesita y DaliGo no tiene (ids de tipo de documento, de
 * oficina y de medio de pago) vive en `config/dte.php` y arranca VACÍO. Si falta
 * un mapeo, esta clase lanza una excepción nombrando la clave exacta en lugar de
 * adivinar: emitir con la oficina equivocada es un documento tributario mal
 * atribuido, y la corrección es una nota de crédito.
 *
 * ⚠️ Lo verificado y lo no verificado. El shape de `documents.json` está
 * confirmado contra la API real (ver `docs/BSALE_API.md` §Documentos). Los de
 * `returns.json` (notas de crédito) y del CAF salen de la documentación pública
 * y NO se han ejercido contra la cuenta: se confirman en el paso B6
 * (`dte:emitir-prueba`) y hasta entonces esos dos métodos deben tratarse como
 * borrador. Los tests cubren NUESTRA lógica (traducción, idempotencia, errores),
 * que es lo que se puede probar sin credencial.
 */
class BsaleEmisor implements EmisorDte
{
    public function __construct(private BsaleClient $client) {}

    public function nombre(): string
    {
        return 'bsale';
    }

    public function emitir(DocumentoTributario $documento): ResultadoEmision
    {
        // El candado va PRIMERO, antes de armar cualquier cosa: si este proceso no
        // puede emitir, ni se toca la red. Ver CandadoDeEmision.
        CandadoDeEmision::verificar();

        $payload = $this->armarPayload($documento);

        try {
            $respuesta = $this->client->post('documents.json', $payload);
        } catch (BsaleApiException $e) {
            throw $this->traducirError($e);
        }

        return $this->leerDocumento($respuesta, $documento->declararAlSii);
    }

    public function consultarEstado(string $documentoExternoId): ResultadoEmision
    {
        try {
            $respuesta = $this->client->get("documents/{$documentoExternoId}.json");
        } catch (BsaleApiException $e) {
            throw $this->traducirError($e);
        }

        return $this->leerDocumento($respuesta);
    }

    /**
     * Nota de crédito que anula un documento ya emitido.
     *
     * ⚠️ Shape NO verificado contra la API real (ver la nota de la clase). El
     * `type` 1 corresponde a la devolución que anula el documento completo sin
     * devolver stock; el 0 devuelve stock además. Se usa 1 porque anular por
     * error de emisión no implica que el equipo vuelva a bodega.
     */
    public function anularConNotaCredito(
        string $documentoExternoId,
        string $motivo,
        string $salesId,
    ): ResultadoEmision {
        // Una nota de crédito también es un documento tributario que se emite.
        CandadoDeEmision::verificar();

        $payload = [
            'documentId' => (int) $documentoExternoId,
            'type' => 1,
            'motive' => $motivo,
            'salesId' => $salesId,
            'declareSii' => 1,
        ];

        try {
            $respuesta = $this->client->post('returns.json', $payload);
        } catch (BsaleApiException $e) {
            throw $this->traducirError($e);
        }

        // La devolución responde el documento de la nota de crédito anidado
        // cuando lo genera; si no, la propia respuesta ya es el documento.
        return $this->leerDocumento($respuesta['creditNote'] ?? $respuesta);
    }

    /**
     * Folios disponibles para un tipo de DTE.
     *
     * ⚠️ Shape NO verificado contra la API real (ver la nota de la clase), y con
     * una duda de fondo que va más allá del shape: el soporte de Bsale respondió
     * el 28-jul-2026 que *"no existe un método para reservar un folio ni para
     * consultar cuál será el siguiente folio antes de emitir"*, porque la
     * asignación ocurre al generar el DTE.
     *
     * Eso NO responde exactamente lo que este método necesita —el *stock
     * restante* y el vencimiento del CAF son otra pregunta que "cuál es el
     * próximo folio"— pero deja el endpoint en duda. Hay una repregunta pendiente
     * (ver `docs/BSALE_API.md` §Documentos y §11 del informe). Si resulta que no
     * hay forma por API, el aviso preventivo de folios pasa a ser control MANUAL
     * y este método debe eliminarse en vez de devolver números inventados.
     */
    public function foliosDisponibles(int $tipoDte): FoliosDisponibles
    {
        try {
            $respuesta = $this->client->get('document_types/caf.json', [
                'codesii' => $tipoDte,
            ]);
        } catch (BsaleApiException $e) {
            throw $this->traducirError($e);
        }

        $caf = $respuesta['items'][0] ?? $respuesta;

        $desde = isset($caf['from']) ? (int) $caf['from'] : null;
        $hasta = isset($caf['to']) ? (int) $caf['to'] : null;
        $ultimo = isset($caf['lastNumber']) ? (int) $caf['lastNumber'] : null;

        // Disponibles = lo que queda del tramo. Si el emisor ya informa
        // `available`, se prefiere ese número antes que deducirlo.
        $disponibles = isset($caf['available'])
            ? (int) $caf['available']
            : max(0, ($hasta ?? 0) - ($ultimo ?? $desde ?? 0));

        return new FoliosDisponibles(
            tipoDte: $tipoDte,
            disponibles: $disponibles,
            ultimoUsado: $ultimo,
            desde: $desde,
            hasta: $hasta,
            venceEl: $caf['expirationDate'] ?? null,
            vencido: (bool) ($caf['expired'] ?? false),
        );
    }

    /**
     * Arma el cuerpo de `documents.json`.
     *
     * Notas de traducción que no son obvias:
     *
     * - NO se manda `priceListId`. Cada línea viaja con su `netUnitValue`
     *   explícito, así que la lista de precios no cumple ninguna función y
     *   mandarla solo agrega una manera de que el emisor cotice distinto de lo
     *   que el cliente ya pagó.
     * - `declareSii` es 1/0 y no true/false: la API lo documenta como entero.
     * - En boleta a consumidor final NO se manda nodo `client`. Mandar el RUT
     *   genérico 66666666-6 como cliente crearía una ficha basura en Bsale por
     *   cada venta de mostrador.
     */
    private function armarPayload(DocumentoTributario $documento): array
    {
        if ($documento->lineas === []) {
            throw new EmisionException('Un documento tributario no puede ir sin líneas de detalle.');
        }

        $payload = [
            'emissionDate' => $documento->fechaEmisionEfectiva(),
            'declareSii' => $documento->declararAlSii ? 1 : 0,
            'salesId' => $documento->salesId,
            'details' => array_map($this->armarLinea(...), $documento->lineas),
        ];

        // documentTypeId explícito si está configurado; si no, codeSii.
        $tipoId = config("dte.bsale.tipos_documento.{$documento->tipoDte}");
        if ($tipoId) {
            $payload['documentTypeId'] = (int) $tipoId;
        } else {
            $payload['codeSii'] = $documento->tipoDte;
        }

        if ($oficina = $this->oficinaDe($documento)) {
            $payload['officeId'] = $oficina;
        }

        if ($cliente = $this->armarCliente($documento)) {
            $payload['client'] = $cliente;
        }

        if ($pagos = $this->armarPagos($documento)) {
            $payload['payments'] = $pagos;
        }

        if (filled($documento->observacion)) {
            $payload['officeNote'] = $documento->observacion;
        }

        if ($documento->rebajaStock) {
            $payload['dispatch'] = 1;
        }

        if ($documento->enviarCorreoAlCliente && filled($documento->receptorEmail)) {
            $payload['sendEmail'] = 1;
        }

        return $payload;
    }

    /**
     * Una línea de detalle. El SKU va en `code` (Bsale resuelve la variante) y
     * el nombre completo en `comment`, que es el texto que se imprime.
     *
     * Por la Res. Ex. SII N°36/2024 la descripción impresa tiene que ser el
     * producto real, sin abreviaturas ni códigos internos — por eso el nombre
     * viaja aunque el SKU ya identifique el producto.
     */
    private function armarLinea(LineaDocumento $linea): array
    {
        $detalle = [
            'quantity' => $linea->cantidad,
            'netUnitValue' => $linea->precioNetoUnitario,
            'comment' => $linea->comentario ?? $linea->descripcion,
        ];

        if ($linea->varianteExternaId) {
            $detalle['variantId'] = $linea->varianteExternaId;
        } elseif (filled($linea->codigoProducto)) {
            $detalle['code'] = $linea->codigoProducto;
        }

        if ($linea->descuentoPct > 0) {
            $detalle['discount'] = $linea->descuentoPct;
        }

        return $detalle;
    }

    /**
     * Nodo `client`. La factura EXIGE identificación completa del receptor (sin
     * RUT, giro y dirección el documento no es válido); la boleta puede ir sin
     * cliente.
     */
    private function armarCliente(DocumentoTributario $documento): ?array
    {
        $esConsumidorFinal = blank($documento->receptorRut)
            || $documento->receptorRut === DocumentoTributario::RUT_CONSUMIDOR_FINAL;

        if ($documento->esBoleta() && $esConsumidorFinal) {
            return null;
        }

        if ($esConsumidorFinal) {
            throw new EmisionException(
                'Una factura necesita el RUT del receptor: no se puede emitir a consumidor final.'
            );
        }

        if (! $documento->esBoleta() && blank($documento->receptorGiro)) {
            throw new EmisionException(
                'Una factura necesita el giro del receptor (lo exige el formato del SII).'
            );
        }

        return array_filter([
            'code' => $documento->receptorRut,
            'company' => $documento->receptorNombre,
            'activity' => $documento->receptorGiro,
            'address' => $documento->receptorDireccion,
            'municipality' => $documento->receptorComuna,
            'city' => $documento->receptorCiudad,
            'email' => $documento->receptorEmail,
        ], fn ($valor) => filled($valor));
    }

    /**
     * Nodo `payments`. Un solo pago por el total del documento: Contabilidad
     * definió que el pago se registra al emitir.
     */
    private function armarPagos(DocumentoTributario $documento): array
    {
        if (blank($documento->formaPago)) {
            return [];
        }

        if (! FormaPago::existe($documento->formaPago)) {
            throw new EmisionException("Forma de pago desconocida: «{$documento->formaPago}».");
        }

        if (! FormaPago::seRegistraAlEmitir($documento->formaPago)) {
            return [];
        }

        $tipo = config("dte.bsale.medios_pago.{$documento->formaPago}");

        if (! $tipo) {
            throw new EmisionException(
                "Falta el medio de pago de Bsale para «{$documento->formaPago}»: "
                ."configúralo en dte.bsale.medios_pago.{$documento->formaPago}."
            );
        }

        return [[
            'paymentTypeId' => (int) $tipo,
            // El total CON IVA declarado por el documento, que es lo que el
            // cliente entrega. No se recalcula desde los netos: ver
            // DocumentoTributario::totalEfectivo().
            'amount' => $documento->totalEfectivo(),
            'recordDate' => $documento->fechaEmisionEfectiva(),
        ]];
    }

    /**
     * officeId de la sucursal donde se emite. Null si el documento no declara
     * sucursal (Bsale usa la oficina por defecto de la cuenta).
     */
    private function oficinaDe(DocumentoTributario $documento): ?int
    {
        $sucursalId = $documento->origen['sucursal_id'] ?? null;

        if (! $sucursalId) {
            return null;
        }

        $codigo = Sucursal::whereKey($sucursalId)->value('codigo');

        if (blank($codigo)) {
            throw new EmisionException("La sucursal {$sucursalId} no existe: no se puede emitir desde ella.");
        }

        $oficina = config("dte.bsale.oficinas.{$codigo}");

        if (! $oficina) {
            throw new EmisionException(
                "Falta la oficina de Bsale para la sucursal «{$codigo}»: "
                ."configúrala en dte.bsale.oficinas.{$codigo}."
            );
        }

        return (int) $oficina;
    }

    /**
     * Lee el documento devuelto por Bsale.
     *
     * `informedSii` tiene escala invertida (0 = aceptado) y por eso el mapeo
     * pasa siempre por EstadoSii::desdeBsale — ver el comentario de esa clase.
     */
    private function leerDocumento(array $documento, bool $declaradoAlSii = true): ResultadoEmision
    {
        $estado = $declaradoAlSii
            ? EstadoSii::desdeBsale(
                array_key_exists('informedSii', $documento) ? (int) $documento['informedSii'] : null
            )
            : EstadoSii::NO_DECLARADO;

        $id = $documento['id'] ?? null;

        return new ResultadoEmision(
            exitoso: true,
            estado: $estado,
            folio: isset($documento['number']) ? (int) $documento['number'] : null,
            documentoExternoId: $id !== null ? (string) $id : null,
            mensaje: $documento['siiMessage'] ?? null,
            urlXml: $documento['urlXml'] ?? null,
            urlPdf: $documento['urlPdf'] ?? null,
            neto: (int) round((float) ($documento['netAmount'] ?? 0)),
            iva: (int) round((float) ($documento['taxAmount'] ?? 0)),
            total: (int) round((float) ($documento['totalAmount'] ?? 0)),
            ted: $documento['ted'] ?? null,
            crudo: $documento,
        );
    }

    /**
     * Traduce el error del emisor a algo que quien atiende pueda accionar.
     *
     * Sin esto, la persona del mostrador ve el texto crudo de Bsale en inglés y
     * no tiene forma de saber que la solución está a un clic en otro sistema.
     */
    private function traducirError(BsaleApiException $e): EmisionException
    {
        $mensaje = mb_strtolower($e->getMessage());

        if (str_contains($mensaje, 'closed box')) {
            return new CajaCerradaException;
        }

        if (str_contains($mensaje, 'salesid')) {
            return new EmisionException(
                'Bsale ya tiene un documento con este identificador de venta, así que no se emitió '
                .'uno nuevo (es la protección contra duplicados). Búscalo en Bsale antes de reintentar.',
                $e->status(),
            );
        }

        if (str_contains($mensaje, 'caf') || str_contains($mensaje, 'folio')) {
            return new EmisionException(
                'Problema con los folios en Bsale (agotados o vencidos): no se puede emitir hasta '
                .'resolverlo allá. Detalle: '.$e->getMessage(),
                $e->status(),
            );
        }

        return new EmisionException('No se pudo emitir en Bsale. '.$e->getMessage(), $e->status());
    }
}
