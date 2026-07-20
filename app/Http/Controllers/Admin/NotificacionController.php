<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Panel de notificaciones (solo lectura + botón de prueba). Muestra TODAS las
 * notificaciones del motor M15, filtrables por estado/canal/evento — permiso
 * `view notificaciones`. La bandeja personal (campanita) es aparte (NotificacionUsuarioController).
 */
class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(Notificacion::ESTADOS)],
            'canal' => ['nullable', Rule::in(Notificacion::CANALES)],
            'evento' => ['nullable', Rule::in(array_keys(Notificacion::EVENTOS))],
        ]);

        $notificaciones = Notificacion::with('user')
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['canal'] ?? null, fn ($q, $v) => $q->where('canal', $v))
            ->when($filtros['evento'] ?? null, fn ($q, $v) => $q->where('evento', $v))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.notificaciones.index', [
            'notificaciones' => $notificaciones,
            'estados' => Notificacion::ESTADOS,
            'canales' => Notificacion::CANALES,
            'eventos' => Notificacion::EVENTOS,
            'filtros' => $filtros,
        ]);
    }

    /**
     * Dispara el evento de prueba al usuario actual (verifica el motor end-to-end
     * desde la UI: crea las notificaciones y las encola).
     */
    public function prueba(Request $request, NotificacionDispatcher $dispatcher): RedirectResponse
    {
        $dispatcher->despachar('sistema.prueba', null, $request->user(), [
            'nombre' => $request->user()->name,
            'fecha' => now()->enChile()->format('d-m-Y H:i'),
        ]);

        return redirect()->route('admin.notificaciones.index')
            ->with('status', 'Notificación de prueba encolada. Revisa la campanita y tu correo.');
    }
}
