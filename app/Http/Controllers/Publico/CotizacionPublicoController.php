<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Mail\RetiroSinReparacion;
use App\Models\OrdenServicioCotizacion;
use App\Support\DiasHabiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Respuesta PÚBLICA del cliente a una cotización del taller (P-M12-02): abre el
 * link firmado del correo, ve la carta (desde el SNAPSHOT, nunca la orden viva)
 * y responde ACEPTO / NO ACEPTO con un «¿por qué?» opcional. OJO: el 30-07 el
 * dueño había pedido sin comentario; el 06-08 dio vuelta esa decisión — quiere
 * leer el motivo en la campanita. Sigue siendo UNA pasada, no una conversación.
 * Sin login: la seguridad es la firma de la URL (GET y POST) + token no
 * enumerable + throttle + honeypot, como el flujo QR.
 *
 * La respuesta NO cambia el estado de la orden: se registra y se avisa a los
 * roles internos (después del commit: si el aviso falla, la respuesta ya quedó).
 *
 * Si la respuesta es NO ACEPTO, en el mismo momento sale SOLO el correo que
 * cita al cliente a retirar su equipo el día hábil siguiente (dueño 07-08: el
 * técnico no envía nada, solo recibe la campanita de que el ciclo se cerró).
 */
class CotizacionPublicoController extends Controller
{
    public function mostrar(OrdenServicioCotizacion $cotizacion): View
    {
        return view('publico.cotizacion.mostrar', [
            'cotizacion' => $cotizacion->load('orden'),
            'urlRespuesta' => URL::signedRoute('cotizacion.responder', ['cotizacion' => $cotizacion->token]),
        ]);
    }

    public function responder(Request $request, OrdenServicioCotizacion $cotizacion): RedirectResponse
    {
        // Honeypot: mismo tratamiento silencioso que el resto del flujo público.
        if (filled($request->input('sitio_web'))) {
            return redirect()->to(URL::signedRoute('cotizacion.mostrar', ['cotizacion' => $cotizacion->token]));
        }

        $data = $request->validate([
            'respuesta' => ['required', Rule::in(['aceptada', 'rechazada'])],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);
        $motivo = trim((string) ($data['motivo'] ?? '')) ?: null;

        // Primera respuesta gana: lock + recheck dentro de la transacción para
        // absorber doble clic o dos pestañas (patrón confirmar() del taller).
        $notificar = DB::transaction(function () use ($cotizacion, $data, $motivo, $request) {
            $fresca = OrdenServicioCotizacion::whereKey($cotizacion->id)->lockForUpdate()->first();

            if (! $fresca->esRespondible()) {
                return false;
            }

            $fresca->update([
                'estado' => $data['respuesta'],
                'respondida_at' => now(),
                'respuesta_ip' => (string) $request->ip(),
                'respuesta_user_agent' => (string) $request->userAgent(),
                'respuesta_motivo' => $motivo,
            ]);

            return true;
        });

        if ($notificar) {
            $cotizacion->refresh()->avisarInternos('cotizacion.respondida', [
                'respuesta' => $data['respuesta'] === 'aceptada' ? 'ACEPTADA' : 'NO ACEPTADA',
                // {motivo} SIEMPRE relleno: un placeholder sin dato queda crudo
                // en la campanita (regla de avisarInternos).
                'motivo' => $motivo !== null ? 'Motivo del cliente: «'.$motivo.'»' : 'El cliente no indicó el motivo.',
            ]);

            if ($data['respuesta'] === 'rechazada') {
                $this->citarRetiroAutomatico($cotizacion);
            }
        }

        return redirect()->to(URL::signedRoute('cotizacion.gracias', ['cotizacion' => $cotizacion->token]));
    }

    /**
     * NO ACEPTO → sale al tiro la cita de retiro para el día hábil siguiente
     * (dueño 07-08: automático, sin pasar por el técnico, para que los equipos
     * no se acumulen). Al taller le llega la campanita del ciclo cerrado.
     *
     * Si el correo falla, NO se estampa nada: la ficha de la orden sigue
     * ofreciendo el botón manual como respaldo. Y nunca tumba la respuesta del
     * cliente, que ya quedó registrada (por eso corre después del commit).
     */
    private function citarRetiroAutomatico(OrdenServicioCotizacion $cotizacion): void
    {
        if (blank($cotizacion->cliente_email)) {
            return; // sin correo no hay cita: al cliente lo llama ventas.
        }

        $retiro = DiasHabiles::siguiente();

        try {
            Mail::to($cotizacion->cliente_email)->send(new RetiroSinReparacion($cotizacion->load('orden'), $retiro));
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        $cotizacion->update([
            'retiro_avisado_at' => now(),
            'retiro_avisado_por' => null, // null = lo mandó el sistema, no una persona
        ]);

        $cotizacion->avisarInternos('cotizacion.retiro_avisado', [
            'retiro_dia' => DiasHabiles::rotulo($retiro),
            'avisado_por' => 'el sistema, automáticamente al registrarse el rechazo',
        ]);
    }

    public function gracias(OrdenServicioCotizacion $cotizacion): View
    {
        return view('publico.cotizacion.gracias', ['cotizacion' => $cotizacion->load('orden')]);
    }
}
