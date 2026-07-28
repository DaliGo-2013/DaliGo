<?php

namespace App\Services\Dte;

/**
 * La caja del día del documento está cerrada en el emisor.
 *
 * Es un caso real y documentado de Bsale, no una hipótesis: su tabla de errores
 * de API devuelve HTTP 400 con `"error": "closed box"` y el texto "El documento
 * que estás intentando eliminar, pertenece a una caja cerrada. Debes abrir caja
 * del día de generación del documento y reintentar".
 *
 * Se distingue del resto de las fallas porque el remedio NO es técnico ni un
 * reintento automático: alguien tiene que abrir la caja en Bsale. Si esto se
 * tratara como un error genérico, el usuario vería "no se pudo emitir" y nadie
 * sabría que la solución está a un clic en otro sistema.
 */
class CajaCerradaException extends EmisionException
{
    public function __construct(string $detalle = '')
    {
        parent::__construct(
            trim('La caja del día está cerrada en Bsale, así que no se puede emitir ni anular. '
                .'Abre la caja del día del documento y vuelve a intentarlo. '.$detalle),
            400,
        );
    }
}
