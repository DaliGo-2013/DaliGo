<?php

namespace App\Mail;

use App\Models\OrdenServicioCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El cliente NO aceptó la cotización y el equipo le avisa que puede pasar a
 * retirar su máquina sin reparar (pedido del dueño 06-08). Carta cortés, sin
 * reproches y SIN montos — igual que SinSolucionCliente: si hay costo de
 * revisión lo define ventas caso a caso, no un correo automático. Deja la
 * puerta abierta: si cambia de opinión, la cotización se puede renovar.
 */
class RetiroSinReparacion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrdenServicioCotizacion $cotizacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Tuteo, como el resto de los correos al cliente (ver la vista).
            subject: 'Puedes pasar a retirar tu equipo — Orden '.$this->cotizacion->orden->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.taller.retiro-sin-reparacion',
        );
    }
}
