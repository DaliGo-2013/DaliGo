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
                // EL PLAZO SALE DE LA SUCURSAL, no escrito a mano: Mirador repara en 10 días
                // hábiles; Coquimbo y Abate Molina mandan el equipo a Mirador y por eso son 15.
                // Es el ÚNICO compromiso de tiempo del correo: la fecha de entrega calculada
                // ya no viaja (dueño 14-08-2026) porque una fecha prometida que el taller no
                // cumple —técnico enfermo, de vacaciones— termina en reclamo. Sin sucursal
                // —ingreso por ruta— no se promete nada.
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
