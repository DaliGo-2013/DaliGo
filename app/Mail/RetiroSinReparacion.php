<?php

namespace App\Mail;

use App\Models\OrdenServicioCotizacion;
use App\Support\DiasHabiles;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * El cliente NO aceptó la cotización y se le cita a retirar su máquina sin
 * reparar el DÍA HÁBIL SIGUIENTE (dueño 07-08: sale AUTOMÁTICO al momento del
 * rechazo — el técnico no envía nada — para que los equipos no se acumulen en
 * el taller; el botón manual quedó de respaldo si este correo falla).
 *
 * Carta cortés, sin reproches y SIN montos — igual que SinSolucionCliente: si
 * hay costo de revisión lo define ventas caso a caso, no un correo automático.
 * Estilo banco: generada automáticamente, SIN invitación a responder (decisión
 * del dueño 07-08 — antes decía «si cambias de opinión, responde este correo»).
 */
class RetiroSinReparacion extends Mailable
{
    use Queueable, SerializesModels;

    public Carbon $retiroDesde;

    public function __construct(public OrdenServicioCotizacion $cotizacion, ?Carbon $retiroDesde = null)
    {
        $this->retiroDesde = $retiroDesde ?? DiasHabiles::siguiente();
    }

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
