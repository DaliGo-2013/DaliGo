<?php

namespace App\Http\Controllers;

use App\Models\Aprobacion;
use App\Models\ProduccionReporte;
use App\Services\Aprobaciones\Aprobaciones;
use App\Services\Aprobaciones\AprobacionYaResueltaException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja movil del aprobador (M14, P-M14-03) + "mis solicitudes" del
 * solicitante. El aprobador usa el celular: resolver toma <=2 taps y el
 * doble-tap lo absorbe el lock del servicio (el segundo recibe un flash
 * "ya fue resuelta", jamas doble aplicacion).
 */
class AprobacionController extends Controller
{
    /**
     * Pendientes del rol VIGENTE del usuario (tras escalar, la solicitud
     * aparece en la bandeja del rol nuevo). El admin ve TODAS las pendientes
     * (puede resolver cualquiera — PLAN-M14 §1.3, deliberado y auditado).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $pendientes = Aprobacion::where('estado', Aprobacion::ESTADO_PENDIENTE)
            ->when(
                ! $user->hasRole('admin'),
                fn ($q) => $q->whereIn('rol_aprobador', $user->getRoleNames()),
            )
            ->with(['solicitante', 'aprobable'])
            ->oldest() // las mas antiguas primero: son las que mas urgen
            ->get();

        return view('aprobaciones.index', ['pendientes' => $pendientes]);
    }

    public function aprobar(Request $request, Aprobacion $aprobacion): RedirectResponse
    {
        try {
            $resuelta = app(Aprobaciones::class)->aprobar($aprobacion, $request->user());
        } catch (AprobacionYaResueltaException) {
            return redirect()->route('aprobaciones.index')
                ->with('status', 'Esa solicitud ya fue resuelta.');
        }

        // El handler pudo rechazarla solo (conflicto: el objetivo cambio
        // despues de la solicitud) — avisar con la verdad, no con "aprobada".
        $mensaje = $resuelta->estado === Aprobacion::ESTADO_APROBADA
            ? 'Solicitud aprobada y aplicada.'
            : 'No se pudo aplicar: '.$resuelta->resultado_motivo;

        return redirect()->route('aprobaciones.index')->with('status', $mensaje);
    }

    public function rechazar(Request $request, Aprobacion $aprobacion): RedirectResponse
    {
        // El chip "Otro" viaja como centinela y el texto en motivo_otro
        // (mismo idioma que MiProduccionController): resolver ANTES de validar.
        if ($request->input('motivo') === ProduccionReporte::MOTIVO_OTRO) {
            $request->merge(['motivo' => trim((string) $request->input('motivo_otro')) ?: null]);
        }

        $validated = $request->validate(
            ['motivo' => ['required', 'string', 'max:255']],
            ['motivo.required' => 'Elige o escribe el motivo del rechazo.'],
        );

        try {
            app(Aprobaciones::class)->rechazar($aprobacion, $request->user(), $validated['motivo']);
        } catch (AprobacionYaResueltaException) {
            return redirect()->route('aprobaciones.index')
                ->with('status', 'Esa solicitud ya fue resuelta.');
        }

        return redirect()->route('aprobaciones.index')->with('status', 'Solicitud rechazada.');
    }

    /** Historial personal del solicitante (cualquier usuario ve LO SUYO). */
    public function mias(Request $request): View
    {
        $solicitudes = Aprobacion::where('solicitante_id', $request->user()->id)
            ->latest()
            ->take(50)
            ->get();

        return view('aprobaciones.mias', ['solicitudes' => $solicitudes]);
    }
}
