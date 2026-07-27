<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\AgendaTrabajo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Confirmación PÚBLICA del cliente a una visita/trabajo de terreno agendado
 * (link firmado del correo). El cliente ve día/hora/técnico y responde:
 * "Confirmo que puedo" o "No puedo ese día", con un comentario libre corto
 * (~150 palabras). Sin login: firma de la URL + token no enumerable + throttle
 * + honeypot. Primera respuesta manda; reprogramar la reabre (el vendedor
 * reenvía y se resetea). No cambia el estado operativo de la orden.
 */
class VisitaConfirmacionController extends Controller
{
    private function porToken(string $token): AgendaTrabajo
    {
        return AgendaTrabajo::where('confirmacion_token', $token)->firstOrFail();
    }

    public function mostrar(string $token): View
    {
        $trabajo = $this->porToken($token);

        return view('publico.confirmacion-visita.mostrar', [
            'trabajo' => $trabajo->load(['tecnico', 'servicio']),
            'urlResponder' => URL::signedRoute('confirmacion-visita.responder', ['token' => $token]),
        ]);
    }

    public function responder(Request $request, string $token): RedirectResponse
    {
        $trabajo = $this->porToken($token);

        // Honeypot: tratamiento silencioso como el resto del flujo público.
        if (filled($request->input('sitio_web'))) {
            return redirect()->to(URL::signedRoute('confirmacion-visita.mostrar', ['token' => $token]));
        }

        $data = $request->validate([
            'respuesta' => ['required', Rule::in(['confirmada', 'no_puede'])],
            // Texto libre corto (~150 palabras ≈ 1000 caracteres).
            'nota' => ['nullable', 'string', 'max:1000'],
        ]);

        // Primera respuesta gana: lock + recheck (patrón del taller).
        $notificar = DB::transaction(function () use ($trabajo, $data) {
            $fresco = AgendaTrabajo::whereKey($trabajo->id)->lockForUpdate()->first();

            if (! $fresco->esConfirmable()) {
                return false;
            }

            $fresco->forceFill([
                'cliente_confirmacion' => $data['respuesta'],
                'cliente_confirmacion_at' => now(),
                'cliente_confirmacion_nota' => $data['nota'] ?? null,
            ])->save();

            return true;
        });

        if ($notificar) {
            $trabajo->refresh()->avisarConfirmacionInterna();
        }

        return redirect()->to(URL::signedRoute('confirmacion-visita.gracias', ['token' => $token]));
    }

    public function gracias(string $token): View
    {
        return view('publico.confirmacion-visita.gracias', ['trabajo' => $this->porToken($token)]);
    }
}
