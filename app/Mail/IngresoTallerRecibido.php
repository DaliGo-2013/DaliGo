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
        $bodegaje = config('servicio_tecnico.bodegaje');

        return new Content(
            view: 'emails.taller.recibido',
            with: [
                // EL PLAZO SALE DE LA SUCURSAL, no escrito a mano: cada una tiene el suyo
                // (Mirador repara; las otras mandan el equipo a Mirador y por eso tardan más).
                // Con un número fijo, el mismo correo que muestra la entrega estimada se
                // contradiría solo. Sin sucursal —ingreso por ruta— no se promete plazo.
                'plazoDias' => $this->orden->sucursal?->dias_reparacion,
                // La MISMA constante que usa el correo de retiro: un solo número para las dos
                // cartas, así no puede prometerse una garantía al ingresar y otra al entregar.
                'garantiaMeses' => OrdenServicio::GARANTIA_REPARACION_MESES,
                'bodegajeDesdeMeses' => $bodegaje['desde_meses'],
                'bodegajeMensual' => '$'.number_format((int) $bodegaje['mensual_clp'], 0, ',', '.'),
                'bodegajeLimiteMeses' => $bodegaje['limite_meses'],
            ],
        );
    }
}
