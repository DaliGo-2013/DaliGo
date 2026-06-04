<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProduccionController extends Controller
{
    private const TURNOS = ['dia', 'noche'];

    /**
     * Panel del jefe: resumen del dia + cola de reportes para revisar.
     */
    public function index(): View
    {
        $hoy = now()->toDateString();

        $reportes = ProduccionReporte::with('soplador')
            ->delDia($hoy)
            ->orderByRaw("CASE estado WHEN 'enviado' THEN 0 WHEN 'devuelto' THEN 1 WHEN 'borrador' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();

        $resumen = [
            'sopladores' => $reportes->pluck('soplador_id')->unique()->count(),
            'asignadas' => (int) ProduccionAsignacion::whereDate('fecha', $hoy)->sum('asignadas'),
            'pendientes' => $reportes->where('estado', ProduccionReporte::ENVIADO)->count(),
            'aprobados' => $reportes->where('estado', ProduccionReporte::APROBADO)->count(),
        ];

        return view('admin.produccion.index', [
            'reportes' => $reportes,
            'resumen' => $resumen,
        ]);
    }

    /**
     * Formulario para asignar produccion del dia a un soplador.
     */
    public function asignar(): View
    {
        return view('admin.produccion.asignar', [
            'sopladores' => User::permission('report production')->orderBy('name')->get(),
            'turnos' => self::TURNOS,
        ]);
    }

    /**
     * Crea (o actualiza) la asignacion del dia y deja un reporte en borrador.
     */
    public function asignarStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'soplador_id' => ['required', 'integer', 'exists:users,id'],
            'turno' => ['required', 'in:'.implode(',', self::TURNOS)],
            'fecha' => ['required', 'date'],
            'asignadas' => ['required', 'integer', 'min:1'],
        ]);

        // Buscamos con whereDate para normalizar la comparacion de fecha (cast a date)
        // tanto en SQLite (tests) como en MySQL (produccion).
        $asignacion = ProduccionAsignacion::whereDate('fecha', $validated['fecha'])
            ->where('soplador_id', $validated['soplador_id'])
            ->where('turno', $validated['turno'])
            ->first();

        if ($asignacion) {
            $asignacion->update([
                'asignadas' => $validated['asignadas'],
                'creado_por' => $request->user()->id,
            ]);
        } else {
            $asignacion = ProduccionAsignacion::create([
                'soplador_id' => $validated['soplador_id'],
                'fecha' => $validated['fecha'],
                'turno' => $validated['turno'],
                'asignadas' => $validated['asignadas'],
                'creado_por' => $request->user()->id,
            ]);
        }

        // Reporte en borrador asociado (idempotente). Mantiene el snapshot de asignadas al dia.
        $reporte = $asignacion->reporte()->firstOrNew([]);
        $reporte->fill([
            'soplador_id' => $asignacion->soplador_id,
            'fecha' => $asignacion->fecha->toDateString(),
            'turno' => $asignacion->turno,
            'asignadas' => $asignacion->asignadas,
        ]);
        if (! $reporte->exists) {
            $reporte->estado = ProduccionReporte::BORRADOR;
        }
        $reporte->save();

        return redirect()->route('admin.produccion.index')
            ->with('status', "Asignacion guardada para {$asignacion->soplador->name} ({$asignacion->asignadas} preformas).");
    }

    /**
     * Detalle de un reporte para revisar.
     */
    public function reporteShow(ProduccionReporte $reporte): View
    {
        $reporte->load('soplador', 'revisadoPor');

        return view('admin.produccion.reporte', ['reporte' => $reporte]);
    }

    /**
     * Aprueba un reporte enviado.
     */
    public function aprobar(ProduccionReporte $reporte): RedirectResponse
    {
        if (! $reporte->esPendienteDeRevision()) {
            return back()->with('status', 'Solo se pueden aprobar reportes enviados.');
        }

        $reporte->update([
            'estado' => ProduccionReporte::APROBADO,
            'revisado_por' => request()->user()->id,
            'revisado_at' => now(),
        ]);

        return redirect()->route('admin.produccion.index')
            ->with('status', "Reporte de {$reporte->soplador->name} aprobado.");
    }

    /**
     * Devuelve un reporte al soplador para que lo corrija.
     */
    public function devolver(Request $request, ProduccionReporte $reporte): RedirectResponse
    {
        if (! $reporte->esPendienteDeRevision()) {
            return back()->with('status', 'Solo se pueden devolver reportes enviados.');
        }

        $validated = $request->validate([
            'devuelto_motivo' => ['required', 'string', 'max:255'],
        ]);

        $reporte->update([
            'estado' => ProduccionReporte::DEVUELTO,
            'devuelto_motivo' => $validated['devuelto_motivo'],
            'revisado_por' => $request->user()->id,
            'revisado_at' => now(),
        ]);

        return redirect()->route('admin.produccion.index')
            ->with('status', "Reporte de {$reporte->soplador->name} devuelto.");
    }

    /**
     * Ajuste de cantidades por el jefe (queda registrado con su motivo).
     */
    public function ajustar(Request $request, ProduccionReporte $reporte): RedirectResponse
    {
        if (! $reporte->esPendienteDeRevision()) {
            return back()->with('status', 'Solo se pueden ajustar reportes enviados.');
        }

        $validated = $request->validate([
            'primera' => ['required', 'integer', 'min:0'],
            'segunda' => ['required', 'integer', 'min:0'],
            'malo' => ['required', 'integer', 'min:0'],
            'motivo_ajuste' => ['required', 'string', 'max:255'],
        ]);

        $reporte->update($validated);

        return redirect()->route('admin.produccion.reporte.show', $reporte)
            ->with('status', 'Cantidades ajustadas.');
    }
}
