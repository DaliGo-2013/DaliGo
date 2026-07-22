<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aprobacion;
use App\Models\Maquina;
use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionMovimiento;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\TipoBotellon;
use App\Models\User;
use App\Services\Aprobaciones\Aprobaciones;
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
        // Día de NEGOCIO (P-TZ-01): la cola/alertas/resumen del jefe viven en
        // el día chileno, no en el UTC (que avanza a las 20/21h de Chile).
        $hoy = \App\Support\FechaNegocio::hoy();

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

        // Pendientes de OTROS dias (enviados/devueltos con fecha != hoy): las
        // alertas de arriba los cuentan (son globales), asi que la cola debe
        // darles una fila donde actuar — sin esto la alerta es un callejon sin
        // salida. Mismo patron que los "devueltos de otros dias" del soplador.
        $pendientesOtrosDias = ProduccionReporte::with('soplador')
            ->withCount('registros')
            ->whereIn('estado', [ProduccionReporte::ENVIADO, ProduccionReporte::DEVUELTO])
            ->whereDate('fecha', '!=', $hoy)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        // --- Resumen de HOY (ampliado) ---
        $t = ProduccionReporte::delDia($hoy)
            ->selectRaw('COALESCE(SUM(primera),0) p1, COALESCE(SUM(segunda),0) p2, COALESCE(SUM(malo),0) mal, COALESCE(SUM(danada),0) dan')
            ->first();
        $hoyResumen = ProduccionReporte::armarResumen(
            (int) $t->p1, (int) $t->p2, (int) $t->mal, (int) $t->dan,
            (int) ProduccionAsignacion::whereDate('fecha', $hoy)->sum('asignadas'),
        );
        $hoyResumen['sopladores'] = $reportes->pluck('soplador_id')->unique()->count();

        // --- Produccion por periodo (rango; default ultimos 7 dias) + desgloses ---
        [$desde, $hasta, $esDefault] = $this->rango($request);
        $periodo = $this->construirTendencia($desde, $hasta, $this->reportesPorDia($desde, $hasta), $this->asignadasPorDia($desde, $hasta))
            + ['desde' => $desde, 'hasta' => $hasta, 'esDefault' => $esDefault];
        $rankingSopladores = $this->desgloseSopladores($desde, $hasta);
        $porTipoPeriodo = $this->desgloseRegistros($desde, $hasta, 'tipo_botellon_id', 'tipos_botellon');

        // --- Por maquina de HOY (rollup operativo desde las tandas) ---
        $porMaquina = $this->porMaquinaEntre($hoy, $hoy);
        $porMaquinaMultiSucursal = $porMaquina->whereNotNull('sucursal')->pluck('sucursal')->unique()->count() > 1;

        return view('admin.produccion.index', [
            'reportes' => $reportes,
            'pendientesOtrosDias' => $pendientesOtrosDias,
            'alertas' => $alertas,
            'hoy' => $hoyResumen,
            'periodo' => $periodo,
            'rankingSopladores' => $rankingSopladores,
            'porTipoPeriodo' => $porTipoPeriodo,
            'porMaquina' => $porMaquina,
            'porMaquinaMultiSucursal' => $porMaquinaMultiSucursal,
        ]);
    }

    // ============================================================
    //  Drill-down: helpers de agregacion + vistas de detalle
    // ============================================================

    /**
     * Resuelve el rango [desde, hasta] desde el request (valida y acota). Default:
     * los ultimos $ventana+1 dias hasta hoy. Devuelve [desde, hasta, esDefault].
     */
    private function rango(Request $request, int $ventana = 6): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->toDateString() : \App\Support\FechaNegocio::hoy();
        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->toDateString() : Carbon::parse($hasta)->subDays($ventana)->toDateString();
        if ($desde > $hasta) {
            $desde = $hasta;
        }
        // La tabla por dia se arma en PHP: acotar a ~92 filas.
        if (Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) > 92) {
            $desde = Carbon::parse($hasta)->subDays(92)->toDateString();
        }

        return [$desde, $hasta, ! $request->filled('desde') && ! $request->filled('hasta')];
    }

    /** Serie por dia desde los reportes — delega en el estatico compartido del modelo. */
    private function reportesPorDia(string $desde, string $hasta, ?int $sopladorId = null)
    {
        return ProduccionReporte::seriePorDia($desde, $hasta, $sopladorId);
    }

    /** Serie por dia desde las tandas (registros) filtradas por maquina/tipo, keyed por Y-m-d. */
    private function registrosPorDia(string $desde, string $hasta, string $col, int $id)
    {
        return ProduccionRegistro::query()
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_registros.reporte_id')
            ->whereDate('produccion_reportes.fecha', '>=', $desde)->whereDate('produccion_reportes.fecha', '<=', $hasta)
            ->where("produccion_registros.$col", $id)
            ->selectRaw('produccion_reportes.fecha AS fecha, COALESCE(SUM(produccion_registros.primera),0) p1, COALESCE(SUM(produccion_registros.segunda),0) p2, COALESCE(SUM(produccion_registros.malo),0) mal, COALESCE(SUM(produccion_registros.danada),0) dan, COUNT(DISTINCT produccion_registros.reporte_id) reportes')
            ->groupBy('produccion_reportes.fecha')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->fecha)->toDateString());
    }

    /** Asignadas por dia — delega en el estatico compartido del modelo. */
    private function asignadasPorDia(string $desde, string $hasta)
    {
        return ProduccionAsignacion::asignadasPorDia($desde, $hasta);
    }

    /**
     * Arma la tendencia diaria: una fila por cada dia del rango (con ceros donde
     * no hubo), totales del rango y el maximo de producido (para escalar barras).
     */
    private function construirTendencia(string $desde, string $hasta, $porDia, $asignadasPorDia = null): array
    {
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

            $dias[] = ProduccionReporte::armarResumen($p1, $p2, $mal, $dan, $asig) + ['fecha' => $cursor->copy(), 'reportes' => $rep];
            $sp1 += $p1;
            $sp2 += $p2;
            $smal += $mal;
            $sdan += $dan;
            $sasig += $asig;
            $srep += $rep;
        }

        return [
            // Mas reciente primero (arriba de la lista).
            'dias' => array_reverse($dias),
            'totales' => ProduccionReporte::armarResumen($sp1, $sp2, $smal, $sdan, $sasig) + ['reportes' => $srep],
            'maxProducido' => max(1, collect($dias)->max('producido') ?? 0),
        ];
    }

    /** Ranking de sopladores del rango (producido/merma/tasas + reportes), orden desc. */
    private function desgloseSopladores(string $desde, string $hasta)
    {
        return ProduccionReporte::query()
            ->join('users', 'users.id', '=', 'produccion_reportes.soplador_id')
            ->whereDate('produccion_reportes.fecha', '>=', $desde)->whereDate('produccion_reportes.fecha', '<=', $hasta)
            ->groupBy('produccion_reportes.soplador_id', 'users.name')
            ->selectRaw('produccion_reportes.soplador_id AS id, users.name AS nombre, COALESCE(SUM(primera),0) p1, COALESCE(SUM(segunda),0) p2, COALESCE(SUM(malo),0) mal, COALESCE(SUM(danada),0) dan, COUNT(*) reportes')
            ->get()
            ->map(fn ($r) => (object) (ProduccionReporte::armarResumen((int) $r->p1, (int) $r->p2, (int) $r->mal, (int) $r->dan, 0) + ['id' => $r->id, 'nombre' => $r->nombre, 'reportes' => (int) $r->reportes]))
            ->sortByDesc('producido')->values();
    }

    /**
     * Desglose por maquina o tipo (group por $groupCol, nombre desde $joinTable).
     * Con $whereCol/$whereId filtra (ej. tipos de UNA maquina). Orden desc por producido.
     */
    private function desgloseRegistros(string $desde, string $hasta, string $groupCol, string $joinTable, ?string $whereCol = null, ?int $whereId = null)
    {
        return ProduccionRegistro::query()
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_registros.reporte_id')
            ->leftJoin($joinTable, "$joinTable.id", '=', "produccion_registros.$groupCol")
            ->whereDate('produccion_reportes.fecha', '>=', $desde)->whereDate('produccion_reportes.fecha', '<=', $hasta)
            ->when($whereCol && $whereId, fn ($q) => $q->where("produccion_registros.$whereCol", $whereId))
            ->groupBy("produccion_registros.$groupCol", "$joinTable.nombre")
            ->selectRaw("produccion_registros.$groupCol AS id, $joinTable.nombre AS nombre, COALESCE(SUM(produccion_registros.primera),0) p1, COALESCE(SUM(produccion_registros.segunda),0) p2, COALESCE(SUM(produccion_registros.malo),0) mal, COALESCE(SUM(produccion_registros.danada),0) dan")
            ->get()
            ->map(fn ($r) => (object) (ProduccionReporte::armarResumen((int) $r->p1, (int) $r->p2, (int) $r->mal, (int) $r->dan, 0) + ['id' => $r->id, 'nombre' => $r->nombre]))
            ->sortByDesc('producido')->values();
    }

    /** Sopladores que pasaron por una maquina/tipo en el rango (orden desc por producido). */
    private function desgloseRegistrosPorSoplador(string $desde, string $hasta, string $whereCol, int $whereId)
    {
        return ProduccionRegistro::query()
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_registros.reporte_id')
            ->join('users', 'users.id', '=', 'produccion_reportes.soplador_id')
            ->whereDate('produccion_reportes.fecha', '>=', $desde)->whereDate('produccion_reportes.fecha', '<=', $hasta)
            ->where("produccion_registros.$whereCol", $whereId)
            ->groupBy('produccion_reportes.soplador_id', 'users.name')
            ->selectRaw('produccion_reportes.soplador_id AS id, users.name AS nombre, COALESCE(SUM(produccion_registros.primera),0) p1, COALESCE(SUM(produccion_registros.segunda),0) p2, COALESCE(SUM(produccion_registros.malo),0) mal, COALESCE(SUM(produccion_registros.danada),0) dan')
            ->get()
            ->map(fn ($r) => (object) (ProduccionReporte::armarResumen((int) $r->p1, (int) $r->p2, (int) $r->mal, (int) $r->dan, 0) + ['id' => $r->id, 'nombre' => $r->nombre]))
            ->sortByDesc('producido')->values();
    }

    /** Rollup "por maquina" (con sucursal) para un rango de fechas. */
    private function porMaquinaEntre(string $desde, string $hasta)
    {
        return ProduccionRegistro::query()
            ->join('produccion_reportes', 'produccion_reportes.id', '=', 'produccion_registros.reporte_id')
            ->leftJoin('maquinas', 'maquinas.id', '=', 'produccion_registros.maquina_id')
            ->leftJoin('sucursales', 'sucursales.id', '=', 'maquinas.sucursal_id')
            ->whereDate('produccion_reportes.fecha', '>=', $desde)->whereDate('produccion_reportes.fecha', '<=', $hasta)
            ->groupBy('produccion_registros.maquina_id', 'maquinas.nombre', 'sucursales.nombre')
            ->orderByRaw('maquinas.nombre IS NULL, maquinas.nombre')
            ->selectRaw('produccion_registros.maquina_id AS maquina_id, maquinas.nombre AS maquina, sucursales.nombre AS sucursal, SUM(produccion_registros.primera) AS primera, SUM(produccion_registros.segunda) AS segunda, SUM(produccion_registros.malo) AS malo, SUM(produccion_registros.danada) AS danada')
            ->get();
    }

    /**
     * Detalle de un dia puntual: todas las producciones de esa fecha (todos los
     * sopladores), su rollup por maquina y por tipo, y el resumen del dia.
     */
    public function diaDetalle(Request $request): View
    {
        $request->validate(['fecha' => ['nullable', 'date']]);
        $fecha = $request->filled('fecha') ? Carbon::parse($request->input('fecha'))->toDateString() : \App\Support\FechaNegocio::hoy();

        $reportes = ProduccionReporte::with('soplador')->withCount('registros')
            ->whereDate('fecha', $fecha)
            ->orderByRaw("CASE estado WHEN 'enviado' THEN 0 WHEN 'devuelto' THEN 1 WHEN 'borrador' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();

        $t = ProduccionReporte::whereDate('fecha', $fecha)
            ->selectRaw('COALESCE(SUM(primera),0) p1, COALESCE(SUM(segunda),0) p2, COALESCE(SUM(malo),0) mal, COALESCE(SUM(danada),0) dan')
            ->first();
        $resumen = ProduccionReporte::armarResumen(
            (int) $t->p1, (int) $t->p2, (int) $t->mal, (int) $t->dan,
            (int) ProduccionAsignacion::whereDate('fecha', $fecha)->sum('asignadas'),
        );

        $porMaquina = $this->porMaquinaEntre($fecha, $fecha);

        return view('admin.produccion.dia', [
            'fecha' => Carbon::parse($fecha),
            'reportes' => $reportes,
            'resumen' => $resumen,
            'porMaquina' => $porMaquina,
            'porMaquinaMultiSucursal' => $porMaquina->whereNotNull('sucursal')->pluck('sucursal')->unique()->count() > 1,
            'porTipo' => $this->desgloseRegistros($fecha, $fecha, 'tipo_botellon_id', 'tipos_botellon'),
        ]);
    }

    /**
     * Rendimiento de una maquina en un rango (default ultimo mes): tendencia por
     * dia + desglose por tipo y por soplador que la usaron.
     */
    public function maquinaRendimiento(Request $request, Maquina $maquina): View
    {
        [$desde, $hasta, $esDefault] = $this->rango($request, 29);

        $tendencia = $this->construirTendencia($desde, $hasta, $this->registrosPorDia($desde, $hasta, 'maquina_id', $maquina->id));

        return view('admin.produccion.maquina', [
            'maquina' => $maquina->load('sucursal'),
            'desde' => $desde,
            'hasta' => $hasta,
            'esDefault' => $esDefault,
            'tendencia' => $tendencia,
            'porTipo' => $this->desgloseRegistros($desde, $hasta, 'tipo_botellon_id', 'tipos_botellon', 'maquina_id', $maquina->id),
            'porSoplador' => $this->desgloseRegistrosPorSoplador($desde, $hasta, 'maquina_id', $maquina->id),
        ]);
    }

    /**
     * Produccion de un tipo de botellon en un rango (default ultimo mes):
     * tendencia por dia + desglose por maquina y por soplador.
     */
    public function tipoRendimiento(Request $request, TipoBotellon $tipoBotellon): View
    {
        [$desde, $hasta, $esDefault] = $this->rango($request, 29);

        $tendencia = $this->construirTendencia($desde, $hasta, $this->registrosPorDia($desde, $hasta, 'tipo_botellon_id', $tipoBotellon->id));

        return view('admin.produccion.tipo', [
            'tipo' => $tipoBotellon,
            'desde' => $desde,
            'hasta' => $hasta,
            'esDefault' => $esDefault,
            'tendencia' => $tendencia,
            'porMaquina' => $this->desgloseRegistros($desde, $hasta, 'maquina_id', 'maquinas', 'tipo_botellon_id', $tipoBotellon->id),
            'porSoplador' => $this->desgloseRegistrosPorSoplador($desde, $hasta, 'tipo_botellon_id', $tipoBotellon->id),
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
        $desde = $request->date('desde') ?? \App\Support\FechaNegocio::ahora()->startOfMonth();
        $hasta = $request->date('hasta') ?? \App\Support\FechaNegocio::ahora()->endOfMonth();

        // whereDate (no whereBetween): la columna casteada guarda "Y-m-d 00:00:00"
        // y el borde superior del between la deja fuera (bitacora 2026-07-01).
        $reportes = ProduccionReporte::with(['registros.maquina', 'registros.tipoBotellon', 'asignacion'])
            ->where('soplador_id', $soplador->id)
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
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
     * menciona "preforma", excluyendo las preformas DANADAS (registran merma
     * en el catalogo, no son material asignable a un turno). Fallback: todos
     * los activos (para no bloquear si la categorizacion del catalogo aun no
     * distingue preformas), con la misma exclusion.
     */
    private function preformasParaSelector()
    {
        $preformas = Producto::query()->where('activo', true)
            ->where('categoria', 'like', '%preforma%')
            ->where($this->sinPreformasDanadas())
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);

        if ($preformas->isNotEmpty()) {
            return $preformas;
        }

        return Producto::query()->where('activo', true)
            ->where($this->sinPreformasDanadas())
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre']);
    }

    /**
     * Filtro reutilizable (selector y validacion comparten el universo): fuera
     * los productos cuyo nombre contiene "dañada". Van las DOS variantes de
     * caja porque el LIKE de SQLite solo case-foldea ASCII ('Ñ' != 'ñ'); en
     * MySQL (collation ci) la segunda es redundante pero inofensiva.
     */
    private function sinPreformasDanadas(): \Closure
    {
        return function ($query) {
            $query->where('nombre', 'not like', '%dañada%')
                ->where('nombre', 'not like', '%DAÑADA%');
        };
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
            // max como guardia anti-dedazo: un cero de mas ensucia metricas y
            // kardex aunque a la BD (unsigned int) le quepa.
            'asignadas' => ['required', 'integer', 'min:1', 'max:100000'],
            // Preforma del turno (producto del catalogo). Opcional: si no se
            // elige, el consumo del kardex queda sin enlace a producto. Se
            // restringe a productos ACTIVOS y NO dañados (mismo universo que
            // el selector; un id fuera de ese universo no debe entrar al kardex).
            'preforma_id' => ['nullable', 'integer', Rule::exists('productos', 'id')->where('activo', true)->where($this->sinPreformasDanadas())],
        ], [
            'asignadas.max' => 'La cantidad es demasiado grande; revisa el número ingresado.',
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
        $nombre = $reporte->soplador->name;

        // Guard bajo lock: registroStore lockea el reporte al agregar tandas,
        // asi que el lock serializa borrar-vs-agregar y una tanda que entre
        // entre el chequeo y el delete ya no se pierde en la cascada.
        $eliminado = DB::transaction(function () use ($reporte) {
            $locked = ProduccionReporte::whereKey($reporte->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->estado !== ProduccionReporte::BORRADOR || $locked->registros()->exists()) {
                return false;
            }

            // Borrar la asignacion arrastra el reporte; si por datos viejos no
            // hay asignacion, borrar el reporte directo.
            if ($locked->asignacion) {
                $locked->asignacion->delete();
            } else {
                $locked->delete();
            }

            return true;
        });

        if (! $eliminado) {
            return back()->with('status', 'Solo se puede eliminar una produccion en borrador y sin avances.');
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

        // Mismo idioma de lock que aprobar(): sin el, una devolucion concurrente
        // con una aprobacion podia pisar el estado APROBADO ya con kardex
        // generado (quedaria DEVUELTO con movimientos; al re-aprobar, el guard
        // de idempotencia saltaria la regeneracion y el kardex quedaria
        // desincronizado de las tandas finales). El lock re-lee el estado y la
        // request que llega segunda sale sin tocar nada.
        DB::transaction(function () use ($request, $reporte, $validated) {
            $locked = ProduccionReporte::whereKey($reporte->getKey())->lockForUpdate()->first();
            if (! $locked || ! $locked->esPendienteDeRevision()) {
                return;
            }

            $locked->update([
                'estado' => ProduccionReporte::DEVUELTO,
                'devuelto_motivo' => $validated['devuelto_motivo'],
                'revisado_por' => $request->user()->id,
                'revisado_at' => now(),
            ]);
        });

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
            'asignadas' => ['required', 'integer', 'min:1', 'max:100000'],
            'primera' => ['required', 'integer', 'min:0', 'max:100000'],
            'segunda' => ['required', 'integer', 'min:0', 'max:100000'],
            'malo' => ['required', 'integer', 'min:0', 'max:100000'],
            'danada' => ['required', 'integer', 'min:0', 'max:100000'],
            'motivo_ajuste' => ['required', 'string', 'max:255'],
        ], [
            '*.max' => 'La cantidad es demasiado grande; revisa el número ingresado.',
        ]);

        // M14 (P-M14-05): el ajuste pasa por el motor de aprobaciones. La
        // magnitud es la suma de las diferencias |Δ| de las 5 cantidades: el
        // dedazo chico se auto-aprueba y aplica AQUI mismo (misma UX de
        // siempre, via el handler con lock — la transaccion vive en el
        // servicio); la reescritura grande queda pendiente para el admin y
        // el reporte NO se toca hasta que la apruebe.
        $monto = collect(['asignadas', 'primera', 'segunda', 'malo', 'danada'])
            ->sum(fn (string $campo) => abs($validated[$campo] - $reporte->{$campo}));

        $aprobacion = app(Aprobaciones::class)->solicitar(
            tipoAccion: Aprobacion::ACCION_AJUSTE_REPORTE,
            aprobable: $reporte,
            solicitante: $request->user(),
            motivo: $validated['motivo_ajuste'],
            datos: [
                'nuevo' => $validated,
                'anterior' => $reporte->only(['asignadas', 'primera', 'segunda', 'malo', 'danada', 'motivo_ajuste']),
                'objetivo_updated_at' => $reporte->updated_at?->toJSON(),
            ],
            monto: $monto,
            descripcion: "Ajuste reporte #{$reporte->id} de {$reporte->soplador->name}",
        );

        return redirect()->route('admin.produccion.reporte.show', $reporte)
            ->with('status', $aprobacion->esPendiente()
                ? 'El ajuste supera el umbral: quedó pendiente de aprobación (el reporte no cambia hasta que lo aprueben).'
                : 'Reporte actualizado.');
    }
}
