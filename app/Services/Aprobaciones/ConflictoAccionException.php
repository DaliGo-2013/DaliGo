<?php

namespace App\Services\Aprobaciones;

use RuntimeException;

/**
 * El objetivo de la accion diferida cambio entre la solicitud y la
 * aprobacion. El servicio la captura DENTRO de la misma transaccion y
 * resuelve deterministico: la aprobacion pasa a `rechazada` con un
 * `resultado_motivo` claro y se notifica al solicitante.
 */
class ConflictoAccionException extends RuntimeException
{
}
