<?php

namespace App\Mail;

use App\Models\OrdenServicioCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Carta formal de cotización al cliente (P-M12-02, fase correo): explica qué se
 * hará, por qué (causa de la falla) y el costo total, con un link firmado donde
 * el cliente responde ACEPTO / NO ACEPTO (sin campo de comentario, decisión del
 * dueño). Renderiza SOLO desde el snapshot de la cotización, nunca desde la
 * orden viva.
 */
class CotizacionCliente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrdenServicioCotizacion $cotizacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cotización de reparación — Orden '.$this->cotizacion->orden->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.taller.cotizacion',
            with: [
                // Firmada: solo quien recibe este correo puede abrir/responder.
                'urlRespuesta' => URL::signedRoute('cotizacion.mostrar', ['cotizacion' => $this->cotizacion->token]),
            ],
        );
    }
}
