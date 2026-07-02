<?php

namespace App\Mail;

use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo generico del motor de notificaciones M15: titulo + cuerpo ya
 * renderizados por el dispatcher (placeholders resueltos desde payload).
 */
class NotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Notificacion $notificacion)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notificacion->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notificacion',
            with: [
                'titulo' => $this->notificacion->titulo,
                'cuerpo' => $this->notificacion->cuerpo,
            ],
        );
    }
}
