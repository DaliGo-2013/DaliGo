<?php

namespace App\Mail;

use App\Models\AgendaTrabajo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Aviso al cliente sobre su visita/trabajo de terreno coordinado por el vendedor:
 *  - 'agendada'    → confirmamos día/hora/técnico (con link para responder)
 *  - 'reprogramada'→ cambiamos la fecha (con link para responder de nuevo)
 *  - 'anulada'     → tuvimos que cancelar; te contactaremos (sin link)
 *
 * El link (agendada/reprogramada) va FIRMADO a la página donde el cliente
 * confirma que puede o avisa que no.
 */
class AgendaTrabajoAviso extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AgendaTrabajo $trabajo,
        public string $motivo = 'agendada', // agendada | reprogramada | anulada
    ) {}

    public function envelope(): Envelope
    {
        $asuntos = [
            'agendada' => 'Confirmación de tu visita — DaliGo',
            'reprogramada' => 'Cambio de fecha de tu visita — DaliGo',
            'anulada' => 'Visita cancelada — DaliGo',
        ];

        return new Envelope(subject: $asuntos[$this->motivo] ?? $asuntos['agendada']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.terreno.aviso',
            with: [
                'motivo' => $this->motivo,
                'urlConfirmar' => $this->motivo === 'anulada' || blank($this->trabajo->confirmacion_token)
                    ? null
                    : URL::signedRoute('confirmacion-visita.mostrar', ['token' => $this->trabajo->confirmacion_token]),
            ],
        );
    }
}
