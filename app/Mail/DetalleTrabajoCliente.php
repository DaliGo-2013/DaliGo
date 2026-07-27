<?php

namespace App\Mail;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Garantía: detalle del trabajo realizado al cliente, SIN cobro. Es el
 * equivalente a la cotización cuando el equipo está en garantía vigente: el
 * cliente no paga, solo recibe el resumen de lo que se hizo (trabajo, causa y
 * repuestos usados, sin precios). No lleva link de respuesta.
 */
class DetalleTrabajoCliente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrdenServicio $orden) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Detalle de su servicio (garantía) — Orden '.$this->orden->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.taller.detalle-trabajo',
        );
    }
}
