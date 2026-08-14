<?php

namespace App\Services\Aprobaciones\Acciones;

use App\Models\AgendaTrabajo;
use App\Models\Aprobacion;
use App\Services\Aprobaciones\AccionAprobable;
use App\Services\Aprobaciones\ConflictoAccionException;

/**
 * Handler de la cita de terreno que fija un vendedor (tercer consumidor del motor).
 *
 * Pedido del dueño (13-08-2026): «cuando un vendedor fije una cita con un cliente por
 * mantención, reparación o instalación le tiene que llegar una notificación al jefe de ventas
 * para autorizar eso, que él siempre esté al tanto de lo que hacen sus vendedores». Y su
 * decisión sobre qué pasa mientras: **la cita queda en espera**, no ocupa la agenda.
 *
 * ────────────────────────────────────────────────────────────────────────────────────────
 * CÓMO SE VE UNA CITA «EN ESPERA», que es la decisión de diseño que más importa acá:
 *
 * El trabajo se crea con estado **'solicitado'** y la fecha pedida en `fecha_preferida` /
 * `hora_preferida`. No se inventó un estado nuevo: 'solicitado' + fecha preferida ya
 * significaba exactamente eso —algo que se quiere para un día pero todavía no está fijado— y
 * es lo que muestra el bloque «Por coordinar» de la agenda.
 *
 * La consecuencia importante es que `AgendaTrabajo::conflictos()` solo cuenta
 * agendado/realizado, así que una cita esperando autorización **no bloquea el día** ni para
 * otro vendedor ni para el formulario público. Es lo correcto: no está confirmada. Si dos
 * vendedores piden el mismo día, el jefe autoriza una y al aprobar la segunda el motor lanza
 * conflicto (ver abajo) en vez de dejar dos citas encima.
 * ────────────────────────────────────────────────────────────────────────────────────────
 *
 * Al aprobar, esto pone la fecha real, el técnico y el estado 'agendado' — y avisa al cliente,
 * que si no se enteraría por teléfono o no se enteraría.
 *
 * SI EL JEFE RECHAZA, el motor no llama a este handler: el trabajo se queda 'solicitado' con
 * su fecha preferida y el motivo del rechazo le llega al vendedor por «Mis solicitudes». Es a
 * propósito: la cita no se agenda, pero el registro queda para volver a coordinarla con el
 * cliente en vez de desaparecer.
 */
class CitaTerreno implements AccionAprobable
{
    public function aplicar(Aprobacion $aprobacion): void
    {
        // Lock propio sobre el agregado destino (mismo orden que los otros handlers:
        // aprobacion → objetivo).
        $trabajo = AgendaTrabajo::whereKey($aprobacion->aprobable_id)
            ->lockForUpdate()
            ->first();

        if ($trabajo === null) {
            throw new ConflictoAccionException('la cita de esta solicitud ya no existe.');
        }

        $snapshot = $aprobacion->datos['objetivo_updated_at'] ?? null;

        if ($snapshot !== null && $trabajo->updated_at?->toJSON() !== $snapshot) {
            throw new ConflictoAccionException(
                'La cita fue modificada después de la solicitud; vuelve a resolverla.',
            );
        }

        // Pudo resolverse por otro camino mientras esperaba (la cancelaron, o alguien la
        // agendó a mano): aplicar el payload viejo pisaría esa decisión.
        if ($trabajo->estado !== 'solicitado') {
            throw new ConflictoAccionException(
                "la cita ya no está esperando autorización (quedó {$trabajo->estado}).",
            );
        }

        $nuevo = $aprobacion->datos['nuevo'] ?? [];
        $fecha = $nuevo['fecha'] ?? null;

        if (blank($fecha)) {
            throw new ConflictoAccionException('la solicitud no trae fecha para la cita.');
        }

        // EL DÍA PUDO OCUPARSE MIENTRAS ESPERABA. Es el caso real de dos vendedores pidiendo
        // el mismo día: el jefe autoriza una y la otra tiene que rebotar con un motivo legible
        // en la bandeja, no quedar encimada. Se pregunta con la MISMA función que usa la
        // agenda para bloquear (`conflictos`), excluyendo esta cita.
        $hasta = $nuevo['fecha_fin'] ?? $fecha;
        $choque = AgendaTrabajo::conflictos((string) $fecha, (string) $hasta, $trabajo->id)->first();

        if ($choque !== null) {
            throw new ConflictoAccionException(
                "el técnico quedó ocupado esos días ({$choque->rango_fechas_label}) mientras la cita esperaba autorización.",
            );
        }

        $trabajo->update([
            'estado' => 'agendado',
            'fecha' => $fecha,
            'fecha_fin' => $nuevo['fecha_fin'] ?? null,
            'hora' => $nuevo['hora'] ?? null,
            'hora_fin' => $nuevo['hora_fin'] ?? null,
            'tecnico_id' => $nuevo['tecnico_id'] ?? null,
        ]);

        // El cliente se entera también por el camino DIFERIDO (cuando el jefe autoriza horas o
        // días después). Sin esto, la cita autorizada quedaba sin avisar a nadie.
        if (filled($trabajo->cliente_email)) {
            $trabajo->refresh()->avisarAlCliente('agendada');
        }

        // Y EL TÉCNICO, por el mismo motivo: acá la cita se le agenda horas o días después de
        // que el vendedor la pidió, así que es justo el camino donde el aviso hace más falta.
        // El estado previo era 'solicitado' (lo exige la guarda de arriba), o sea: para el
        // técnico esto es nuevo.
        $trabajo->refresh()->avisarAlTecnicoSiCorresponde([
            'estado' => 'solicitado', 'fecha' => null, 'hora' => null, 'tecnico_id' => null,
        ]);
    }
}
