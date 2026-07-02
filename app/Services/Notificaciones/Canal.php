<?php

namespace App\Services\Notificaciones;

use App\Models\Notificacion;

/**
 * Contrato de canal de envio (PLAN-M15 §1.1). Cada canal transporta una
 * notificacion YA renderizada (titulo/cuerpo listos). Si el envio falla,
 * lanza una excepcion: el job EnviarNotificacion la captura y gestiona el
 * reintento (estado/backoff) — el canal NO conoce esa logica.
 */
interface Canal
{
    public function enviar(Notificacion $notificacion): void;
}
