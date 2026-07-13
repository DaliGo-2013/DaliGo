<?php

namespace App\Http\Controllers;

use App\Models\Aprobacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use App\Services\Aprobaciones\Aprobaciones;
use App\Services\Aprobaciones\AprobacionYaResueltaException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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

    /**
     * Historial completo del motor (M14, P-M14-06; permiso `view aprobaciones`,
     * admin). Solo lectura: filtros por estado/tipo/solicitante/aprobador/rango
     * de fechas + resumen por estado y por aprobador/solicitante. Toda transicion
     * queda auditada (Aprobacion está en AuditController::MODELOS).
     */
    public function historial(Request $request): View
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(Aprobacion::ESTADOS)],
            'tipo_accion' => ['nullable', Rule::in(array_keys(Aprobacion::TIPOS_ACCION))],
            'solicitante_id' => ['nullable', 'integer', 'exists:users,id'],
            'resuelto_por' => ['nullable', 'integer', 'exists:users,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        // Fabrica de la query filtrada: cada llamada devuelve un builder fresco
        // (la lista pagina, el resumen agrega — no comparten estado).
        // whereDate sobre created_at (NUNCA whereBetween: la columna casteada
        // guarda hora y el borde superior se escapa — bitácora 2026-07-01/02).
        $filtrada = fn (): Builder => Aprobacion::query()
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['tipo_accion'] ?? null, fn ($q, $v) => $q->where('tipo_accion', $v))
            ->when($filtros['solicitante_id'] ?? null, fn ($q, $v) => $q->where('solicitante_id', $v))
            ->when($filtros['resuelto_por'] ?? null, fn ($q, $v) => $q->where('resuelto_por', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        $aprobaciones = $filtrada()
            ->with(['solicitante', 'resueltoPor'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $porEstado = $filtrada()->selectRaw('estado, COUNT(*) c')->groupBy('estado')->pluck('c', 'estado');

        return view('admin.aprobaciones.index', [
            'aprobaciones' => $aprobaciones,
            'porEstado' => $porEstado,
            'porSolicitante' => $this->agruparPorUsuario($filtrada(), 'solicitante_id'),
            'porAprobador' => $this->agruparPorUsuario($filtrada(), 'resuelto_por'),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'estados' => Aprobacion::ESTADOS,
            'tipos' => Aprobacion::TIPOS_ACCION,
            'filtros' => $filtros,
        ]);
    }

    /**
     * Agrupa las aprobaciones filtradas por una columna de usuario
     * (solicitante_id / resuelto_por) → [{nombre, c}], sin N+1 (nombres en un
     * solo pluck). Ignora los null (auto-aprobadas sin aprobador humano igual
     * llevan resuelto_por = solicitante, pero una fila sin usuario no suma).
     *
     * @return Collection<int, array{nombre: string, c: int}>
     */
    private function agruparPorUsuario(Builder $filtrada, string $columna): Collection
    {
        $filas = $filtrada->whereNotNull($columna)
            ->selectRaw("{$columna} as uid, COUNT(*) as c")
            ->groupBy($columna)
            ->orderByDesc('c')
            ->get();

        $nombres = User::whereIn('id', $filas->pluck('uid'))->pluck('name', 'id');

        return $filas->map(fn ($f) => ['nombre' => $nombres[$f->uid] ?? '—', 'c' => (int) $f->c]);
    }
}
