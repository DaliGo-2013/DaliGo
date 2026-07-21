<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\OrdenServicioCotizacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Respuesta PÚBLICA del cliente a una cotización del taller (P-M12-02): abre el
 * link firmado del correo, ve la carta (desde el SNAPSHOT, nunca la orden viva)
 * y responde solo ACEPTO / NO ACEPTO — sin campo de comentario (decisión del
 * dueño: evitar el ida y vuelta). Sin login: la seguridad es la firma de la URL
 * (GET y POST) + token no enumerable + throttle + honeypot, como el flujo QR.
 *
 * La respuesta NO cambia el estado de la orden: se registra y se avisa a los
 * roles internos (después del commit: si el aviso falla, la respuesta ya quedó).
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
        ]);

        // Primera respuesta gana: lock + recheck dentro de la transacción para
        // absorber doble clic o dos pestañas (patrón confirmar() del taller).
        $notificar = DB::transaction(function () use ($cotizacion, $data, $request) {
            $fresca = OrdenServicioCotizacion::whereKey($cotizacion->id)->lockForUpdate()->first();

            if (! $fresca->esRespondible()) {
                return false;
            }

            $fresca->update([
                'estado' => $data['respuesta'],
                'respondida_at' => now(),
                'respuesta_ip' => (string) $request->ip(),
                'respuesta_user_agent' => (string) $request->userAgent(),
            ]);

            return true;
        });

        if ($notificar) {
            $cotizacion->refresh()->avisarInternos('cotizacion.respondida', [
                'respuesta' => $data['respuesta'] === 'aceptada' ? 'ACEPTADA' : 'NO ACEPTADA',
            ]);
        }

        return redirect()->to(URL::signedRoute('cotizacion.gracias', ['cotizacion' => $cotizacion->token]));
    }

    public function gracias(OrdenServicioCotizacion $cotizacion): View
    {
        return view('publico.cotizacion.gracias', ['cotizacion' => $cotizacion->load('orden')]);
    }
}
