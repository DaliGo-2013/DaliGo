<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bandeja personal (campanita): cualquier usuario autenticado gestiona SUS
 * propias notificaciones in-app. No requiere permiso admin — es lo propio.
 */
class NotificacionUsuarioController extends Controller
{
    public function leer(Request $request, Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->user_id === $request->user()->id, 403);

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
