<?php

namespace App\Mail;

use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * «Tu equipo está listo, pásalo a retirar» (dueño 07-08). Lo manda el TÉCNICO
 * cuando termina: el taller no coordina plata, así que la carta dice el monto y
 * manda a pagar a SALA DE VENTAS al momento del retiro.
 *
 * El monto sale de la cotización que el cliente ACEPTÓ (snapshot), nunca de la
 * orden viva: se cobra lo que el cliente autorizó. Si es garantía —o si no hay
 * cotización aceptada— la carta no inventa un número: en garantía dice «sin
 * costo» y en el resto manda a coordinar el detalle en el mostrador.
 */
class EquipoListoParaRetiro extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrdenServicio $orden,
        public ?OrdenServicioCotizacion $cotizacion = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Tuteo, como el resto de los correos al cliente (ver la vista).
            subject: 'Tu equipo está listo para retirar — Orden '.$this->orden->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.taller.listo-para-retiro',
            with: [
                'esGarantia' => $this->orden->condicion_efectiva === 'garantia',
                // LA GARANTÍA DE LA REPARACIÓN (dueño 14-08-2026): tres meses desde la fecha de
                // reparación, SIN fechas de desde/hasta — «no le pongas la fecha desde hasta
                // cuándo, solo 3 meses y listo». Se probó con las dos fechas calculadas y le
                // pareció de más. Los métodos del modelo (`garantiaReparacionDesde/Vence`) quedan:
                // los usa la pantalla interna y no cuesta nada tenerlos bien definidos.
                'garantiaMeses' => OrdenServicio::GARANTIA_REPARACION_MESES,
            ],
        );
    }
}
