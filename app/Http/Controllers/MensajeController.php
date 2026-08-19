<?php

namespace App\Http\Controllers;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\User;
use App\Services\Mensajes\Mensajeria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pantallas del chat interno (MSG-2, PLAN-MENSAJES §5.2). Molde de
 * NotificacionUsuarioController: superficie PERSONAL — el permiso
 * 'usar mensajes' gatea las rutas y adentro el gate es ser PARTICIPANTE
 * del hilo (403). Todo envio delega en Mensajeria (lock + rafaga).
 */
class MensajeController extends Controller
{
    /**
     * Lista de conversaciones: solo las MIAS, ordenadas por ultimo mensaje.
     * Sin paginacion a proposito (equipo de decenas: tope natural N-1 hilos).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversaciones = Conversacion::paraUsuario($user->id)
            ->with(['menor', 'mayor', 'ultimoMensaje'])
            ->orderByDesc('ultimo_mensaje_at')
            ->get();

        return view('mensajes.index', [
            'conversaciones' => $conversaciones,
            'usuario' => $user,
        ]);
    }

    /**
     * Nuevo mensaje: selector de destinatario + texto. El selector solo
     * ofrece usuarios que pueden usar el chat (mismo criterio del gate de
     * las rutas), excluyendome.
     */
    public function create(Request $request): View
    {
        $destinatarios = User::permission('usar mensajes')
            ->where('id', '!=', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('mensajes.create', ['destinatarios' => $destinatarios]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'destinatario_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$request->user()->id])],
            'texto' => ['required', 'string', 'max:'.Mensaje::TEXTO_MAX],
        ], [
            'destinatario_id.required' => 'Elige a quién escribirle.',
            'destinatario_id.exists' => 'Elige un destinatario válido.',
            'destinatario_id.not_in' => 'No puedes escribirte a ti mismo.',
            'texto.required' => 'Escribe el mensaje antes de enviarlo.',
            'texto.max' => 'El mensaje no puede superar los '.Mensaje::TEXTO_MAX.' caracteres.',
        ]);

        $mensaje = app(Mensajeria::class)->enviar(
            $request->user(),
            User::findOrFail($validated['destinatario_id']),
            $validated['texto'],
        );

        return redirect()->route('mensajes.show', $mensaje->conversacion_id)
            ->with('status', 'Mensaje enviado.');
    }

    /**
     * El hilo: historial paginado + composicion. Abrirlo marca leido (baja
     * MI contador — idempotente; diseño §5.2: sin speculation-rules ni
     * prefetch en esta app y el SW es passthrough, riesgo declarado).
     */
    public function show(Request $request, Conversacion $conversacion): View
    {
        $user = $request->user();

        abort_unless($conversacion->esParticipante($user), 403);

        $conversacion->marcarLeida($user);

        // Descendente + paginado: la pagina 1 trae lo mas reciente; la vista
        // la invierte para leer en orden cronologico.
        $mensajes = $conversacion->mensajes()
            ->with('emisor:id,name')
            ->latest('id')
            ->paginate(50);

        return view('mensajes.show', [
            'conversacion' => $conversacion,
            'mensajes' => $mensajes,
            'otro' => $conversacion->otroLado($user),
            'usuario' => $user,
        ]);
    }

    public function responder(Request $request, Conversacion $conversacion): RedirectResponse
    {
        $user = $request->user();

        abort_unless($conversacion->esParticipante($user), 403);

        $otro = $conversacion->otroLado($user);
        abort_unless($otro !== null, 404);

        $validated = $request->validate([
            'texto' => ['required', 'string', 'max:'.Mensaje::TEXTO_MAX],
        ], [
            'texto.required' => 'Escribe el mensaje antes de enviarlo.',
            'texto.max' => 'El mensaje no puede superar los '.Mensaje::TEXTO_MAX.' caracteres.',
        ]);

        app(Mensajeria::class)->enviar($user, $otro, $validated['texto']);

        return redirect()->route('mensajes.show', $conversacion)
            ->with('status', 'Mensaje enviado.');
    }
}
