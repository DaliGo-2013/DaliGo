<?php

namespace App\Services\Dte;

/**
 * PUERTO de emisión de documentos tributarios electrónicos (M05).
 *
 * Es la pieza central del diseño: la aplicación (controladores, jobs, comandos)
 * depende SOLO de esta interfaz y nunca de un emisor concreto. Hoy la implementa
 * BsaleEmisor; el día que la empresa cambie a un proveedor de DTE o emita por sí
 * misma, se escribe otra implementación y el resto de DaliGo no se toca.
 *
 * Eso no es prolijidad: es la ruta de salida. La investigación de julio 2026
 * concluyó que construir el timbre propio son 45-70 días-persona más
 * mantenimiento normativo permanente (el SII movió el formato cuatro veces en 12
 * meses), mientras que delegarlo a un emisor cuesta días. Con este puerto,
 * cambiar de emisor es sustituir una clase.
 *
 * Contrato:
 *   - `emitir()` es IDEMPOTENTE por `DocumentoTributario::$salesId`: llamarla dos
 *     veces con el mismo salesId debe devolver el documento ya emitido, no crear
 *     uno nuevo.
 *   - Un documento RECHAZADO por el SII se devuelve como ResultadoEmision con
 *     estado RECHAZADO (el documento existe). Solo las fallas de emisión lanzan
 *     EmisionException.
 */
interface EmisorDte
{
    /**
     * Nombre corto del emisor, tal como se guarda en `dte_emitidos.emisor`
     * (ej. 'bsale'). Permite convivir con más de un emisor en una migración.
     */
    public function nombre(): string;

    /**
     * Emite el documento. Idempotente por `$documento->salesId`.
     *
     * @throws EmisionException si la emisión falla (el documento no existe).
     * @throws CajaCerradaException si la caja del día está cerrada en el emisor.
     */
    public function emitir(DocumentoTributario $documento): ResultadoEmision;

    /**
     * Vuelve a leer el documento en el emisor para conocer el veredicto del SII.
     * Lo usa el cron que persigue los que quedaron en PENDIENTE o ENVIADO.
     *
     * @throws EmisionException
     */
    public function consultarEstado(string $documentoExternoId): ResultadoEmision;

    /**
     * Emite una nota de crédito que anula (total o parcialmente) un documento ya
     * emitido. Es el ÚNICO camino válido para deshacer un DTE aceptado por el
     * SII: los documentos electrónicos no se borran.
     *
     * @param  string  $documentoExternoId  Documento original a anular.
     * @param  string  $salesId  Clave de idempotencia de la nota de crédito.
     *
     * @throws EmisionException
     */
    public function anularConNotaCredito(
        string $documentoExternoId,
        string $motivo,
        string $salesId,
    ): ResultadoEmision;

    /**
     * Folios (CAF) disponibles para un tipo de DTE. Alimenta el aviso preventivo:
     * quedarse sin folios, o usar un CAF vencido, frena la emisión.
     *
     * @throws EmisionException
     */
    public function foliosDisponibles(int $tipoDte): FoliosDisponibles;
}
