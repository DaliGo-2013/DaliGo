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
 * y responde ACEPTO / NO ACEPTO con un «¿por qué?» OBLIGATORIO. Historia de esa
 * decisión, porque dio dos vueltas: el 30-07 el dueño pidió sin comentario; el
 * 06-08 lo agregó como opcional para leer el motivo en la campanita; el 14-08 lo
 * hizo obligatorio en las DOS respuestas. Sigue siendo UNA pasada, no una
 * conversación: se responde una vez y se cierra.
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

        // EL «¿POR QUÉ?» ES OBLIGATORIO EN LAS DOS RESPUESTAS (dueño 14-08-2026),
        // no solo en el rechazo: un ACEPTO también trae información que el equipo
        // necesita («autorizo, pero factúrenlo a nombre de la empresa»), y pedirle
        // explicaciones solo a quien dice no se lee como un interrogatorio.
        //
        // `required` ALCANZA, incluido el caso del textarea con solo espacios: la
        // regla de Laravel trimea los strings antes de decidir, así que '   ' rebota
        // igual que '' y que el campo ausente. Acá hubo un intento de normalizar el
        // valor con un `merge(trim(...))` ANTES de validar «por si acaso»: se quitó
        // al comprobar por mutación que quitándolo NINGÚN test cambiaba de color, o
        // sea que no hacía nada. El trim que sí importa es el de abajo, y es para
        // GUARDAR limpio, no para validar.
        $data = $request->validate([
            'respuesta' => ['required', Rule::in(['aceptada', 'rechazada'])],
            'motivo' => ['required', 'string', 'max:1000'],
        ], [
            // Al cliente le hablamos como cliente: el mensaje por defecto de Laravel
            // («El campo motivo es obligatorio») no le dice qué hacer.
            'motivo.required' => 'Cuéntanos brevemente el motivo de tu decisión: es obligatorio para registrar tu respuesta.',
        ]);
        $motivo = trim((string) $data['motivo']) ?: null;

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
            // Reparto POR CARTERA (dueño 07-08): la respuesta del cliente es la
            // noticia comercial de la orden, así que va al vendedor de ESE cliente
            // —no a los nueve— y si el cliente todavía no tiene vendedor asignado
            // cae en sala de ventas, que lo monitorea. El técnico y jefatura la
            // reciben igual porque tienen 'ver todo servicio tecnico': para el taller
            // un ACEPTO es su luz verde para reparar.
            $cotizacion->refresh()->avisarInternos('cotizacion.respondida', [
                'respuesta' => $data['respuesta'] === 'aceptada' ? 'ACEPTADA' : 'NO ACEPTADA',
                // {motivo} SIEMPRE relleno: un placeholder sin dato queda crudo
                // en la campanita (regla de avisarInternos). Desde el 14-08 el
                // motivo es obligatorio, así que la segunda rama es una red por si
                // la regla se relaja — no un caso que hoy pueda ocurrir.
                'motivo' => $motivo !== null ? 'Motivo del cliente: «'.$motivo.'»' : 'El cliente no indicó el motivo.',
            ], porCartera: true);

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
