<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Models\PreferenciaCanal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Preferencias de notificación por usuario (M15 · P-M15-07): opt-out por
 * evento × canal togglable. El canal `database` (campanita) es fijo — no se
 * puede desactivar; siempre queda la traza in-app.
 */
class NotificacionPreferenciaController extends Controller
{
    /** Canales que el usuario puede activar/desactivar (database es fijo). */
    private const CANALES_TOGGLABLES = [Notificacion::CANAL_MAIL, Notificacion::CANAL_WHATSAPP];

    public function update(Request $request): RedirectResponse
    {
        // Checkboxes marcados llegan como prefs[evento][canal]=1; los no marcados
        // no llegan → se interpretan como opt-out.
        $marcadas = $request->input('prefs', []);
        $user = $request->user();

        foreach (array_keys(Notificacion::EVENTOS) as $evento) {
            foreach (self::CANALES_TOGGLABLES as $canal) {
                // Acceso directo (NO data_get): el evento lleva un punto
                // ("sistema.prueba") y data_get lo interpretaría como anidación.
                $habilitado = (bool) ($marcadas[$evento][$canal] ?? false);

                PreferenciaCanal::updateOrCreate(
                    ['user_id' => $user->id, 'evento' => $evento, 'canal' => $canal],
                    ['habilitado' => $habilitado],
                );
            }
        }

        return redirect()->route('profile.edit')
            ->with('status', 'preferencias-notificaciones-actualizadas');
    }
}
