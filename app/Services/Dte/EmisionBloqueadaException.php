<?php

namespace App\Services\Dte;

/**
 * El candado de emisión frenó la operación ANTES de llamar al emisor.
 *
 * Se distingue del resto de las fallas por lo que significa: no hubo error, no
 * se cayó la red y el emisor nunca se enteró. **No se creó nada** y no hay que
 * reintentar — hay que cambiar la configuración a propósito, con autorización.
 *
 * Ver App\Services\Dte\CandadoDeEmision para las dos condiciones.
 */
class EmisionBloqueadaException extends EmisionException
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje, 403);
    }
}
