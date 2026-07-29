<?php

namespace App\Services\Dte;

/**
 * Emisor que puede mostrar QUÉ le mandaría al proveedor, sin mandarlo.
 *
 * Es el "ensayo en seco": armar el documento completo y mirarlo antes de que
 * exista. Sirve para revisar con Contabilidad —o con quien atiende— que el
 * desglose, los códigos y los montos son los correctos, cuando todavía no hay
 * nada que anular.
 *
 * Va en una interfaz APARTE y no en EmisorDte a propósito. EmisorDte es el puerto
 * neutral: habla de documentos tributarios, no de proveedores. Lo que devuelve
 * `previsualizar()` es, en cambio, el formulario crudo de un proveedor concreto,
 * con sus nombres de campo. Meterlo en el puerto obligaría a todo emisor futuro a
 * exponer las tripas de su API, que es justo lo que el puerto evita.
 *
 * Quien la use debe preguntar antes (`instanceof`): un emisor puede no ofrecerla.
 */
interface EmisorPrevisualizable
{
    /**
     * El cuerpo exacto que se le enviaría al proveedor para emitir este
     * documento. NO emite ni contacta al proveedor.
     *
     * @return array<string, mixed>
     *
     * @throws EmisionException si el documento no se puede armar (datos faltantes).
     */
    public function previsualizar(DocumentoTributario $documento): array;
}
