<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja personal (campanita): cualquier usuario autenticado gestiona SUS
 * propias notificaciones in-app. No requiere permiso admin — es lo propio.
 */
class NotificacionUsuarioController extends Controller
{
    /**
     * "Mis notificaciones": lista las in-app (canal database) del usuario,
     * no-leídas primero. Pensada para el celular del operario (mobile-first).
     */
    public function index(Request $request): View
    {
        $notificaciones = Notificacion::query()
            ->where('user_id', $request->user()->id)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->orderByRaw("CASE estado WHEN '".Notificacion::ENVIADA."' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(20);

        return view('notificaciones.index', ['notificaciones' => $notificaciones]);
    }

    public function leer(Request $request, Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->user_id === $request->user()->id, 403);
        // Solo las in-app se "leen": marcar una mail/whatsapp fallida la sacaría
        // indebidamente del reintentador.
        abort_unless($notificacion->canal === Notificacion::CANAL_DATABASE, 404);

        $notificacion->marcarLeida();

        return back();
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        Notificacion::campanitaDe($request->user()->id)
            ->update(['estado' => Notificacion::LEIDA, 'leida_at' => now()]);

        return back();
    }
}
