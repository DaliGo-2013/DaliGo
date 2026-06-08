<?php

namespace App\Services\Bsale;

use RuntimeException;

/**
 * Error de la API de Bsale (HTTP no-2xx tras reintentos).
 */
class BsaleApiException extends RuntimeException
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
