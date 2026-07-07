<?php

namespace App\Mail;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo simple al cliente cuando el encargado confirma la recepcion de una
 * maquina ingresada por QR (P-M12-01, piloto). Lleva el folio y el detalle del
 * ingreso.
 *
 * Piloto standalone: usa el mailer nativo de Laravel (config/mail.php). Cuando
 * M15 (feature/m15-notificaciones) llegue a main, migrar a un evento
 * 'taller.recibido' del NotificacionDispatcher (ver docs/RUTA-MAESTRA.md E1).
 */
class IngresoTallerRecibido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrdenServicio $orden) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibimos tu equipo — Orden '.$this->orden->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.taller.recibido',
        );
    }
}
