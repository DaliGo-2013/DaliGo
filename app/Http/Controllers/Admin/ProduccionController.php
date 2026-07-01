<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionMovimiento;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProduccionController extends Controller
{
    private const TURNOS = ['dia', 'noche'];

    /**
     * Panel del jefe: alertas de accion + resumen de hoy + produccion por
     * periodo (rango, default ultimos 7 dias) + por maquina y cola de HOY.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $hoy = now()->toDateString();

        // --- Cola de reportes de HOY (la superficie de trabajo del jefe) ---
        $reportes = ProduccionReporte::with('soplador')
            ->withCount('registros')
            ->delDia($hoy)
            ->orderByRaw("CASE estado WHEN 'enviado' THEN 0 WHEN 'devuelto' THEN 1 WHEN 'borrador' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();

        // --- Requiere atencion (independiente del rango: son senales de "ahora") ---
        $alertas = [
            'porAprobar' => ProduccionReporte::pendientes()->count(),
            'devueltos' => ProduccionReporte::where('estado', ProduccionReporte::DEVUELTO)->count(),
            // Asignaciones de hoy sin enviar todavia (reporte en borrador o inexistente).
            'atrasados' => ProduccionAsignacion::whereDate('fecha', $hoy)
                ->where(function ($q) {
                    $q->doesntHave('reporte')
                        ->orWhereHas('reporte', fn ($r) => $r->where('estado', ProduccionReporte::BORRADOR));
                })
                ->count(),
        ];

        // --- Resumen de HOY (ampliado) ---
        $t = ProduccionReporte::delDia($hoy)
            ->selectRaw('COALESCE(SUM(primera),0) p1, COALESCE(SUM(segunda),0) p2, COALESCE(SUM(malo),0) mal, COALESCE(SUM(danada),0) dan')
            ->first();
        $hoyResumen = $this->armarResumen(
            (int) $t->p1, (int) $t->p2, (int) $t->mal, (int) $t->dan,
            (int) ProduccionAsignacion::whereDate('fecha', $hoy)->sum('asignadas'),
        );
        $hoyResumen['sopladores'] = $reportes->pluck('soplador_id')->unique()->count();

        // --- Produccion por periodo (rango; default ultimos 7 dias) ---
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->toDateString() : $hoy;
        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->toDateString() : Carbon::parse($hasta)->subDays(6)->toDateString();
        if ($desde > $hasta) {
            $desde = $hasta;
        }
        // Cota de seguridad: la tabla por dia se arma en PHP; no mas de ~92 filas.
        if (Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) > 92) {
            $desde = Carbon::parse($hasta)->subDays(92)->toDateString();
        }

        // whereDate en ambos extremos: la columna 'fecha' (cast a date) puede
        // traer hora 00:00:00 y romper un whereBetween por string cuando desde==hasta.
        $porDia = ProduccionReporte::whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)
            ->selectRaw('fecha, COALESCE(SUM(primera),0) p1, COALESCE(SUM(segunda),0) p2, COALESCE(SUM(malo),0) mal, COALESCE(SUM(danada),0) dan, COUNT(*) reportes')
            ->groupBy('fecha')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->fecha)->toDateString());

        $asignadasPorDia = ProduccionAsignacion::whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)
            ->selectRaw('fecha, COALESCE(SUM(asignadas),0) a')
            ->groupBy('fecha')
            ->get()
            ->mapWithKeys(fn ($r) => [Carbon::parse($r->fecha)->toDateString() => (int) $r->a]);

        $dias = [];
        [$sp1, $sp2, $smal, $sdan, $sasig, $srep] = [0, 0, 0, 0, 0, 0];
        for ($cursor = Carbon::parse($desde); $cursor->lte(Carbon::parse($hasta)); $cursor->addDay()) {
            $k = $cursor->toDateString();
            $r = $porDia->get($k);
            $p1 = (int) ($r->p1 ?? 0);
            $p2 = (int) ($r->p2 ?? 0);
            $mal = (int) ($r->mal ?? 0);
            $dan = (int) ($r->dan ?? 0);
            $asig = (int) ($asignadasPorDia[$k] ?? 0);
            $rep = (int) ($r->reportes ?? 0);

            $dias[] = $this->armarResumen($p1, $p2, $mal, $dan, $asig) + [
                'fecha' => $cursor->copy(),
                'reportes' => $rep,
            ];
            $sp1 += $p1;
            $sp2 += $p2;
            $smal += $mal;
            $sdan += $dan;
            $sasig += $asig;
            $srep += $rep;
        }

        $periodo = [
            'desde' => $desde,
            'hasta' => $hasta,
            'esDefault' => ! $request->filled('desde') && ! $request->filled('hasta'),
            'dias' => $dias,
            'totales' => $this->armarResumen($sp1, $sp2, $smal, $sdan, $sasig) + ['reportes' => $srep],
            'maxProducido' => max(1, collect($dias)->max('producido') ?? 0),
        ];

        // --- Por maquina de HOY (rollup operativo desde las tandas) ---
        $porMaquina = ProduccionRegistro::query()
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_registros.reporte_id')
            ->leftJoin('maquinas', 'maquinas.id', '=', 'produccion_registros.maquina_id')
            ->leftJoin('sucursales', 'sucursales.id', '=', 'maquinas.sucursal_id')
            ->whereDate('produccion_reportes.fecha', $hoy)
            ->groupBy('produccion_registros.maquina_id', 'maquinas.nombre', 'sucursales.nombre')
            ->orderByRaw('maquinas.nombre IS NULL, maquinas.nombre')
            ->selectRaw('maquinas.nombre AS maquina, sucursales.nombre AS sucursal, SUM(produccion_registros.primera) AS primera, SUM(produccion_registros.segunda) AS segunda, SUM(produccion_registros.malo) AS malo, SUM(produccion_registros.danada) AS danada')
            ->get();

        // Solo desambiguar con sucursal si el dia mezcla varias (evita ruido mono-sucursal).
        $porMaquinaMultiSucursal = $porMaquina->whereNotNull('sucursal')->pluck('sucursal')->unique()->count() > 1;

        return view('admin.produccion.index', [
            'reportes' => $reportes,
            'alertas' => $alertas,
            'hoy' => $hoyResumen,
            'periodo' => $periodo,
            'porMaquina' => $porMaquina,
            'porMaquinaMultiSucursal' => $porMaquinaMultiSucursal,
        ]);
    }

    /**
     * Arma un resumen de produccion (producido/merma/tasas/avance) a partir de
     * las 4 cantidades y lo asignado. Fuente unica para hoy, cada dia y el total
     * del rango, asi todos calculan igual.
     */
    private function armarResumen(int $p1, int $p2, int $mal, int $dan, int $asignadas): array
    {
        $producido = $p1 + $p2;
        $merma = $mal + $dan;
        $total = $producido + $merma;

        return [
            'asignadas' => $asignadas,
            'producido' => $producido,
            'merma' => $merma,
            'total' => $total,
            'merma_pct' => $total > 0 ? (int) round($merma / $total * 100) : 0,
            'tasa1' => $total > 0 ? (int) round($p1 / $total * 100) : 0,
            'avance' => $asignadas > 0 ? (int) round($producido / $asignadas * 100) : 0,
        ];
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
            // Producido = vendible (1a + 2a); consumido y merma se muestran aparte
            // para no inflar la productividad con preformas perdidas.
            'producido' => (int) $reportes->sum(fn (ProduccionReporte $r) => $r->producido),
            'consumido' => (int) $reportes->sum(fn (ProduccionReporte $r) => $r->total),
            'merma' => (int) $reportes->sum(fn (ProduccionReporte $r) => $r->merma),
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
            'preformas' => $this->preformasParaSelector(),
        ]);
    }

    /**
     * Productos elegibles como preforma del turno: activos cuya categoria
     * menciona "preforma". Fallback: todos los activos (para no bloquear si la
     * categorizacion del catalogo aun no distingue preformas).
     */
    private function preformasParaSelector()
    {
        $preformas = Producto::query()->where('activo', true)
            ->where('categoria', 'like', '%preforma%')
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);

        if ($preformas->isNotEmpty()) {
            return $preformas;
        }

        return Producto::query()->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);
    }

    /**
     * Crea una produccion NUEVA del dia para un soplador (asignacion + reporte en
     * borrador). Un soplador puede tener varias producciones el mismo dia/turno:
     * cada "Asignar" crea una independiente y NO toca las existentes (un reporte
     * ya enviado/aprobado queda intacto). Para deshacer una asignacion equivocada,
     * destroyReporte borra los borradores sin tandas.
     */
    public function asignarStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'soplador_id' => ['required', 'integer', 'exists:users,id'],
            'turno' => ['required', 'in:'.implode(',', self::TURNOS)],
            'fecha' => ['required', 'date'],
            'asignadas' => ['required', 'integer', 'min:1'],
            // Preforma del turno (producto del catalogo). Opcional: si no se
            // elige, el consumo del kardex queda sin enlace a producto. Se
            // restringe a productos ACTIVOS (mismo universo que el selector;
            // un id inactivo o de otra categoria no debe entrar al kardex).
            'preforma_id' => ['nullable', 'integer', Rule::exists('productos', 'id')->where('activo', true)],
        ]);

        // Asignacion + reporte en una sola transaccion: si el reporte falla, no
        // queda una asignacion huerfana (el soplador veria "sin asignacion").
        $asignacion = DB::transaction(function () use ($validated, $request) {
            $asignacion = ProduccionAsignacion::create([
                'soplador_id' => $validated['soplador_id'],
                'fecha' => $validated['fecha'],
                'turno' => $validated['turno'],
                'asignadas' => $validated['asignadas'],
                'preforma_id' => $validated['preforma_id'] ?? null,
                'creado_por' => $request->user()->id,
            ]);

            // Reporte en borrador con el snapshot de asignadas del dia.
            $asignacion->reporte()->create([
                'soplador_id' => $asignacion->soplador_id,
                'fecha' => $asignacion->fecha->toDateString(),
                'turno' => $asignacion->turno,
                'asignadas' => $asignacion->asignadas,
                'estado' => ProduccionReporte::BORRADOR,
            ]);

            return $asignacion;
        });

        return redirect()->route('admin.produccion.index')
            ->with('status', "Asignacion guardada para {$asignacion->soplador->name} ({$asignacion->asignadas} preformas).");
    }

    /**
     * Elimina una produccion (asignacion + su reporte) que se asigno por error.
     * Solo si el reporte sigue en BORRADOR y SIN tandas: asi nunca se borra algo
     * que ya movio inventario (el kardex se genera al aprobar) ni trabajo del
     * soplador. Borrar la asignacion cascadea el reporte (FK cascadeOnDelete).
     */
    public function destroyReporte(ProduccionReporte $reporte): RedirectResponse
    {
        if ($reporte->estado !== ProduccionReporte::BORRADOR || $reporte->registros()->exists()) {
            return back()->with('status', 'Solo se puede eliminar una produccion en borrador y sin avances.');
        }

        $nombre = $reporte->soplador->name;
        // Borrar la asignacion arrastra el reporte; si por datos viejos no hay
        // asignacion, borrar el reporte directo.
        if ($reporte->asignacion) {
            $reporte->asignacion->delete();
        } else {
            $reporte->delete();
        }

        return redirect()->route('admin.produccion.index')
            ->with('status', "Produccion de {$nombre} eliminada.");
    }

    /**
     * Detalle de un reporte para revisar.
     */
    public function reporteShow(ProduccionReporte $reporte): View
    {
        $reporte->load([
            'soplador',
            'revisadoPor',
            'asignacion.preforma',
            'registros' => fn ($query) => $query->latest('id'),
            'registros.maquina',
            'registros.tipoBotellon.producto',
            'movimientos.producto',
        ]);

        return view('admin.produccion.reporte', ['reporte' => $reporte]);
    }

    /**
     * Aprueba un reporte enviado.
     */
    public function aprobar(Request $request, ProduccionReporte $reporte): RedirectResponse
    {
        if (! $reporte->esPendienteDeRevision()) {
            return back()->with('status', 'Solo se pueden aprobar reportes enviados.');
        }

        // Aprobar + generar el kardex local en la misma transaccion (regla #9:
        // solo lo aprobado mueve inventario). Idempotente: si ya tiene
        // movimientos, no se duplican ante un doble submit.
        DB::transaction(function () use ($request, $reporte) {
            // Lock pesimista del reporte ANTES de mutar: el guard de idempotencia
            // movimientos()->exists() es check-then-act, asi que sin lock dos
            // aprobaciones concurrentes (doble-tap / reintento en el celular)
            // podrian pasar ambas y duplicar el kardex. El lock las serializa: la
            // segunda espera, re-lee el estado ya APROBADO y sale sin re-generar.
            $locked = ProduccionReporte::whereKey($reporte->getKey())->lockForUpdate()->first();
            if (! $locked || ! $locked->esPendienteDeRevision()) {
                return;
            }

            $locked->update([
                'estado' => ProduccionReporte::APROBADO,
                'revisado_por' => $request->user()->id,
                'revisado_at' => now(),
            ]);

            if (! $locked->movimientos()->exists()) {
                ProduccionMovimiento::generarParaReporte($locked);
            }
        });

        return redirect()->route('admin.produccion.index')
            ->with('status', "Reporte de {$reporte->soplador->name} aprobado.");
    }

    /**
     * Kardex local de produccion: movimientos generados al aprobar, filtrables
     * por producto (nombre/SKU), tipo de movimiento y rango de fechas. No toca
     * el stock espejo de Bsale; es la verdad local de produccion.
     */
    public function movimientos(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'in:'.implode(',', ProduccionMovimiento::TIPOS)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $query = ProduccionMovimiento::query()->with(['producto', 'reporte.soplador'])
            ->when($filtros['q'] ?? null, fn ($q, $term) => $q->whereHas(
                'producto',
                fn ($p) => $p->where('nombre', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"),
            ))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['desde'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn ($q, $h) => $q->whereDate('fecha', '<=', $h));

        // Resumen del filtro actual (totales por tipo).
        $resumen = (clone $query)->selectRaw('tipo, COALESCE(SUM(cantidad), 0) AS total')
            ->groupBy('tipo')->pluck('total', 'tipo');

        $movimientos = $query->latest('fecha')->latest('id')->paginate(25)->withQueryString();

        return view('admin.produccion.movimientos', [
            'movimientos' => $movimientos,
            'resumen' => $resumen,
            'tipos' => ProduccionMovimiento::TIPOS,
            'etiquetasTipos' => ProduccionMovimiento::ETIQUETAS,
            'filtros' => $filtros,
        ]);
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
            'danada' => ['required', 'integer', 'min:0'],
            'motivo_ajuste' => ['required', 'string', 'max:255'],
        ]);

        $reporte->update($validated);
        $reporte->asignacion?->update(['asignadas' => $validated['asignadas']]);

        return redirect()->route('admin.produccion.reporte.show', $reporte)
            ->with('status', 'Reporte actualizado.');
    }
}
