<?php

namespace App\Services\Aprobaciones;

use RuntimeException;

/**
 * La solicitud ya no esta pendiente (doble-tap, o dos aprobadores
 * compitiendo): el segundo llega aqui SIN doble aplicacion — el re-check
 * de estado corre con la fila bloqueada (lockForUpdate). El controller de
 * la bandeja la traduce a un flash "ya fue resuelta".
 */
class AprobacionYaResueltaException extends RuntimeException
{
}
