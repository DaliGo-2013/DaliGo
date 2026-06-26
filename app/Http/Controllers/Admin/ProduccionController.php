<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionRegistro;
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

        // Produccion del dia agrupada por maquina (primer entregable de
        // metricas por maquina; incluye reportes aun sin aprobar).
        $porMaquina = ProduccionRegistro::query()
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_registros.reporte_id')
            ->leftJoin('maquinas', 'maquinas.id', '=', 'produccion_registros.maquina_id')
            ->whereDate('produccion_reportes.fecha', $hoy)
            ->groupBy('produccion_registros.maquina_id', 'maquinas.nombre')
            ->orderByRaw('maquinas.nombre IS NULL, maquinas.nombre')
            ->selectRaw('maquinas.nombre AS maquina, SUM(produccion_registros.primera) AS primera, SUM(produccion_registros.segunda) AS segunda, SUM(produccion_registros.malo) AS malo')
            ->get();

        return view('admin.produccion.index', [
            'reportes' => $reportes,
            'resumen' => $resumen,
            'porMaquina' => $porMaquina,
        ]);
    }

    /**
     * Lista de sopladores para entrar a su historial. Incluye un agregado por
     * soplador (total de reportes y ultima fecha) en una sola query, sin N+1.
     */
    public function sopladores(): View
    {
        $sopladores = User::permission('report production')->orderBy('name')->get(['id', 'name']);

        $stats = ProduccionReporte::query()
            ->selectRaw('soplador_id, COUNT(*) AS total, MAX(fecha) AS ultima')
            ->groupBy('soplador_id')
            ->get()
            ->keyBy('soplador_id');

        return view('admin.produccion.sopladores', [
            'sopladores' => $sopladores,
            'stats' => $stats,
        ]);
    }

    /**
     * Historial de un soplador: que se le asigno y que produjo, dia por dia, en
     * el rango de fechas elegido (por defecto el mes actual). El detalle de un
     * dia reusa admin.produccion.reporte.show.
     */
    public function sopladorHistorial(Request $request, User $soplador): View
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now()->endOfMonth();

        $reportes = ProduccionReporte::with(['registros.maquina', 'registros.tipoBotellon', 'asignacion'])
            ->where('soplador_id', $soplador->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $totales = [
            'asignadas' => (int) $reportes->sum('asignadas'),
            'producido' => (int) $reportes->sum(fn (ProduccionReporte $r) => $r->total),
            'reportes' => $reportes->count(),
        ];

        return view('admin.produccion.soplador', [
            'soplador' => $soplador,
            'reportes' => $reportes,
            'totales' => $totales,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
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
        $reporte->load([
            'soplador',
            'revisadoPor',
            'registros' => fn ($query) => $query->latest('id'),
            'registros.maquina',
            'registros.tipoBotellon',
        ]);

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
     * Edicion del reporte por el admin: asignadas + cantidades producidas. A
     * diferencia de aprobar/devolver, se permite en CUALQUIER estado (el admin
     * tiene control total); queda registrado con su motivo y en auditoria. Si
     * se cambia asignadas, se sincroniza la asignacion (fuente de verdad) para
     * no dejar desfasado el snapshot del reporte.
     */
    public function ajustar(Request $request, ProduccionReporte $reporte): RedirectResponse
    {
        $validated = $request->validate([
            'asignadas' => ['required', 'integer', 'min:1'],
            'primera' => ['required', 'integer', 'min:0'],
            'segunda' => ['required', 'integer', 'min:0'],
            'malo' => ['required', 'integer', 'min:0'],
            'motivo_ajuste' => ['required', 'string', 'max:255'],
        ]);

        $reporte->update($validated);
        $reporte->asignacion?->update(['asignadas' => $validated['asignadas']]);

        return redirect()->route('admin.produccion.reporte.show', $reporte)
            ->with('status', 'Reporte actualizado.');
    }
}
