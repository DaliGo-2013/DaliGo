<?php

namespace App\Mail;

use App\Models\OrdenServicio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al cliente de que su equipo NO tuvo arreglo (decision del dueño 30-07).
 *
 * Sale automatico al cerrar la orden como 'sin_solucion', junto con el aviso
 * interno a ventas. Tuteo, como todo el flujo al cliente.
 *
 * Lo que este correo NO dice, a proposito: el DIAGNOSTICO del tecnico. La causa
 * de la falla ('Mal uso del cliente' / 'Desgaste por uso normal' / 'Falla de
 * fabrica') va en el aviso INTERNO, no en este. Dos razones: leer «mal uso del
 * cliente» en un correo automatico es una acusacion sin nadie que la explique, y
 * «falla de fabrica» abre una conversacion de garantia o reemplazo que la tiene
 * ventas por telefono — no un correo. El correo avisa y ofrece coordinar; el
 * resto es una llamada.
 *
 * Tampoco lleva montos: una orden sin solucion puede tener costo de revision, y
 * eso lo define ventas caso a caso.
 */
class SinSolucionCliente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrdenServicio $orden) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Sin la palabra «solución» en el asunto: al cliente no le dice nada.
            subject: 'Sobre la revisión de tu equipo — Orden '.$this->orden->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.taller.sin-solucion',
        );
    }
}
