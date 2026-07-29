<?php

namespace App\Services\Dte;

use RuntimeException;

/**
 * Falla al emitir un documento tributario. Espeja el patrón de
 * App\Services\Bsale\BsaleApiException (mensaje + status HTTP).
 *
 * Regla: estas excepciones son para fallas de la EMISIÓN (el documento no
 * existe). Un documento que el SII RECHAZÓ no es una excepción — es un
 * ResultadoEmision con estado RECHAZADO, porque el documento sí existe y se
 * corrige con nota de crédito, no reintentando.
 */
class EmisionException extends RuntimeException
{
    public function __construct(string $message, private int $status = 0)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
