<?php

namespace App\Services\Notificaciones;

use App\Mail\NotificacionMail;
use App\Models\Notificacion;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Canal email: envia por el mailer configurado (log en local, smtp en prod).
 */
class CanalMail implements Canal
{
    public function enviar(Notificacion $notificacion): void
    {
        $email = $notificacion->destinatario ?: $notificacion->user?->email;

        if (blank($email)) {
            throw new RuntimeException('Notificación sin email de destino (ni destinatario ni user con correo).');
        }

        Mail::to($email)->send(new NotificacionMail($notificacion));
    }
}
