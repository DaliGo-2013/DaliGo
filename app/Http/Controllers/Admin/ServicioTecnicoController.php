<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CotizacionCliente;
use App\Mail\DetalleTrabajoCliente;
use App\Mail\EquipoListoParaRetiro;
use App\Mail\RetiroSinReparacion;
use App\Mail\SinSolucionCliente;
use App\Mail\IngresoTallerRecibido;
use App\Models\AgendaTrabajo;
use App\Models\AgendaTrabajoRepuesto;
use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\OrdenServicioFoto;
use App\Models\OrdenServicioRepuesto;
use App\Models\Producto;
use App\Models\TiempoReparacion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Notificaciones\NotificacionDispatcher;
use App\Services\ServicioTecnico\InformeTallerExcel;
use App\Services\ServicioTecnico\InformeTerrenoExcel;
use App\Rules\RutChileno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Servicio Tecnico (taller): ingreso de maquinas y lavadoras. Version basica =
 * CRUD (registro/listado). El cliente dueno se busca por RUT (autocompletado
 * via buscarCliente, JSON) porque hay decenas de miles de clientes y un <select>
 * no sirve. Espeja el patron de ClienteController.
 */
class ServicioTecnicoController extends Controller
{
    /**
     * Repuestos comunes del taller. Sirven de catalogo base para el
     * autocompletado de "Repuestos usados" cuando aun no hay historial
     * suficiente. El historial real (nombres ya escritos en reparaciones)
     * se mezcla con esta lista en buscarRepuesto().
     */
    private const REPUESTOS_COMUNES = [
        'Placa electrica',
        'Cambio de tapa lateral derecha',
        'Cambio de tapa lateral izquierda',
        'Celda de peltier',
        'Llaves',
        'Caldera',
        'Resistencia',
        'Termostato',
        'Sensor de temperatura',
        'Bomba de agua',
        'Motor',
        'Ventilador',
        'Cable de poder',
        'Interruptor',
        'Fusible',
        'Manguera',
        'Empaquetadura',
        'Filtro',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        // Visibilidad por vendedor (regla #2): quien no tiene 'ver todo servicio
        // tecnico' solo ve las ordenes de SU cartera (+ la de su equipo si es
        // jefatura). Ver OrdenServicio::scopeVisiblePara.
        $ordenes = $this->filteredQuery($request)
            ->visiblePara($user)
            ->with(['producto', 'sucursal'])
            ->latest('fecha_ingreso')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.servicio-tecnico.index', array_merge([
            'ordenes' => $ordenes,
            'filtros' => $request->only(['q', 'estado', 'estados', 'tipo_equipo', 'facturacion', 'sucursal_id', 'anio', 'mes', 'por']),
            // Cards de navegacion del historial (Año → Mes) sobre el listado.
            'historial' => $this->resumenHistorial($request->filled('anio') ? (int) $request->input('anio') : null),
            // Maquinas que llegaron por QR y esperan que el encargado confirme la
            // recepcion (bloque destacado arriba del listado). Se acota igual que
            // el listado para no filtrar carteras ajenas.
            'porConfirmar' => OrdenServicio::porConfirmar()
                ->visiblePara($user)
                ->with('sucursal')
                ->latest('id')
                ->get(),
            // Aviso suave en la vista cuando el listado esta acotado a la cartera
            // propia (el usuario ve menos ordenes a proposito, no por un bug).
            'soloMiCartera' => ! $user->can('ver todo servicio tecnico'),
        ], $this->formData()));
    }

    /**
     * Landing de informes: dos "carpetas" — Dispensadores (taller) e Industrial
     * (terreno). Cada rol ve solo la(s) que puede: si tiene una sola, va directo
     * a ella; si tiene ambas, elige en el landing.
     */
    public function informes(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $dispensadores = $user->can('ver informe dispensadores');
        $industrial = $user->can('ver informe industrial');

        if ($dispensadores && ! $industrial) {
            return redirect()->route('admin.servicio-tecnico.informe.dispensadores');
        }
        if ($industrial && ! $dispensadores) {
            return redirect()->route('admin.servicio-tecnico.informe.industrial');
        }

        return view('admin.servicio-tecnico.informes');
    }

    /**
     * Informe de estadisticas del taller (DISPENSADORES) por periodo (un mes o el
     * año completo). Lectura para los jefes (permiso 'view servicio tecnico'):
     * cuantas ordenes ingresaron, garantia vs reparacion, que equipos y clientes
     * se repiten y que repuestos se usaron (apoyo al control de inventario).
     */
    public function informeDispensadores(Request $request): View
    {
        [$desde, $hasta, $anio, $mes, $tipo] = $this->periodoInforme($request);

        // KPIs en una pasada. Se agrega sobre la columna cruda `facturacion`
        // (la condicion registrada al ingreso): condicion_efectiva es un
        // accessor PHP, y validateData ya fuerza garantia vencida a
        // 'reparacion' al registrar, asi que en la practica coinciden.
        // Reparaciones = total - garantias (igual que condicion_efectiva:
        // las ordenes viejas con facturacion NULL cuentan como reparacion).
        $kpis = $this->ordenesDelPeriodo($desde, $hasta, $tipo)->selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN facturacion = 'garantia' THEN 1 ELSE 0 END) AS garantias
        ")->first();

        $porTipo = $this->ordenesDelPeriodo($desde, $hasta, $tipo)
            ->selectRaw('tipo_equipo AS nombre, COUNT(*) AS cantidad')
            ->groupBy('tipo_equipo')->orderByDesc('cantidad')->get();

        $porEstado = $this->ordenesDelPeriodo($desde, $hasta, $tipo)
            ->selectRaw('estado AS nombre, COUNT(*) AS cantidad')
            ->groupBy('estado')->orderByDesc('cantidad')->get();

        // Por causa de la falla (indicador de capacitacion): mal uso / desgaste
        // / fabrica; las NULL se agrupan como "sin_determinar". COALESCE es
        // portable MySQL 5.7 / SQLite.
        $porCausa = $this->ordenesDelPeriodo($desde, $hasta, $tipo)
            ->selectRaw("COALESCE(causa_falla, 'sin_determinar') AS causa, COUNT(*) AS cantidad")
            ->groupBy('causa')->orderByDesc('cantidad')->get();

        // "Que equipo ingresa mas": por producto del catalogo (el campo `modelo`
        // es texto libre y no agrupa bien). Los ingresos por QR sin codigo caen
        // en la fila "Sin código". MAX() por ONLY_FULL_GROUP_BY (MySQL 5.7).
        $topEquipos = $this->ordenesDelPeriodo($desde, $hasta, $tipo)
            ->leftJoin('productos', 'productos.id', '=', 'ordenes_servicio.producto_id')
            ->selectRaw('ordenes_servicio.producto_id AS id, MAX(productos.nombre) AS nombre, MAX(productos.sku) AS sku, COUNT(*) AS cantidad')
            ->groupBy('ordenes_servicio.producto_id')
            ->orderByDesc('cantidad')->limit(10)->get();

        // Agrupar por RUT cuando existe; si no hay RUT (muchas órdenes históricas
        // no lo traen), agrupar por NOMBRE para no juntar clientes distintos en una
        // sola bolsa. Antes, todo lo sin-RUT caía en un mismo grupo y MAX(nombre)
        // le ponía el nombre alfabeticamente mayor (inflaba un solo cliente).
        $claveCliente = "COALESCE(NULLIF(cliente_rut, ''), cliente_nombre)";
        $topClientes = $this->ordenesDelPeriodo($desde, $hasta, $tipo)
            ->selectRaw('MAX(cliente_nombre) AS nombre, MAX(cliente_rut) AS cliente_rut, COUNT(*) AS cantidad')
            ->groupBy(DB::raw($claveCliente))
            ->orderByDesc('cantidad')->limit(10)->get();

        // Repuestos usados en las ordenes del periodo, agregados por nombre.
        // Mismo filtro de tipo que el resto del informe (para calcular compras
        // por tipo de equipo).
        $repuestos = OrdenServicioRepuesto::query()
            ->join('ordenes_servicio', 'ordenes_servicio.id', '=', 'orden_servicio_repuestos.orden_servicio_id')
            ->whereDate('ordenes_servicio.fecha_ingreso', '>=', $desde)
            ->whereDate('ordenes_servicio.fecha_ingreso', '<=', $hasta)
            ->when($tipo, fn (Builder $qb) => $qb->where('ordenes_servicio.tipo_equipo', $tipo))
            ->selectRaw('orden_servicio_repuestos.nombre AS nombre, SUM(orden_servicio_repuestos.cantidad) AS unidades, COUNT(DISTINCT orden_servicio_repuestos.orden_servicio_id) AS ordenes')
            ->groupBy('orden_servicio_repuestos.nombre')
            ->orderByDesc('unidades')->get();

        $total = (int) ($kpis->total ?? 0);
        $periodoLabel = $this->periodoLabel($anio, $mes);

        return view('admin.servicio-tecnico.informe-dispensadores', [
            'anio' => $anio,
            'mes' => $mes,
            'tipo' => $tipo,
            'anios' => $this->aniosDisponibles(),
            'tipos' => OrdenServicio::TIPOS,
            'kpis' => [
                'total' => $total,
                'garantias' => (int) ($kpis->garantias ?? 0),
                'reparaciones' => $total - (int) ($kpis->garantias ?? 0),
                'pctGarantia' => $total > 0 ? (int) round(($kpis->garantias ?? 0) / $total * 100) : 0,
            ],
            'porTipo' => $porTipo,
            'porEstado' => $porEstado,
            'porCausa' => $porCausa,
            'topEquipos' => $topEquipos,
            'topClientes' => $topClientes,
            'repuestos' => $repuestos->take(15)->values(),
            'totalUnidadesRepuestos' => (int) $repuestos->sum('unidades'),
            'totalNombresRepuestos' => $repuestos->count(),
            'periodoLabel' => $periodoLabel,
            'tipoLabel' => $tipo ? OrdenServicio::etiquetaTipo($tipo) : 'Todos los equipos',
        ]);
    }

    /**
     * Informe del servicio INDUSTRIAL (agenda de terreno) por periodo. Indicadores:
     * uso de repuestos en números, % por tipo de trabajo, servicios más usados,
     * cumplimiento (realizados vs pendientes), clientes que más solicitan y visitas
     * técnicas. Base: trabajos con fecha en el período y estado agendado o realizado
     * (se excluyen cancelados y solicitudes sin coordinar).
     */
    public function informeIndustrial(Request $request): View
    {
        [$desde, $hasta, $anio, $mes] = $this->periodoInforme($request);

        // Query base reutilizable (clon por indicador). El criterio vive en
        // trabajosDelPeriodo() porque el Excel de este mismo informe tiene que
        // exportar EXACTAMENTE el universo que se muestra: si se escribiera dos
        // veces, un cambio en uno dejaria el archivo diciendo otra cosa que la
        // pantalla.
        $base = fn () => $this->trabajosDelPeriodo($desde, $hasta);

        $total = $base()->count();

        // Cumplimiento: realizados vs pendientes vs NO realizados. Los tres se
        // cuentan explícitamente y `pendientes` ya NO se deduce restando: desde
        // que existe 'no_realizado' (14-08) la resta lo habría metido dentro de
        // «pendientes», o sea habría contado como «falta hacerlo» un trabajo al
        // que el técnico ya fue. Los tres suman el total del período.
        $realizados = $base()->where('estado', 'realizado')->count();
        $noRealizados = $base()->where('estado', 'no_realizado')->count();
        $pendientes = $base()->where('estado', 'agendado')->count();
        $pctCumplimiento = $total > 0 ? (int) round($realizados / $total * 100) : 0;
        $pctPendientes = $total > 0 ? (int) round($pendientes / $total * 100) : 0;
        $pctNoRealizados = $total > 0 ? (int) round($noRealizados / $total * 100) : 0;

        // Detalle cliqueable de las tarjetas Realizados/Pendientes: la lista de
        // trabajos que hay detrás de cada número, para pasar del agregado al
        // detalle sin salir del informe. Volumen de terreno es bajo (decenas por
        // período), así que se cargan completas. Realizados: lo más reciente
        // primero; pendientes: lo más próximo primero.
        $realizadosLista = $base()->where('estado', 'realizado')
            ->with(['servicio:id,nombre', 'tecnico:id,name', 'repuestos'])
            ->orderByDesc('fecha')->get();
        $pendientesLista = $base()->where('estado', 'agendado')
            ->with(['servicio:id,nombre', 'tecnico:id,name', 'repuestos'])
            ->orderBy('fecha')->get();
        // Los NO realizados con su motivo: es la lista que ventas mira para
        // decidir si se vuelve o no (el motivo lo escribió el técnico al cerrar).
        $noRealizadosLista = $base()->where('estado', 'no_realizado')
            ->with(['servicio:id,nombre', 'tecnico:id,name', 'repuestos'])
            ->orderByDesc('fecha')->get();

        // DESGLOSE POR TIPO DE TRABAJO, en UNA consulta que alimenta las DOS
        // superficies: las tarjetas por tipo y el ranking «por tipo de trabajo».
        // Antes eran TRES consultas para el mismo agrupamiento —dos dedicadas solo a
        // la visita técnica y una para el ranking—, y la de las visitas era la única
        // que además contaba realizados.
        //
        // SUM(CASE WHEN…) y no un `having`: es portable entre MySQL 5.7 y SQLite, y
        // trae el total y los realizados en la misma pasada.
        $conteoPorTipo = $base()
            ->selectRaw("tipo, COUNT(*) AS total, SUM(CASE WHEN estado = 'realizado' THEN 1 ELSE 0 END) AS realizados")
            ->groupBy('tipo')
            ->get()
            ->keyBy('tipo');

        // Las tarjetas: SIEMPRE LAS CUATRO y en el orden del catálogo (pedido del
        // dueño 14-08, que antes solo tenía la de visitas técnicas). Un tipo sin
        // trabajos muestra 0 en vez de desaparecer: que un mes no haya reparaciones
        // es información, y una tarjeta ausente se lee como «esto no se mide».
        $tiposResumen = collect(AgendaTrabajo::TIPOS)->map(function (string $tipo) use ($conteoPorTipo, $total) {
            $n = (int) ($conteoPorTipo[$tipo]->total ?? 0);

            return [
                'tipo' => $tipo,
                'label' => AgendaTrabajo::TIPO_ETIQUETAS[$tipo] ?? $tipo,
                'total' => $n,
                'realizados' => (int) ($conteoPorTipo[$tipo]->realizados ?? 0),
                'pct' => $total > 0 ? (int) round($n / $total * 100) : 0,
            ];
        })->values();

        // El ranking sale del MISMO conteo (no de una consulta propia), ordenado por
        // cantidad. Conserva las claves nombre/cantidad que espera `_ranking`.
        $porTipo = $conteoPorTipo
            ->map(fn ($r) => (object) ['nombre' => $r->tipo, 'cantidad' => (int) $r->total])
            ->sortByDesc('cantidad')
            ->values();

        // Clientes que más solicitan servicio industrial. Agrupa por RUT cuando
        // existe; si no, por nombre — el criterio vive en el MODELO porque el
        // historial de abajo tiene que agrupar la misma colección en PHP.
        $claveCliente = AgendaTrabajo::SQL_CLAVE_CLIENTE;
        $topClientes = $base()
            ->selectRaw("{$claveCliente} AS clave, MAX(cliente_nombre) AS nombre, MAX(cliente_rut) AS cliente_rut, COUNT(*) AS cantidad")
            ->groupBy(DB::raw($claveCliente))
            ->orderByDesc('cantidad')->limit(10)->get();

        // QUÉ SE LE HIZO A CADA UNO DE ESOS CLIENTES (pedido del técnico Carlos,
        // 14-08-2026): el ranking decía cuántas veces vino cada cliente pero no qué
        // se hizo en esas visitas, que es lo que él necesita cuando lo llaman de
        // vuelta — «a esta lavadora ya le cambiamos los rodamientos en junio».
        //
        // El detalle sale del texto que el propio técnico escribió al cerrar
        // (`notas_tecnico`) más los repuestos que declaró; no hay un catálogo de
        // trabajos que inventariar.
        //
        // UNA sola consulta para los 10 clientes (no una por cliente) y se agrupa
        // en PHP con la MISMA clave que agrupó el SQL de arriba. Con la lista de
        // claves vacía, `whereIn` no devuelve nada — que es lo correcto acá y no el
        // `whereNotIn([])` que barre todo (bitácora 2026-06-12).
        $historialClientes = $topClientes->isEmpty()
            ? collect()
            : $base()
                ->with(['servicio:id,nombre', 'tecnico:id,name', 'repuestos'])
                ->whereIn(DB::raw($claveCliente), $topClientes->pluck('clave')->all())
                ->orderByDesc('fecha')->orderByDesc('id')
                ->get()
                ->groupBy(fn (AgendaTrabajo $t) => $t->claveCliente());

        // Servicios del catálogo más usados. MAX() por ONLY_FULL_GROUP_BY (5.7).
        // Los trabajos "fuera de tarifa" (servicio_terreno_id null) caen en su fila.
        $topServicios = $base()
            ->leftJoin('servicios_terreno', 'servicios_terreno.id', '=', 'agenda_trabajos.servicio_terreno_id')
            ->selectRaw('agenda_trabajos.servicio_terreno_id AS id, MAX(servicios_terreno.nombre) AS nombre, COUNT(*) AS cantidad')
            ->groupBy('agenda_trabajos.servicio_terreno_id')
            ->orderByDesc('cantidad')->limit(10)->get();

        // USO de repuestos (en números): suma de unidades por repuesto de los
        // trabajos del período. Mismo patrón que el informe de dispensadores.
        //
        // LAS VISITAS TÉCNICAS QUEDAN FUERA, y es la diferencia entre un número
        // verdadero y uno que miente: en la visita de revisión el técnico anota lo
        // que va a NECESITAR (con eso ventas cotiza la segunda visita), no lo que
        // instaló — ahí no instala nada. Contarlas acá infla el consumo con
        // repuestos que nunca salieron de bodega, y encima DOS VECES, porque en la
        // segunda visita se declaran de nuevo al usarlos de verdad. Medido con el
        // candado puesto: 8 unidades donde había 4.
        // El pronóstico no se pierde: viaja en el aviso a ventas y en el Excel,
        // rotulado en la columna «Registro».
        $repuestos = AgendaTrabajoRepuesto::query()
            ->join('agenda_trabajos', 'agenda_trabajos.id', '=', 'agenda_trabajo_repuestos.agenda_trabajo_id')
            ->where('agenda_trabajos.tipo', '!=', AgendaTrabajo::TIPO_PUBLICO)
            ->whereNotNull('agenda_trabajos.fecha')
            ->whereDate('agenda_trabajos.fecha', '>=', $desde)
            ->whereDate('agenda_trabajos.fecha', '<=', $hasta)
            ->selectRaw('agenda_trabajo_repuestos.nombre AS nombre, SUM(agenda_trabajo_repuestos.cantidad) AS unidades, COUNT(DISTINCT agenda_trabajo_repuestos.agenda_trabajo_id) AS trabajos')
            ->groupBy('agenda_trabajo_repuestos.nombre')
            ->orderByDesc('unidades')->get();

        $periodoLabel = $this->periodoLabel($anio, $mes);

        return view('admin.servicio-tecnico.informe-industrial', [
            'anio' => $anio,
            'mes' => $mes,
            'anios' => $this->aniosDisponiblesAgenda(),
            'total' => $total,
            'realizados' => $realizados,
            'pendientes' => $pendientes,
            'noRealizados' => $noRealizados,
            'pctNoRealizados' => $pctNoRealizados,
            'noRealizadosLista' => $noRealizadosLista,
            'pctCumplimiento' => $pctCumplimiento,
            'pctPendientes' => $pctPendientes,
            'realizadosLista' => $realizadosLista,
            'pendientesLista' => $pendientesLista,
            // Reemplaza a 'visitas'/'visitasRealizadas'/'pctVisitas': el mismo dato
            // para los cuatro tipos, en una sola estructura. Tres variables sueltas
            // para UN tipo no se podían generalizar sin multiplicarlas por cuatro.
            'tiposResumen' => $tiposResumen,
            'porTipo' => $porTipo,
            'topServicios' => $topServicios,
            'topClientes' => $topClientes,
            'historialClientes' => $historialClientes,
            'repuestos' => $repuestos->take(15)->values(),
            'totalUnidadesRepuestos' => (int) $repuestos->sum('unidades'),
            'totalNombresRepuestos' => $repuestos->count(),
            'periodoLabel' => $periodoLabel,
        ]);
    }

    /**
     * El informe del taller como Excel de TABLA PLANA (pedido del gerente
     * general, 13-08-2026: bajarse el apartado de Informes «como informacion»
     * para decidir con ella). Exporta el MISMO periodo y filtro que la pantalla
     * —reusa periodoInforme() y ordenesDelPeriodo()— pero completo: sin el top 15
     * de las tarjetas, que es un recorte de legibilidad y en un archivo de datos
     * seria mentir por omision.
     */
    public function informeDispensadoresExcel(Request $request): Response
    {
        [$desde, $hasta, $anio, $mes, $tipo] = $this->periodoInforme($request);

        $ordenes = $this->ordenesDelPeriodo($desde, $hasta, $tipo)
            ->with(['producto:id,nombre,sku', 'sucursal:id,nombre', 'repuestos'])
            ->orderBy('fecha_ingreso')->orderBy('id')
            ->get();

        $periodoLabel = $this->periodoLabel($anio, $mes);
        $contenido = (new InformeTallerExcel)->generar(
            $ordenes,
            $periodoLabel,
            $tipo ? OrdenServicio::etiquetaTipo($tipo) : 'Todos los equipos',
        );

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.InformeTallerExcel::nombreArchivo($periodoLabel).'"',
        ]);
    }

    /** El informe de terreno como Excel de tabla plana. Hermano del anterior. */
    public function informeIndustrialExcel(Request $request): Response
    {
        [$desde, $hasta, $anio, $mes] = $this->periodoInforme($request);

        $trabajos = $this->trabajosDelPeriodo($desde, $hasta)
            ->with(['servicio:id,nombre', 'tecnico:id,name', 'repuestos'])
            ->orderBy('fecha')->orderBy('id')
            ->get();

        $periodoLabel = $this->periodoLabel($anio, $mes);
        $contenido = (new InformeTerrenoExcel)->generar($trabajos, $periodoLabel);

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.InformeTerrenoExcel::nombreArchivo($periodoLabel).'"',
        ]);
    }

    public function create(): View
    {
        return view('admin.servicio-tecnico.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request, creando: true);

        // El staff (tecnico/admin) puede elegir el estado inicial al registrar
        // para ir informando el paso a paso; por defecto parte en 'recibido'.
        // El cliente no toca este campo (el ingreso por QR no lo tiene). La fecha
        // estimada la sigue fijando el servidor segun la sucursal (no editable).
        $data['estado'] = $data['estado'] ?? 'recibido';
        // La fecha estimada la fija la sucursal; en "ruta" no hay sucursal, así que
        // se respeta la que venga del formulario (o queda vacía).
        $data['fecha_entrega'] = ! empty($data['sucursal_id'])
            ? Sucursal::findOrFail($data['sucursal_id'])->fechaEntregaEstimada($data['fecha_ingreso'])->toDateString()
            : ($data['fecha_entrega'] ?? null);
        // Quien registra en el mostrador es quien recibe el equipo.
        $data['recibida_por'] = $request->user()->name;

        $orden = OrdenServicio::create($data);

        // Se le envia el folio al cliente (mismo correo que el flujo QR). Es
        // SECUNDARIO: si el mailer del servidor falla, NO tumba el registro; se
        // loguea y se avisa en el mensaje. Máquina propia sin correo: no se envía.
        if (blank($orden->cliente_email)) {
            $status = "Orden {$orden->folio} registrada.";
        } else {
            try {
                Mail::to($orden->cliente_email)->send(new IngresoTallerRecibido($orden));
                $status = "Orden {$orden->folio} registrada. Folio enviado a {$orden->cliente_email}.";
            } catch (\Throwable $e) {
                report($e);
                $status = "Orden {$orden->folio} registrada. No se pudo enviar el correo (revisa la configuración de correo del servidor).";
            }
        }

        return redirect()->route('admin.servicio-tecnico.index')->with('status', $status);
    }

    /**
     * Confirmar la recepcion de una maquina que llego por QR (ingreso publico).
     * El encargado ya reviso los datos con el cliente y la maquina fisica:
     * marca confirmada_at y recien AHI se le manda el correo con el folio (asi
     * el cliente recibe datos verificados). Lock para que dos clics no confirmen
     * ni manden el correo dos veces (patron de locks del modulo).
     */
    public function confirmar(OrdenServicio $orden): RedirectResponse
    {
        $confirmadaAhora = DB::transaction(function () use ($orden) {
            $fresh = OrdenServicio::whereKey($orden->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->confirmada_at !== null) {
                return false;
            }

            // Queda registrado QUIEN recibio el equipo (el encargado que confirma).
            $fresh->update([
                'confirmada_at' => now(),
                'recibida_por' => auth()->user()->name,
            ]);

            return true;
        });

        if (! $confirmadaAhora) {
            return back()->with('status', "La orden {$orden->folio} ya estaba confirmada.");
        }

        $orden = $orden->fresh();

        // El correo es SECUNDARIO: si el mailer del servidor no esta configurado
        // (SMTP pendiente, P-M15-10), su fallo NO debe tumbar la recepcion ya
        // confirmada. Se loguea y se sigue.
        if (filled($orden->cliente_email)) {
            try {
                Mail::to($orden->cliente_email)->send(new IngresoTallerRecibido($orden));

                return back()->with('status', "Recepción de la orden {$orden->folio} confirmada y avisada a {$orden->cliente_email}.");
            } catch (\Throwable $e) {
                report($e);

                return back()->with('status', "Recepción de la orden {$orden->folio} confirmada. No se pudo enviar el correo al cliente (revisa la configuración de correo del servidor).");
            }
        }

        return back()->with('status', "Recepción de la orden {$orden->folio} confirmada.");
    }

    /**
     * Conteo (JSON) de ordenes por confirmar (llegaron por QR y sin confirmar).
     * Lo consulta en segundo plano el listado para el "aviso suave": si el total
     * sube respecto al de la carga, muestra un banner "hay nuevos" SIN recargar.
     */
    public function porConfirmarConteo(): JsonResponse
    {
        return response()->json(['total' => OrdenServicio::porConfirmar()->count()]);
    }

    /**
     * Pagina imprimible con el QR de cada sucursal activa. Cada QR apunta al link
     * FIRMADO del formulario publico con su sucursal_id embebido. El encargado la
     * imprime y la pega en el mostrador. El QR se dibuja en el cliente desde la
     * URL firmada (sin dependencia nueva de servidor).
     */
    public function qr(): View
    {
        // Solo las sucursales que RECIBEN servicio tecnico (config): Buzeta no
        // recibe, asi que no se imprime su QR. Mismo criterio que el selector de
        // la portada.
        $sucursales = Sucursal::recepcionServicioTecnico()
            ->get()
            ->map(fn (Sucursal $s) => [
                'sucursal' => $s,
                'url' => URL::signedRoute('ingreso-taller.create', ['sucursal' => $s->id]),
            ]);

        return view('admin.servicio-tecnico.qr', ['sucursales' => $sucursales]);
    }

    public function show(OrdenServicio $orden): View
    {
        // Visibilidad por vendedor: no dejar abrir por URL una orden fuera de la
        // cartera propia (quien tiene 'ver todo servicio tecnico' pasa siempre).
        abort_unless($orden->esVisiblePara(auth()->user()), 403);

        // `lote` para el bloque «Retiro en ruta» (conductor y ciudad de origen).
        $orden->load(['producto.precios.lista', 'sucursal', 'repuestos', 'fotos', 'lote']);

        return view('admin.servicio-tecnico.show', [
            'orden' => $orden,
            'sucursalCentral' => Sucursal::firstWhere('es_central', true),
            'precioVentaEquipo' => $this->precioVentaProducto($orden->producto),
        ]);
    }

    /**
     * Precio de venta (con IVA) del producto en el catálogo, de la lista oficial
     * de ventas. Null si no hay producto o no tiene precio ahí. Se usa para
     * advertir cuando la reparación es cara respecto al valor del equipo.
     */
    private function precioVentaProducto(?Producto $producto): ?int
    {
        return $producto?->precioVentaConIva();
    }

    /**
     * Sirve una foto de recepcion desde el disco PRIVADO `local`. Solo para
     * usuarios con sesion y permiso de ver servicio tecnico (la ruta lo exige);
     * NO es una URL publica adivinable.
     */
    public function foto(OrdenServicioFoto $foto): StreamedResponse
    {
        // Misma visibilidad por cartera que la ficha: la foto es parte del detalle.
        abort_unless($foto->orden->esVisiblePara(auth()->user()), 403);
        abort_unless(Storage::disk('local')->exists($foto->ruta), 404);

        return Storage::disk('local')->response($foto->ruta);
    }

    public function edit(OrdenServicio $orden): View
    {
        return view('admin.servicio-tecnico.edit', array_merge(
            ['orden' => $orden->load('producto', 'fotos')],
            $this->formData($orden)
        ));
    }

    public function update(Request $request, OrdenServicio $orden): RedirectResponse
    {
        $orden->update($this->validateData($request, orden: $orden));

        return redirect()->route('admin.servicio-tecnico.index')
            ->with('status', "Orden {$orden->folio} actualizada.");
    }

    public function destroy(OrdenServicio $orden): RedirectResponse
    {
        $folio = $orden->folio;
        $orden->delete();

        return back()->with('status', "Orden {$folio} eliminada.");
    }

    /**
     * Etapa de taller (tecnico): pantalla aparte sobre la MISMA orden para no
     * alargar el formulario de recepcion en movil. Aqui se registra el arreglo,
     * los repuestos, la mano de obra, el estado y las fechas de aviso/retiro.
     */
    public function reparacion(OrdenServicio $orden): View
    {
        // Desde el 20-08 esta pantalla es LA pantalla de la orden (dueño: «toda la
        // información en un solo apartado»), así que necesita todo lo que antes solo
        // pedía la pestaña Cotización: el presupuesto, sus candados y el historial.
        $orden->load(['producto.precios.lista', 'repuestos', 'trabajos']);

        return view('admin.servicio-tecnico.reparacion', [
            'orden' => $orden,
            'estados' => OrdenServicio::ESTADOS,
            'causasFalla' => OrdenServicio::CAUSAS_FALLA,
            // El catálogo de trabajos, agrupado, PARA MARCAR. Sale de la base y no de
            // `config('servicio_tecnico.respuestas_trabajo')`, que es de donde salía hasta el
            // 28-08: eran DOS listas y ya divergían — un trabajo que jefatura agregaba en
            // «Costos generales de reparación» no aparecía nunca en la pantalla del técnico.
            // Hoy la lista y las horas son la MISMA fila.
            'trabajosCatalogo' => $this->catalogoParaMarcar($orden),
            // Los que ya están marcados, para dibujarlos marcados al volver.
            'trabajosMarcados' => $orden->trabajos->pluck('id')->all(),
            // Los tres remates del catálogo («funciona normal», «queda en óptimas
            // condiciones», «irreparable») para elegir UNO al final de la frase.
            'rematesTrabajo' => $this->rematesTrabajo(),
            // Valor hora de mano de obra (precio con IVA del SKU de servicio
            // tecnico). Null si no existe/no tiene precio -> mano de obra $0.
            'precioHoraServicio' => $this->precioHoraServicio(),
            // El tope de horas, para mostrar la resta en vivo («suman 2,5 h · tope 2 h»).
            'topeHoras' => TiempoReparacion::topeHoras(),
            // --- Lo que el presupuesto y el historial necesitan (antes solo en la
            //     pestaña Cotización; ver cotizacion() para el porqué de cada uno).
            'cotizaciones' => $orden->cotizaciones()->latest('id')->get(),
            'precioVentaEquipo' => $this->precioVentaProducto($orden->producto),
            'horasTrabajo' => $orden->horasACobrar(),
            'manoObraVigente' => $this->manoObraDe($orden),
            'faltaManoObra' => $this->faltaManoObra($orden),
        ]);
    }

    /**
     * El catálogo que la pantalla ofrece para marcar: los ACTIVOS, más los que esta orden ya
     * tiene marcados aunque estén desactivados.
     *
     * Los inactivos-ya-marcados no son un detalle: una orden histórica pudo cerrarse con un
     * trabajo que jefatura desactivó después. Si la pantalla no lo ofreciera, al abrir el parte
     * y guardarlo ese trabajo se caería del pivote y la mano de obra bajaría sola — un borrado
     * silencioso de dinero ya cotizado. Este método define además el SCOPE que valida el
     * guardado (doctrina de la bitácora [2026-06-30]: un id se valida contra el mismo scope que
     * lo ofrece, no con un `exists` pelado).
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, TiempoReparacion>>
     */
    private function catalogoParaMarcar(OrdenServicio $orden): \Illuminate\Support\Collection
    {
        return $this->trabajosMarcables($orden)
            ->sortBy([['grupo', 'asc'], ['trabajo', 'asc']])
            ->groupBy('grupo');
    }

    /** Las filas marcables (el scope: activos ∪ los que esta orden ya tiene marcados). */
    private function trabajosMarcables(OrdenServicio $orden): \Illuminate\Support\Collection
    {
        $yaMarcados = $orden->trabajos->pluck('id');

        // El `orWhere` va AGRUPADO aunque hoy sean las dos únicas condiciones: un `where(...)
        // ->orWhere(...)` sin paréntesis se rompe en silencio en cuanto alguien agregue un
        // tercer filtro (el `or` se lo come), y es un defecto que no se nota mirando la pantalla
        // — devuelve MÁS filas, no menos. Agrupado, el scope queda cerrado por construcción.
        return TiempoReparacion::query()
            ->where(fn ($q) => $q->where('activo', true)->orWhereIn('id', $yaMarcados))
            ->get();
    }

    /**
     * Los remates que existen en el catálogo, derivados de él y no escritos a mano acá: si
     * jefatura agrega un trabajo con un remate nuevo, aparece solo. Ordenados por cuántas veces
     * se usan, así el más común («funciona normal», 15 de 21) queda primero.
     *
     * @return array<int, string>
     */
    private function rematesTrabajo(): array
    {
        return TiempoReparacion::query()->where('activo', true)->pluck('trabajo')
            ->map(fn ($t) => (new TiempoReparacion(['trabajo' => $t]))->remate)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->all();
    }

    /**
     * Pestaña Cotización: VISTA PREVIA de solo lectura de lo que se le cotiza al
     * cliente (total, mano de obra, descuento) + la constancia de lo ya enviado.
     *
     * NO se edita ni se envía nada desde acá (dueño 20-08-2026: «que la cotización
     * no tenga opción de modificarse»): el presupuesto se arma y se manda en el
     * parte del técnico, que es la pantalla de la orden. Antes esta pestaña tenía su
     * propio formulario con las MISMAS filas de repuestos —dos lugares para el mismo
     * número— y era lo que el dueño mandó a sacar.
     */
    public function cotizacion(OrdenServicio $orden): View
    {
        $orden->load(['producto.precios.lista', 'repuestos', 'trabajos']);

        return view('admin.servicio-tecnico.cotizacion', [
            'orden' => $orden,
            // `cotizaciones` ya no viaja: el historial de envíos se mudó al parte del
            // técnico (dueño 20-08). Esta pantalla no lo muestra, así que tampoco lo
            // consulta — una consulta que nadie usa es la que después nadie borra.
            // Valor hora vigente (para mostrar cómo se compone la mano de obra fija).
            'precioHoraServicio' => $this->precioHoraServicio(),
            // Precio de venta del equipo: si la reparación supera el 40% se advierte.
            'precioVentaEquipo' => $this->precioVentaProducto($orden->producto),
            // Horas a cobrar de esta orden (suma de los trabajos marcados, con el tope
            // aplicado): la mano de obra se muestra de solo lectura.
            'horasTrabajo' => $orden->horasACobrar(),
            // Mano de obra que el catálogo calcula HOY = exactamente lo que va a
            // quedar al guardar. La pantalla muestra ESTO y no `$orden->mano_obra`:
            // si difieren (el SKU de la hora perdió precio en la lista oficial) el
            // guardado manda, así que mostrar el monto viejo sería prometer un total
            // que el guardado va a bajar.
            'manoObraVigente' => $this->manoObraDe($orden),
            // Por qué el catálogo NO puede calcularla (null si puede): candado del
            // envío al cliente. Ver faltaManoObra().
            'faltaManoObra' => $this->faltaManoObra($orden),
        ]);
    }

    /**
     * El descuento que corresponde guardar, como par de columnas listo para el
     * `update()`.
     *
     * EL DESCUENTO ES DECISIÓN COMERCIAL: solo lo cambia quien tiene el permiso
     * (jefatura de ventas / admin). Quien no lo tiene —el técnico que arma el
     * presupuesto— conserva el ya guardado: no puede aplicarlo ni quitarlo por más
     * que manipule el formulario, porque el permiso se chequea acá y no en la vista.
     *
     * VIVE EN UN SOLO LUGAR desde el 20-08, cuando el presupuesto pasó a poder
     * guardarse desde DOS acciones (el parte del técnico y la cotización). Con la
     * regla copiada, un día una de las dos dejaría aplicar un descuento a quien no
     * puede — y sería la copia que nadie mira.
     *
     * UN CAMPO AUSENTE NO ES UN CERO: si el formulario no trae `descuento_pct` se
     * conserva el guardado. Lo necesita el parte de una GARANTÍA, que no dibuja el
     * selector (no hay cobro) y por lo tanto no lo manda: sin esta guarda, que
     * jefatura guardara ese parte borraría un descuento en silencio. Quitarlo sigue
     * siendo posible — mandando un 0 explícito, que es lo que hace el selector.
     *
     * @param  array<string, mixed>  $data  el ya validado
     * @return array{descuento_pct: int, descuento_motivo: string|null}
     */
    private function descuentoAplicable(Request $request, OrdenServicio $orden, array $data): array
    {
        if (! $request->has('descuento_pct') || ! $request->user()->can('aplicar descuento servicio tecnico')) {
            return [
                'descuento_pct' => (int) $orden->descuento_pct,
                'descuento_motivo' => $orden->descuento_motivo,
            ];
        }

        $pct = (int) ($data['descuento_pct'] ?? 0);

        return [
            'descuento_pct' => $pct,
            // Sin descuento no hay motivo que guardar: dejarlo colgado haría que la
            // ficha explicara un descuento que no existe.
            'descuento_motivo' => $pct > 0 ? ($data['descuento_motivo'] ?? null) : null,
        ];
    }

    /**
     * Valor hora de mano de obra: precio CON IVA del producto configurado como
     * "hora de servicio tecnico" (config sku_hora_servicio), de la lista oficial
     * de ventas. Null si el SKU no existe o no tiene precio ahí (mismo criterio
     * que buscarRepuesto: Producto::precioVentaConIva).
     */
    /**
     * A qué pantalla volver después de avisarle algo al cliente: la que muestra la
     * CONSTANCIA de ese aviso.
     *
     * Desde el 20-08-2026 el historial de envíos y la tarjeta de «listo para
     * retirar» viven en el PARTE DEL TÉCNICO (el dueño las sacó de la pestaña
     * Cotización porque estaban repetidas). En GARANTÍA siguen en la pestaña
     * Cotización, que es su pantalla de envío — el parte no las incluye. Sin esto,
     * después de avisar el usuario aterrizaba en una pantalla que ya no muestra lo
     * que acababa de hacer.
     *
     * Espeja la condición con la que las vistas incluyen `_envio-historial` /
     * `_listo-retiro`: si esa condición cambia, esta también.
     */
    private function pantallaDeConstancia(OrdenServicio $orden): string
    {
        return $orden->condicion_efectiva === 'reparacion'
            ? 'admin.servicio-tecnico.reparacion'
            : 'admin.servicio-tecnico.cotizacion';
    }

    private function precioHoraServicio(): ?int
    {
        $sku = config('servicio_tecnico.sku_hora_servicio');
        if (! $sku) {
            return null;
        }

        return Producto::where('sku', $sku)->with('precios.lista')->first()?->precioVentaConIva();
    }

    /**
     * Mano de obra FIJA de una orden = horas a cobrar × valor hora. La fija jefatura (las horas
     * salen del catálogo «Costos generales de reparación», y el tope también); el técnico no la
     * edita, solo marca QUÉ hizo.
     *
     * Las horas salen del PIVOTE y no del catálogo vigente: se congelaron al guardar el parte,
     * porque jefatura calibra el catálogo con el tiempo y una orden ya cotizada no puede cambiar
     * de precio sola después — su carta le prometió un monto al cliente.
     */
    private function manoObraDe(OrdenServicio $orden): int
    {
        return $this->manoObraDeHoras($orden->trabajos->pluck('pivot.horas'));
    }

    /**
     * La misma cuenta sobre horas sueltas: la usa el guardado, que tiene que calcular el monto
     * ANTES de que el pivote exista.
     */
    private function manoObraDeHoras(iterable $horas): int
    {
        $valor = $this->precioHoraServicio();

        return $valor ? (int) round(TiempoReparacion::horasACobrar($horas) * $valor) : 0;
    }

    /**
     * Por qué NO se puede calcular la mano de obra de esta orden, o null si sí se puede. Es el
     * candado del ENVÍO al cliente (regla del dueño, 07-08-2026): una cotización que sale con la
     * mano de obra en $0 por un HUECO DE DATOS cobra de menos y nadie se entera. GUARDAR sigue
     * permitido a propósito — el técnico tiene que poder seguir poniéndole precio a los
     * repuestos mientras resuelve el resto.
     *
     * QUÉ CAMBIÓ EL 28-08 Y POR QUÉ: antes esto exigía que el TEXTO del trabajo coincidiera
     * palabra por palabra con una fila del catálogo, y eso trababa el caso más normal del
     * taller —una reparación mixta— porque ninguna frase combinada existe en el catálogo ni
     * puede existir (dueño: «la lista tendría que ser una combinación infinita de reparaciones
     * que sería muy extensa»). Ahora exige que haya al menos UN trabajo marcado. Lo que el
     * técnico escribió a mano ya no bloquea: se declara en pantalla y queda listado para que
     * jefatura lo agregue al catálogo.
     *
     * OJO: 0 horas fijadas a propósito por jefatura (el catálogo acepta `min:0`) NO son un
     * hueco: ahí la mano de obra es $0 legítima y el envío pasa. Por eso el candado mira si hay
     * trabajos MARCADOS y nunca si el monto es cero.
     */
    private function faltaManoObra(OrdenServicio $orden): ?string
    {
        if ($orden->trabajos->isEmpty()) {
            return 'marca en «Trabajo realizado» al menos un trabajo de la lista (de ahí sale la mano de obra)';
        }

        if (! $this->precioHoraServicio()) {
            return 'el código de hora de servicio técnico ('.config('servicio_tecnico.sku_hora_servicio').') no tiene precio en la lista oficial de ventas';
        }

        return null;
    }

    public function guardarReparacion(Request $request, OrdenServicio $orden): RedirectResponse
    {
        // Regla del dueño (03-08-2026): una maquina no se puede reparar si no fue
        // recepcionada en la casa matriz. Cubre los dos casos que antes eran
        // invisibles —sigue en la sucursal, o va en camino sin confirmar— y es lo
        // que obliga a que el traslado se registre: sin este candado el registro
        // seria opcional y moriria a la semana.
        if ($orden->en_transito) {
            return back()->with('status', 'No se puede trabajar esta máquina todavía: '.$orden->motivo_no_llego);
        }

        // Diagnostico final OBLIGATORIO al cerrar la orden: toda maquina que se
        // marca como 'reparado' o 'sin_solucion' debe quedar con la causa de la
        // falla (para que el informe refleje la realidad). En los estados
        // intermedios sigue siendo opcional. '' -> null por el middleware
        // ConvertEmptyStringsToNull, asi que 'Sin determinar' no pasa el required.
        $exigeDiagnostico = in_array($request->input('estado'), ['reparado', 'sin_solucion'], true);

        // «Otro — lo escribo yo» (dueño, 14-08-2026): el select manda un centinela y el texto
        // viaja aparte. El largo se corta ACÁ porque la cotización guarda su snapshot del
        // trabajo en un VARCHAR: un texto más largo pasa en SQLite y revienta en MySQL al
        // ENVIAR la cotización, o sea lejos de donde se escribió.
        $escribeElTrabajo = $request->input('trabajo_realizado') === OrdenServicio::TRABAJO_OTRO;

        // Los trabajos MARCADOS se validan contra el mismo scope que los ofrece (activos ∪ los
        // que esta orden ya tiene marcados), no con un `exists` pelado — doctrina de la bitácora
        // [2026-06-30]. Sin el segundo conjunto, re-guardar una orden histórica cuyo trabajo
        // jefatura desactivó después sería un error de validación sin salida para el técnico.
        $marcables = $this->trabajosMarcables($orden)->pluck('id')->all();

        // SE LIMPIA EL ARRAY ANTES DE VALIDAR, y esto NO es defensivo: el formulario manda un
        // `<input type="hidden" name="trabajos[]" value="">` para que la clave viaje siempre
        // (así «desmarqué todo» se distingue de «esta pantalla no preguntó»), de modo que el
        // primer elemento que llega del navegador es SIEMPRE vacío. Sin este filtro, `Rule::in`
        // lo rechaza y NINGÚN guardado del parte pasa la validación.
        //
        // Se cazó probando el payload exacto del navegador: los tests que mandaban el array
        // limpio pasaban los 18 en verde con la pantalla incapaz de guardar — el mismo hueco de
        // la bitácora [2026-07-06] (un campo que el navegador siempre envía y el test omite).
        // Candado: TrabajosMarcadosTest::test_el_payload_del_navegador_con_el_hidden_vacio_guarda.
        if ($request->has('trabajos')) {
            $request->merge([
                'trabajos' => collect($request->input('trabajos'))
                    ->filter(fn ($id) => filled($id))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }

        $data = $request->validate([
            'estado' => ['required', Rule::in(OrdenServicio::ESTADOS)],
            'trabajo_realizado' => ['nullable', 'string'],
            'trabajo_realizado_otro' => [
                Rule::requiredIf($escribeElTrabajo),
                'nullable', 'string', 'min:3', 'max:'.OrdenServicio::TRABAJO_MAX,
            ],
            'trabajos' => ['array'],
            'trabajos.*' => [Rule::in($marcables)],
            // Lo que el técnico hizo y no está en la lista. No exige mínimo ni formato: es una
            // nota, y lo que hace con ella es alimentar el catálogo de jefatura.
            'trabajos_extra' => ['nullable', 'string', 'max:'.OrdenServicio::TRABAJO_MAX],
            'causa_falla' => [Rule::requiredIf($exigeDiagnostico), 'nullable', Rule::in(OrdenServicio::CAUSAS_FALLA)],
            // Categoría de cierre: solo aplica a máquinas propias (IMP. DALI).
            'categoria' => ['nullable', Rule::in(OrdenServicio::CATEGORIAS)],
            // Descuento: desde el 20-08 el presupuesto se arma en esta misma pantalla
            // (dueño). Sigue siendo decisión COMERCIAL — abajo solo se aplica si el
            // usuario tiene el permiso.
            'descuento_pct' => ['nullable', 'integer', Rule::in(array_merge([0], OrdenServicio::DESCUENTOS_PCT))],
            'descuento_motivo' => [Rule::requiredIf((int) $request->input('descuento_pct') > 0), 'nullable', Rule::in(array_keys(OrdenServicio::DESCUENTO_MOTIVOS))],
            'fecha_aviso' => ['nullable', 'date'],
            'fecha_retiro' => ['nullable', 'date'],
            'repuestos' => ['array'],
            'repuestos.*.nombre' => ['nullable', 'string', 'max:191'],
            'repuestos.*.sku' => ['nullable', 'string', 'max:191'],
            'repuestos.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'repuestos.*.precio_unitario' => ['nullable', 'integer', 'min:0'],
        ], [
            'causa_falla.required' => 'Indica la causa de la falla (diagnóstico final) para cerrar la orden como «Reparado» o «Sin solución».',
            'trabajo_realizado_otro.required' => 'Escribe el trabajo realizado, o elige una respuesta de la lista.',
            'trabajo_realizado_otro.min' => 'Escribe el trabajo realizado con algo más de detalle: lo lee el cliente.',
            'trabajo_realizado_otro.max' => 'El trabajo realizado no puede pasar de :max caracteres (es lo que entra en la cotización).',
            'trabajos.*.in' => 'Uno de los trabajos marcados ya no está en el catálogo. Recarga la pantalla y márcalo de nuevo.',
            'trabajos_extra.max' => 'Lo escrito a mano no puede pasar de :max caracteres.',
        ]);

        // El centinela NUNCA se guarda como trabajo: se reemplaza por el texto, con los espacios
        // y saltos de línea colapsados (se pega desde WhatsApp y llega con saltos adentro).
        if ($escribeElTrabajo) {
            $data['trabajo_realizado'] = trim(preg_replace('/\s+/u', ' ', (string) ($data['trabajo_realizado_otro'] ?? '')));
        }
        unset($data['trabajo_realizado_otro']);

        // Validacion por fila: si empezo a llenar una fila, exige el nombre (min 3).
        //
        // EL PRECIO SE EXIGE SOLO AL ENVIAR (dueño 20-08, al unificar las pantallas):
        // guardar tiene que seguir siendo libre —el tecnico registra el repuesto
        // cuando lo pone y le busca el precio despues— pero lo que sale AL CLIENTE no
        // puede llevar un repuesto en $0, porque ahi se cobra de menos y nadie lo
        // nota. Es el mismo criterio con el que `faltaManoObra` bloquea el envio y no
        // el guardado.
        // `previsualizar` cuenta como enviar: la carta de la vista previa es la que va a salir,
        // asi que no puede armarse con un repuesto en $0 (si no, se corrige DESPUES de verla).
        $vaAEnviar = $request->boolean('enviar') || $request->boolean('previsualizar');

        $errores = [];
        foreach ($request->input('repuestos', []) as $i => $r) {
            $nombre = trim((string) ($r['nombre'] ?? ''));
            $precio = (int) ($r['precio_unitario'] ?? 0);
            $cantidad = (int) ($r['cantidad'] ?? 1);

            $tieneAlgo = $nombre !== '' || $precio > 0 || $cantidad > 1;
            if (! $tieneAlgo) {
                continue;
            }

            if (mb_strlen($nombre) < 3) {
                $errores["repuestos.{$i}.nombre"] = 'El repuesto necesita un nombre (mínimo 3 caracteres).';
            }
            if ($vaAEnviar && $nombre !== '' && $precio < 1) {
                $errores["repuestos.{$i}.precio_unitario"] = 'Indica el precio del repuesto (mayor a 0) antes de enviar la cotización.';
            }
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        // Estado ANTES de guardar: el aviso de «reparado» va en la TRANSICIÓN, no
        // en cada guardado. El técnico re-guarda el parte varias veces (agrega un
        // repuesto, corrige el trabajo) y sin esto ventas recibiría un aviso por
        // cada vez.
        $estadoAnterior = $orden->estado;

        // Los trabajos marcados, con sus horas del catálogo CONGELADAS acá (ver el comentario de
        // OrdenServicio::trabajos()). Se leen en una consulta y se reusan para el monto y para
        // el pivote, así el número que se guarda y el que se sincroniza no pueden discrepar.
        //
        // OJO — `has()` y no `??`: si la pantalla NO manda `trabajos`, no es «ningún trabajo»,
        // es «esta pantalla no lo preguntó», y defaultear a vacío BORRARÍA la mano de obra en
        // silencio (la familia de defecto de la bitácora [2026-08-20]). Hoy el parte siempre lo
        // manda —el `<input type="hidden">` del formulario garantiza la clave incluso sin nada
        // marcado— pero la guarda es lo que hace que mañana siga siendo verdad.
        $sincronizaTrabajos = $request->has('trabajos');
        $horasPorTrabajo = $sincronizaTrabajos
            ? TiempoReparacion::whereIn('id', $data['trabajos'] ?? [])->pluck('horas', 'id')
            : $orden->trabajos->pluck('pivot.horas', 'id');

        $orden->update([
            'estado' => $data['estado'],
            'trabajo_realizado' => $data['trabajo_realizado'] ?? null,
            'trabajos_extra' => $data['trabajos_extra'] ?? null,
            'causa_falla' => $data['causa_falla'] ?? null,
            // La categoría solo se guarda para máquinas propias (IMP. DALI).
            'categoria' => $orden->es_propia ? ($data['categoria'] ?? null) : null,
            // Mano de obra FIJA por los trabajos marcados (horas a cobrar × valor hora): el
            // técnico no la ingresa ni la edita, solo marca qué hizo.
            'mano_obra' => $this->manoObraDeHoras($horasPorTrabajo),
            'fecha_aviso' => $data['fecha_aviso'] ?? null,
            'fecha_retiro' => $data['fecha_retiro'] ?? null,
        ] + $this->descuentoAplicable($request, $orden, $data));

        if ($sincronizaTrabajos) {
            // sync con las horas en el pivote: reemplaza el conjunto y deja las horas de HOY en
            // los que se marcan ahora. Los que ya estaban y siguen marcados también se
            // re-escriben, que es lo correcto: es el mismo guardado del parte, y el monto que
            // acaba de calcularse arriba usó estas mismas horas.
            $orden->trabajos()->sync(
                $horasPorTrabajo->mapWithKeys(fn ($h, $id) => [$id => ['horas' => $h]])->all()
            );
            $orden->load('trabajos');
        }

        // Reemplazo total de los repuestos: se borran y se recrean los que
        // tengan nombre (las filas vacias del formulario se ignoran).
        $orden->repuestos()->delete();
        foreach ($data['repuestos'] ?? [] as $r) {
            if (empty($r['nombre'])) {
                continue;
            }
            $orden->repuestos()->create([
                'nombre' => $r['nombre'],
                // SKU del catálogo si el técnico lo eligió del buscador; null si lo
                // escribió a mano. La línea del documento tributario lo necesita
                // (regla 4 de Contabilidad: repuestos con su código de catálogo).
                'sku' => $r['sku'] ?? null,
                'cantidad' => $r['cantidad'] ?? 1,
                'precio_unitario' => $r['precio_unitario'] ?? 0,
            ]);
        }

        // Avisos de CIERRE de la orden. Van en la TRANSICIÓN y son acción
        // SECUNDARIA (try/catch): un aviso que falle no puede hacer perder el parte
        // del técnico, que es el dato real.
        $cerroAhora = fn (string $estado) => $data['estado'] === $estado && $estadoAnterior !== $estado;

        // Reparado: el equipo quedó listo y ventas tiene que llamar al cliente.
        if ($cerroAhora('reparado')) {
            try {
                $orden->notificarReparado($request->user());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Sin solución: al CLIENTE por correo + a ventas (decisión del dueño 30-07).
        // El correo va PRIMERO porque su resultado entra en el aviso interno: si no
        // salió, el aviso pide llamarlo en vez de dar por hecho que ya sabe.
        $avisadoAlCliente = null;

        if ($cerroAhora('sin_solucion')) {
            if (filled($orden->cliente_email)) {
                try {
                    Mail::to($orden->cliente_email)->send(new SinSolucionCliente($orden));
                    $avisadoAlCliente = true;
                } catch (\Throwable $e) {
                    report($e);
                    $avisadoAlCliente = false;
                }
            }

            try {
                $orden->notificarSinSolucion($request->user(), $avisadoAlCliente);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Al técnico se le dice qué pasó con el correo al cliente, no solo que
        // guardó: es la única pantalla donde se entera de que el aviso salió (o de
        // que hay que llamar al cliente porque no salió).
        $aviso = match ($avisadoAlCliente) {
            true => " Se le avisó a {$orden->cliente_email}.",
            false => ' No se pudo enviar el correo al cliente: hay que llamarlo.',
            null => $cerroAhora('sin_solucion') ? ' La orden no tiene correo del cliente: hay que llamarlo.' : '',
        };

        // El botón «Enviar cotización» es un submit de ESTE formulario con enviar=1
        // (dueño 20-08: lo quiere en este pie): se guarda primero y se manda después,
        // así lo que sale al cliente es lo que estaba en pantalla. Pegado a
        // «Guardar», mandar el snapshot anterior sin darse cuenta era demasiado
        // fácil. Si el envío no procede (etapa posterior, sin correo, total $0),
        // enviarCotizacion lo explica y lo guardado no se pierde.
        if ($request->boolean('enviar')) {
            return $this->enviarCotizacion($request, $orden->fresh());
        }

        // VISTA PREVIA (dueño 20-08-2026): el mismo guardado, pero en vez de mandar la carta
        // se vuelve con la bandera que abre la ventana con la carta ya armada. El envio de
        // verdad sale de ahi, contra la ruta de siempre — asi lo que se ve y lo que sale son
        // el mismo snapshot, y no hay una segunda forma de enviar que se pueda desincronizar.
        if ($request->boolean('previsualizar')) {
            return redirect()->route('admin.servicio-tecnico.reparacion', $orden)
                ->with('cotizacion_previa', true)
                ->with('status', "Guardado. Revisa la carta antes de enviarla.");
        }

        // Se queda en la MISMA pantalla de reparación (no vuelve al listado): así
        // el técnico puede enviar la cotización enseguida —"guarda antes de
        // enviar"— sin perder la página y con los datos ya guardados a la vista.
        return redirect()->route('admin.servicio-tecnico.reparacion', $orden)
            ->with('status', "Reparación de la orden {$orden->folio} actualizada.".$aviso);
    }

    /**
     * Envía la COTIZACIÓN al cliente (P-M12-02, fase correo): congela un
     * snapshot de lo guardado en la orden, manda la carta formal con el link
     * firmado de respuesta y avisa a los roles internos. El correo es acción
     * secundaria (try/catch): si el SMTP falla, la cotización queda registrada
     * con `correo_enviado_at` null y aparece el botón "Reintentar".
     *
     * Aterriza en una ruta con NOMBRE y no en `back()`: el botón es un submit del
     * formulario de guardar, y ese POST puede llegar sin cabecera Referer — con
     * `back()` el usuario caería en el Inicio. El destino es la pantalla que
     * muestra la constancia (ver pantallaDeConstancia): desde el 20-08 el parte del
     * técnico, que es de donde se envía y donde queda el historial.
     */
    /**
     * LA CARTA, TAL COMO LA VA A RECIBIR EL CLIENTE, antes de mandarla (dueño 20-08-2026:
     * «hay alguna posibilidad que haya una ventana previa donde se vea la cotizacion y despues
     * se pueda enviar»). Sale del MISMO snapshot y de la MISMA plantilla del correo, sobre un
     * borrador que no toca la base: una vista previa dibujada aparte mostraria un total y el
     * cliente recibiria otro.
     *
     * El link de respuesta va inerte: en la vista previa no hay token que aceptar todavia.
     */
    public function previsualizarCotizacion(OrdenServicio $orden): View
    {
        return view('emails.taller.cotizacion', [
            'cotizacion' => OrdenServicioCotizacion::borradorDesde($orden->load('repuestos')),
            'urlRespuesta' => '#',
        ]);
    }
    public function enviarCotizacion(Request $request, OrdenServicio $orden): RedirectResponse
    {
        $volver = fn (string $mensaje) => redirect()
            ->route($this->pantallaDeConstancia($orden), $orden)
            ->with('status', $mensaje);

        // Mismas condiciones que habilitan el botón (defensa server-side).
        if ($orden->condicion_efectiva !== 'reparacion') {
            return $volver('Equipo en garantía vigente: no se cotiza (no hay cobro).');
        }
        // Etapas PREVIAS: enviar la cotización ES pasar el presupuesto, así que
        // la orden salta sola a «Cotización» (dueño 06-08: antes había que
        // peregrinar por Parte del técnico solo para cambiar la etapa). Las
        // etapas POSTERIORES se respetan: re-cotizar ahí es decisión del técnico.
        if (in_array($orden->estado, ['recibido', 'en_revision'], true)) {
            $orden->update(['estado' => 'cotizacion']);
        }
        if ($orden->estado !== 'cotizacion') {
            return $volver('La orden ya pasó la etapa de cotización: para re-cotizar, vuélvela a «Cotización» en Parte del técnico.');
        }
        if (blank($orden->cliente_email)) {
            return $volver('La orden no tiene correo del cliente: agrégalo en los datos de recepción.');
        }
        if ((int) $orden->costo_total <= 0) {
            return $volver('La cotización está en $0: pon los precios de los repuestos y vuelve a enviar.');
        }
        // No sale una cotización cuya mano de obra el catálogo no puede calcular:
        // el cliente recibiría un total sin mano de obra (cobro de menos silencioso).
        if ($falta = $this->faltaManoObra($orden)) {
            return $volver('No se puede enviar la cotización: '.$falta.'.');
        }

        $cotizacion = OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $request->user());

        $correoOk = $this->mandarCartaCotizacion($cotizacion);

        $cotizacion->avisarInternos('cotizacion.enviada', ['enviada_por' => $request->user()->name]);

        return $volver($correoOk
            ? "Cotización enviada a {$cotizacion->cliente_email} (orden {$orden->folio})."
            : 'La cotización quedó registrada, pero el correo NO salió. Usa «Reintentar correo».');
    }

    /**
     * Reintenta el correo de una cotización cuyo envío falló (mismo snapshot y
     * mismo link; no crea fila nueva). Solo tiene sentido si sigue vigente.
     */
    public function reintentarCorreoCotizacion(OrdenServicio $orden, int $cotizacionId): RedirectResponse
    {
        // Id plano (sin binding implícito: el route key del modelo es el token,
        // que solo viaja en el link público del cliente).
        $cotizacion = OrdenServicioCotizacion::findOrFail($cotizacionId);
        abort_unless($cotizacion->orden_servicio_id === $orden->id, 404);

        if ($cotizacion->correo_enviado_at || ! $cotizacion->esRespondible()) {
            return back()->with('status', 'Esa cotización ya no necesita reintento (correo enviado o ya no vigente).');
        }

        $correoOk = $this->mandarCartaCotizacion($cotizacion);

        return back()->with('status', $correoOk
            ? "Correo de la cotización reenviado a {$cotizacion->cliente_email}."
            : 'El correo volvió a fallar. Revisa el correo del cliente o inténtalo más tarde.');
    }

    /**
     * RESPALDO del aviso de retiro tras un NO ACEPTO. Desde el 07-08 la cita
     * sale AUTOMÁTICA al momento del rechazo (CotizacionPublicoController::
     * citarRetiroAutomatico); este botón queda para cuando ese correo falló
     * (SMTP caído) o para rechazos anteriores al cambio. Un solo aviso por
     * cotización (queda registrado quién y cuándo), y solo si el rechazo sigue
     * siendo la última palabra — si después se envió una cotización más nueva,
     * la conversación es otra. Al salir el correo se avisa a los roles internos
     * por la campanita ('cotizacion.retiro_avisado').
     */
    public function avisarRetiroSinReparar(Request $request, OrdenServicio $orden, int $cotizacionId): RedirectResponse
    {
        // Id plano, igual que reintentar: el route key del modelo es el token.
        $cotizacion = OrdenServicioCotizacion::findOrFail($cotizacionId);
        abort_unless($cotizacion->orden_servicio_id === $orden->id, 404);

        if ($cotizacion->estado !== 'rechazada') {
            return back()->with('status', 'Ese aviso es solo para cotizaciones que el cliente NO aceptó.');
        }
        if ($cotizacion->retiro_avisado_at) {
            return back()->with('status', 'Al cliente ya se le avisó que puede pasar a retirar (el '.$cotizacion->retiro_avisado_at->format('d-m-Y H:i').').');
        }
        if (OrdenServicioCotizacion::where('orden_servicio_id', $orden->id)->where('id', '>', $cotizacion->id)->exists()) {
            return back()->with('status', 'Hay una cotización más reciente para esta orden: espera la respuesta del cliente a esa.');
        }
        if (blank($cotizacion->cliente_email)) {
            return back()->with('status', 'La cotización no tiene correo del cliente: hay que llamarlo.');
        }

        // Misma carta que el aviso automático: cita al día hábil siguiente.
        $retiro = \App\Support\DiasHabiles::siguiente();

        try {
            Mail::to($cotizacion->cliente_email)->send(new RetiroSinReparacion($cotizacion->load('orden'), $retiro));
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        if ($ok) {
            $cotizacion->update([
                'retiro_avisado_at' => now(),
                'retiro_avisado_por' => $request->user()->id,
            ]);
            $cotizacion->avisarInternos('cotizacion.retiro_avisado', [
                'retiro_dia' => \App\Support\DiasHabiles::rotulo($retiro),
                'avisado_por' => $request->user()->name,
            ]);
        }

        return back()->with('status', $ok
            ? "Se le avisó a {$cotizacion->cliente_email} que puede pasar a retirar su equipo (orden {$orden->folio})."
            : 'No se pudo enviar el correo ahora. Revisa el correo del cliente o inténtalo más tarde.');
    }

    /**
     * «Tu equipo está listo, pásalo a retirar» (dueño 07-08): el TÉCNICO cierra
     * su parte avisándole al cliente por correo, con el monto que el cliente
     * aceptó y la instrucción de pagar en SALA DE VENTAS al retirar. El taller no
     * coordina plata: manda la cotización, repara si el cliente acepta y avisa.
     *
     * Exige la orden en etapa «Reparado»: «está listo» significa trabajo cerrado
     * con su diagnóstico (eso lo garantiza guardarReparacion). Un aviso por orden
     * —queda registrado quién y cuándo—, y si el SMTP falla no se estampa nada,
     * así se puede reintentar.
     */
    public function avisarListoParaRetiro(Request $request, OrdenServicio $orden): RedirectResponse
    {
        // Vuelve a donde está la tarjeta que acaba de cambiar (parte del técnico en
        // reparación, pestaña Cotización en garantía). Ver pantallaDeConstancia.
        $volver = fn (string $mensaje) => redirect()
            ->route($this->pantallaDeConstancia($orden), $orden)
            ->with('status', $mensaje);

        if ($orden->estado !== 'reparado') {
            return $volver('Marca la orden como «Reparado» en Parte del técnico (con la causa de la falla) antes de avisarle al cliente.');
        }
        if ($orden->listo_avisado_at) {
            return $volver('Al cliente ya se le avisó que puede retirar (el '.$orden->listo_avisado_at->format('d-m-Y H:i').').');
        }
        if (blank($orden->cliente_email)) {
            return $volver('La orden no tiene correo del cliente: hay que llamarlo.');
        }

        // El monto que se cobra es el que el cliente ACEPTÓ (snapshot), no el de
        // la orden viva. En garantía no hay cobro y la carta lo dice.
        $aceptada = $orden->cotizaciones()->where('estado', 'aceptada')->latest('id')->first();

        try {
            Mail::to($orden->cliente_email)->send(new EquipoListoParaRetiro($orden, $aceptada));
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        if (! $ok) {
            return $volver('No se pudo enviar el correo ahora. Revisa el correo del cliente o inténtalo más tarde.');
        }

        $orden->update([
            'listo_avisado_at' => now(),
            'listo_avisado_por' => $request->user()->id,
            // «Fecha de aviso al cliente» del parte: si estaba vacía, la llena
            // este aviso (un solo dato, sin escribirlo dos veces).
            'fecha_aviso' => $orden->fecha_aviso ?? now()->toDateString(),
        ]);

        // A ventas sobre todo: el cliente va a llegar al mostrador a pagar.
        $esGarantia = $orden->condicion_efectiva === 'garantia';
        $equipo = trim($orden->tipo_equipo_label.' '.($orden->modelo ?? ''));
        $datos = [
            'folio' => $orden->folio,
            'cliente' => $orden->cliente_nombre,
            'equipo' => $equipo !== '' ? $equipo : '—',
            // {cobro} SIEMPRE relleno: un placeholder sin dato queda crudo.
            'cobro' => match (true) {
                $esGarantia => 'Sin costo (garantía).',
                $aceptada !== null => 'Cobrar en sala de ventas al retiro: $'.number_format((int) $aceptada->costo_total, 0, ',', '.').'.',
                default => 'Sin cotización aceptada: el cobro se coordina en sala de ventas.',
            },
            'avisado_por' => $request->user()->name,
            'url' => route('admin.servicio-tecnico.show', $orden),
        ];
        $dispatcher = app(NotificacionDispatcher::class);
        User::role(OrdenServicioCotizacion::ROLES_AVISO)->get()->unique('id')
            ->each(fn (User $u) => $dispatcher->despachar('taller.listo_para_retiro', $orden, $u, $datos));

        return $volver("Se le avisó a {$orden->cliente_email} que su equipo está listo para retirar (orden {$orden->folio}).");
    }

    /**
     * Garantía: envía al cliente el DETALLE del trabajo realizado, SIN cobro.
     * Reemplaza a la cotización cuando el equipo está en garantía vigente: el
     * cliente no paga, solo recibe el resumen de lo que se hizo (trabajo, causa y
     * repuestos usados, sin precios). Correo secundario (try/catch): si el SMTP
     * falla se avisa sin tumbar nada.
     */
    public function enviarDetalleTrabajo(OrdenServicio $orden): RedirectResponse
    {
        if ($orden->condicion_efectiva !== 'garantia') {
            return back()->with('status', 'El equipo no está en garantía vigente: usa «Enviar cotización».');
        }
        if (blank($orden->cliente_email)) {
            return back()->with('status', 'La orden no tiene correo del cliente: agrégalo en los datos de recepción.');
        }
        if (blank($orden->trabajo_realizado)) {
            return back()->with('status', 'Registra primero el trabajo realizado en «Parte del técnico».');
        }

        try {
            Mail::to($orden->cliente_email)->send(new DetalleTrabajoCliente($orden->load('repuestos')));
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        // Salió el correo → campanita a los mismos roles que en 'cotizacion.enviada'
        // (dueño 06-08: la ruta de la máquina debe verse también cuando es garantía
        // y no hay cobro). Misma audiencia de "ruta completa": ROLES_AVISO.
        if ($ok) {
            $equipo = trim($orden->tipo_equipo_label.' '.($orden->modelo ?? ''));
            $datos = [
                'folio' => $orden->folio,
                'cliente' => $orden->cliente_nombre,
                'equipo' => $equipo !== '' ? $equipo : '—',
                'enviado_por' => request()->user()->name,
                'url' => route('admin.servicio-tecnico.show', $orden),
            ];
            $dispatcher = app(NotificacionDispatcher::class);
            User::role(OrdenServicioCotizacion::ROLES_AVISO)->get()->unique('id')
                ->each(fn (User $u) => $dispatcher->despachar('garantia.detalle_enviado', $orden, $u, $datos));
        }

        return back()->with('status', $ok
            ? "Detalle del trabajo enviado a {$orden->cliente_email} (orden {$orden->folio}, sin costo por garantía)."
            : 'No se pudo enviar el correo ahora. Revisa el correo del cliente o inténtalo más tarde.');
    }

    /** Manda la carta y estampa `correo_enviado_at`; false si el SMTP falló. */
    private function mandarCartaCotizacion(OrdenServicioCotizacion $cotizacion): bool
    {
        try {
            Mail::to($cotizacion->cliente_email)->send(new CotizacionCliente($cotizacion));
            $cotizacion->update(['correo_enviado_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Autoriza la reparación tras coordinar el pago (P-M12-02): sobre la
     * cotización ACEPTADA por el cliente, ventas registra la forma de pago
     * (obligatoria; puede ser "paga al retiro"), un comprobante opcional (imagen
     * de la transferencia, al disco privado) y una nota, y AUTORIZA → avisa al
     * técnico para que proceda. La info de pago queda visible para todo el
     * equipo (transparencia pedida por el dueño). No cambia el estado de la orden.
     */
    public function autorizarReparacion(Request $request, OrdenServicio $orden): RedirectResponse
    {
        $cotizacion = $orden->cotizaciones()->where('estado', 'aceptada')->latest('id')->first();

        if (! $cotizacion) {
            return back()->with('status', 'No hay una cotización aceptada por el cliente para autorizar.');
        }
        // La aceptada debe ser la ÚLTIMA palabra (QA 07-08): si después se
        // re-cotizó, lo que vale es la respuesta a esa cotización más nueva —
        // autorizar la vieja cobraría un trato que el cliente ya no tiene.
        if (OrdenServicioCotizacion::where('orden_servicio_id', $orden->id)->where('id', '>', $cotizacion->id)->exists()) {
            return back()->with('status', 'Hay una cotización más reciente que esa aceptación: lo que vale es la respuesta del cliente a la última.');
        }
        if ($cotizacion->esta_autorizada) {
            return back()->with('status', 'Esta reparación ya estaba autorizada.');
        }

        $data = $request->validate([
            'pago_forma' => ['required', Rule::in(array_keys(OrdenServicioCotizacion::FORMAS_PAGO))],
            'pago_nota' => ['nullable', 'string', 'max:1000'],
            'comprobante' => ['nullable', 'image', 'max:8192'], // 8 MB; imagen de la transferencia
        ]);

        $ruta = $request->hasFile('comprobante')
            ? \App\Support\ImagenComprimida::guardar($request->file('comprobante'), "ordenes-servicio/comprobantes/{$orden->id}")
            : null;

        $cotizacion->update([
            'pago_forma' => $data['pago_forma'],
            'pago_nota' => $data['pago_nota'] ?? null,
            'pago_comprobante_ruta' => $ruta,
            'autorizada_at' => now(),
            'autorizada_por' => $request->user()->id,
        ]);

        // Aviso de PLATA: va a ventas/admin, NO al técnico (dueño 07-08: el taller
        // no coordina cobros y repara con la sola aceptación del cliente).
        $cotizacion->refresh()->avisarInternos('cotizacion.autorizada', [
            'pago' => $cotizacion->pago_forma_label,
            'autorizada_por' => $request->user()->name,
        ], OrdenServicioCotizacion::ROLES_AVISO_PAGO);

        return back()->with('status', "Pago registrado y reparación autorizada (orden {$orden->folio}).");
    }

    /**
     * Sirve el comprobante de pago de una cotización desde el disco PRIVADO
     * `local` (dato sensible: transferencia). Solo con sesión y acceso al ST.
     */
    public function comprobanteCotizacion(OrdenServicioCotizacion $cotizacion): StreamedResponse
    {
        // Dato sensible (transferencia): misma visibilidad por cartera que la ficha.
        abort_unless($cotizacion->orden->esVisiblePara(auth()->user()), 403);
        abort_unless(
            $cotizacion->pago_comprobante_ruta && Storage::disk('local')->exists($cotizacion->pago_comprobante_ruta),
            404
        );

        return Storage::disk('local')->response($cotizacion->pago_comprobante_ruta);
    }

    /**
     * Autocompletado de cliente por RUT o razon social (JSON). Reutiliza la
     * normalizacion de rut de Cliente: el rut se guarda sin puntos (12345678-9),
     * asi que limpiamos la consulta igual antes del LIKE. Limite 15 + minimo 2
     * caracteres para no escanear toda la tabla de clientes.
     */
    public function buscarCliente(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $rutQ = preg_replace('/[.\s]/', '', $q);

        $clientes = Cliente::query()
            ->where(fn (Builder $w) => $w
                ->where('razon_social', 'like', "%{$q}%")
                ->orWhere('rut', 'like', "%{$rutQ}%"))
            ->orderBy('razon_social')
            ->limit(15)
            ->get(['id', 'rut', 'razon_social', 'telefono']);

        return response()->json($clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'rut' => $c->rut,
            'razon_social' => $c->razon_social,
            'telefono' => $c->telefono,
            'label' => ($c->rut ? $c->rut.' — ' : '').$c->razon_social,
        ]));
    }

    /**
     * Autocompletado de producto Dali (el "codigo" del equipo) por SKU o nombre.
     * Mismo patron que buscarCliente: minimo 2 caracteres, limite 15.
     */
    public function buscarProducto(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $productos = Producto::query()
            ->where(fn (Builder $w) => $w
                ->where('sku', 'like', "%{$q}%")
                ->orWhere('nombre', 'like', "%{$q}%"))
            ->orderBy('sku')
            ->limit(15)
            ->get(['id', 'sku', 'nombre']);

        return response()->json($productos->map(fn (Producto $p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'nombre' => $p->nombre,
            'label' => $p->sku.' — '.$p->nombre,
        ]));
    }

    /**
     * Autocompletado de repuestos (JSON). El catalogo es el historial de
     * nombres ya usados en reparaciones (distinct) + la lista base de
     * repuestos comunes del taller, para que el campo sugiera desde el primer
     * uso. Devuelve nombres unicos (case-insensitive), minimo 2 caracteres,
     * limite 15. El campo sigue siendo de texto libre: la sugerencia solo
     * rellena, no obliga.
     */
    public function buscarRepuesto(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // 1) Catalogo Dali (productos): por codigo (SKU) o nombre. Trae el precio
        //    de venta CON IVA que encuentre (sugerencia editable; el tecnico ajusta).
        $catalogo = Producto::query()
            ->where(fn (Builder $w) => $w
                ->where('sku', 'like', "%{$q}%")
                ->orWhere('nombre', 'like', "%{$q}%"))
            ->with('precios.lista')
            ->orderBy('sku')
            ->limit(10)
            ->get(['id', 'sku', 'nombre'])
            ->map(fn (Producto $p) => [
                'nombre' => $p->nombre,
                'sku' => $p->sku,
                // De la lista oficial de ventas; null si no está ahí (se escribe a mano).
                'precio' => $p->precioVentaConIva(),
            ]);

        // 2) Historial de reparaciones + repuestos comunes (solo nombres).
        $historial = OrdenServicioRepuesto::query()
            ->where('nombre', 'like', "%{$q}%")
            ->distinct()
            ->orderBy('nombre')
            ->limit(10)
            ->pluck('nombre');

        $comunes = collect(self::REPUESTOS_COMUNES)
            ->filter(fn (string $n) => mb_stripos($n, $q) !== false);

        // No repetir un nombre que ya vino del catalogo (ahi trae codigo + precio).
        $yaEnCatalogo = $catalogo->pluck('nombre')->map(fn ($n) => mb_strtolower($n))->all();

        $nombres = $historial->merge($comunes)
            ->map(fn (string $n) => trim($n))
            ->filter()
            ->unique(fn (string $n) => mb_strtolower($n))
            ->reject(fn (string $n) => in_array(mb_strtolower($n), $yaEnCatalogo, true))
            ->take(10)
            ->map(fn (string $n) => ['nombre' => $n, 'sku' => null, 'precio' => null]);

        // Catalogo primero (con codigo + precio), luego los nombres sueltos.
        return response()->json($catalogo->concat($nombres)->take(15)->values());
    }

    // --- Helpers --------------------------------------------------------

    /**
     * Query de ordenes con los filtros del request aplicados. La busqueda libre
     * matchea datos del equipo y, via relacion, la razon social/rut del cliente.
     */
    private function filteredQuery(Request $request): Builder
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'estado' => ['nullable', Rule::in(OrdenServicio::ESTADOS)],
            // Varios estados a la vez (CSV): lo usan las tarjetas del Inicio que
            // agrupan etapas (ej. Recibido + Cotización en una sola card).
            'estados' => ['nullable', 'string', 'max:191'],
            'tipo_equipo' => ['nullable', Rule::in(OrdenServicio::TIPOS)],
            'facturacion' => ['nullable', Rule::in(OrdenServicio::FACTURACION)],
            // Sucursal de RECEPCION (donde se ingreso el equipo). El historial es
            // compartido por las 3 sucursales; este filtro deja ver "que se ingreso
            // en Coquimbo/Abate/Mirador". La reparacion siempre es en Mirador.
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')],
            // Periodo del historial (cards Año → Mes del listado).
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            // Sobre qué fecha aplica el período: 'ingreso' (default) o 'retiro'
            // (para "Entregadas del mes": las que el cliente retiró en el mes).
            'por' => ['nullable', Rule::in(['ingreso', 'retiro'])],
        ]);

        return OrdenServicio::query()
            ->when($f['q'] ?? null, function (Builder $qb, $q) {
                $rutQ = preg_replace('/[.\s]/', '', $q);

                $qb->where(function (Builder $w) use ($q, $rutQ) {
                    // El folio ahora es el codigo unico (ST-XXXXXXXX): se busca por ese.
                    $w->where('codigo', 'like', "%{$q}%")
                        ->orWhere('cliente_nombre', 'like', "%{$q}%")
                        ->orWhere('cliente_rut', 'like', "%{$rutQ}%")
                        ->orWhere('modelo', 'like', "%{$q}%")
                        ->orWhere('numero_serie', 'like', "%{$q}%")
                        ->orWhereHas('producto', fn (Builder $p) => $p
                            ->where('sku', 'like', "%{$q}%")
                            ->orWhere('nombre', 'like', "%{$q}%"));
                });
            })
            ->when($f['estado'] ?? null, fn (Builder $qb, $v) => $qb->where('estado', $v))
            ->when($f['estados'] ?? null, function (Builder $qb, $v) {
                // CSV → solo estados válidos (ignora basura); vacío no filtra.
                $lista = array_values(array_intersect(explode(',', $v), OrdenServicio::ESTADOS));
                if ($lista) {
                    $qb->whereIn('estado', $lista);
                }
            })
            ->when($f['tipo_equipo'] ?? null, fn (Builder $qb, $v) => $qb->where('tipo_equipo', $v))
            ->when($f['facturacion'] ?? null, fn (Builder $qb, $v) => $qb->where('facturacion', $v))
            ->when($f['sucursal_id'] ?? null, fn (Builder $qb, $v) => $qb->where('sucursal_id', $v))
            // Periodo Año/Mes. Mes sin año asume el año actual.
            //
            // Rango SEMIABIERTO sobre la columna cruda (`>= inicio` y `< inicio del
            // periodo siguiente`), no `whereDate`: acá decía que whereDate "usa el
            // indice" y es al revés — en MySQL compila a `date(fecha_ingreso) >= ?`,
            // y envolver la columna en una función deja el índice fuera de juego,
            // así que cada clic de mes leía la tabla entera, dos veces (el count()
            // del paginador + la página). Medido con 9.940 órdenes: 15 ms que
            // crecían con la tabla → 1 ms constante.
            //
            // Semiabierto y no BETWEEN por el borde superior: en los tests (SQLite)
            // la columna guarda '2026-07-31 00:00:00', que como TEXTO es mayor que
            // '2026-07-31', y el BETWEEN dejaba las órdenes del último día del mes
            // fuera (lo cazó el candado del borde). `< '2026-08-01'` es correcto en
            // los dos motores, con y sin hora.
            ->when($f['anio'] ?? $f['mes'] ?? null, function (Builder $qb) use ($f) {
                $anio = (int) ($f['anio'] ?? \App\Support\FechaNegocio::ahora()->year);
                $mes = isset($f['mes']) ? (int) $f['mes'] : null;
                $desde = Carbon::create($anio, $mes ?? 1, 1);
                $siguiente = $mes ? $desde->copy()->addMonth() : $desde->copy()->addYear();
                // Por defecto el período es por fecha de ingreso; 'retiro' lo aplica
                // sobre fecha_retiro (para "Entregadas del mes").
                $col = ($f['por'] ?? 'ingreso') === 'retiro' ? 'fecha_retiro' : 'fecha_ingreso';
                $qb->where($col, '>=', $desde->toDateString())
                    ->where($col, '<', $siguiente->toDateString());
            });
    }

    /**
     * Resumen para las cards de navegacion del historial (Año → Mes) del
     * listado. Agregado en SQL (no en PHP): `fecha_ingreso` se guarda
     * 'YYYY-MM-DD', y SUBSTR es idéntico en MySQL y SQLite (a diferencia de
     * YEAR()/EXTRACT, que no son portables) — evita traer TODAS las órdenes
     * de la historia a PHP en cada carga del listado (crecía sin cota;
     * perf, hallazgo 2026-07-24).
     */
    private function resumenHistorial(?int $anioActivo): array
    {
        // Estos conteos son los MISMOS para todos y cambian solo cuando entra o se
        // edita una orden, asi que se cachean con una version que sube por evento
        // del modelo (OrdenServicio::invalidarHistorial): un ingreso nuevo se ve al
        // instante, y entre ingresos no se vuelve a barrer la tabla.
        $version = OrdenServicio::versionHistorial();

        // Reparacion = total - garantia (igual que condicion_efectiva: las
        // ordenes viejas con facturacion NULL cuentan como reparacion).
        //
        // El GROUP BY por año necesita SUBSTR sobre la columna, y eso deja el indice
        // de `fecha_ingreso` fuera de juego -> barrido completo. Por eso justamente
        // se cachea: es la consulta mas cara del listado y la que menos cambia.
        $anios = Cache::remember("dg.st.historial.anios.$version", now()->addDay(), fn () => OrdenServicio::query()
            ->whereNotNull('fecha_ingreso')
            ->selectRaw("SUBSTR(fecha_ingreso, 1, 4) as anio, COUNT(*) as total, SUM(CASE WHEN facturacion = 'garantia' THEN 1 ELSE 0 END) as garantia")
            ->groupBy('anio')
            ->get()
            ->mapWithKeys(fn ($fila) => [
                (int) $fila->anio => [
                    'total' => (int) $fila->total,
                    'garantia' => (int) $fila->garantia,
                    'reparacion' => (int) $fila->total - (int) $fila->garantia,
                ],
            ])
            ->sortKeysDesc());

        $meses = null;
        if ($anioActivo !== null) {
            $meses = Cache::remember("dg.st.historial.meses.$anioActivo.$version", now()->addDay(), function () use ($anioActivo) {
                // El filtro va por RANGO SEMIABIERTO sobre la columna cruda, no con
                // `SUBSTR(fecha_ingreso,1,4) = ?`: envolver la columna en una
                // funcion anula el indice y obliga a leer la tabla entera. Con el
                // rango, MySQL entra por el indice y solo recorre ese año; el SUBSTR
                // queda unicamente en el SELECT, sobre las filas ya acotadas.
                // Semiabierto por el borde: en SQLite la columna guarda hora.
                $porMes = OrdenServicio::query()
                    ->where('fecha_ingreso', '>=', "$anioActivo-01-01")
                    ->where('fecha_ingreso', '<', ($anioActivo + 1).'-01-01')
                    ->selectRaw('SUBSTR(fecha_ingreso, 6, 2) as mes, COUNT(*) as total')
                    ->groupBy('mes')
                    ->pluck('total', 'mes');

                return collect(range(1, 12))
                    ->mapWithKeys(fn (int $m) => [$m => (int) ($porMes[str_pad((string) $m, 2, '0', STR_PAD_LEFT)] ?? 0)])
                    ->all();
            });
        }

        return ['anios' => $anios, 'meses' => $meses];
    }

    /**
     * Periodo del informe: un mes puntual o el año completo, opcionalmente
     * acotado a un tipo de equipo. Sin parametros = mes actual y todos los
     * tipos; con solo `anio` = ese año completo ("Todo el año").
     * Devuelve [desde Y-m-d, hasta Y-m-d, anio, mes|null, tipo|null].
     */
    private function periodoInforme(Request $request): array
    {
        $v = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'tipo' => ['nullable', Rule::in(OrdenServicio::TIPOS)],
        ]);

        $anio = isset($v['anio']) ? (int) $v['anio'] : null;
        $mes = isset($v['mes']) ? (int) $v['mes'] : null;
        $tipo = $v['tipo'] ?? null;

        if ($anio === null) {
            $anio = \App\Support\FechaNegocio::ahora()->year;
            $mes ??= \App\Support\FechaNegocio::ahora()->month;
        }

        $desde = Carbon::create($anio, $mes ?? 1, 1);
        $hasta = $mes ? $desde->copy()->endOfMonth() : $desde->copy()->endOfYear();

        return [$desde->toDateString(), $hasta->toDateString(), $anio, $mes, $tipo];
    }

    /**
     * Ordenes cuyo ingreso cae dentro del rango [desde, hasta] (Y-m-d),
     * opcionalmente de un solo tipo de equipo. whereDate en ambos bordes:
     * portable (MySQL 5.7 / SQLite) y usa el indice de fecha_ingreso.
     */
    private function ordenesDelPeriodo(string $desde, string $hasta, ?string $tipo = null): Builder
    {
        return OrdenServicio::query()
            ->whereDate('fecha_ingreso', '>=', $desde)
            ->whereDate('fecha_ingreso', '<=', $hasta)
            ->when($tipo, fn (Builder $qb, $t) => $qb->where('tipo_equipo', $t));
    }

    /**
     * Trabajos de terreno del informe industrial: con fecha dentro del rango y
     * agendados o realizados. Se EXCLUYEN los cancelados y las solicitudes sin
     * coordinar (estado 'solicitado'), que no son trabajo del periodo.
     *
     * Es el equivalente de ordenesDelPeriodo() para la agenda, y existe como
     * metodo —no como closure dentro del informe— porque el Excel exporta el
     * mismo universo: una sola definicion para los dos.
     */
    private function trabajosDelPeriodo(string $desde, string $hasta): Builder
    {
        return AgendaTrabajo::query()
            ->whereNotNull('fecha')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            // 'no_realizado' entra desde el 14-08: el técnico FUE y no se pudo
            // hacer, así que es trabajo del período igual que un realizado. Si
            // quedara fuera, esos trabajos desaparecerían del informe y del Excel
            // —el peor resultado: el que no se pudo hacer es justo el que hay que
            // mirar—. Los 'cancelado' y los 'solicitado' siguen afuera: nunca
            // llegaron a ser trabajo de un día.
            ->whereIn('estado', ['agendado', 'realizado', 'no_realizado']);
    }

    /** Rotulo del periodo elegido: «Agosto 2026» o «Año 2026». */
    private function periodoLabel(int $anio, ?int $mes): string
    {
        return $mes
            ? ucfirst(Carbon::create($anio, $mes, 1)->translatedFormat('F Y'))
            : 'Año '.$anio;
    }

    /**
     * Años con ordenes registradas (descendente) para el selector del informe.
     * Siempre incluye el año actual aunque aun no tenga ordenes.
     */
    private function aniosDisponibles(): array
    {
        $min = OrdenServicio::min('fecha_ingreso');
        $max = OrdenServicio::max('fecha_ingreso');

        $primero = $min ? Carbon::parse($min)->year : \App\Support\FechaNegocio::ahora()->year;
        $ultimo = max($max ? Carbon::parse($max)->year : \App\Support\FechaNegocio::ahora()->year, \App\Support\FechaNegocio::ahora()->year);

        return array_reverse(range(min($primero, $ultimo), $ultimo));
    }

    /**
     * Años con trabajos en la agenda de terreno (descendente) para el selector
     * del informe industrial. Siempre incluye el año actual. Espeja
     * aniosDisponibles() pero sobre agenda_trabajos.fecha.
     */
    private function aniosDisponiblesAgenda(): array
    {
        $min = AgendaTrabajo::whereNotNull('fecha')->min('fecha');
        $max = AgendaTrabajo::whereNotNull('fecha')->max('fecha');

        $primero = $min ? Carbon::parse($min)->year : \App\Support\FechaNegocio::ahora()->year;
        $ultimo = max($max ? Carbon::parse($max)->year : \App\Support\FechaNegocio::ahora()->year, \App\Support\FechaNegocio::ahora()->year);

        return array_reverse(range(min($primero, $ultimo), $ultimo));
    }

    /**
     * @param  OrdenServicio|null  $orden  La orden que se edita (null al crear). Se
     *                                     usa para no exigir datos que la orden nunca
     *                                     tuvo — ver la regla de producto_id.
     */
    private function validateData(Request $request, bool $creando = false, ?OrdenServicio $orden = null): array
    {
        // Normalizar el RUT antes de validar (forma canonica 12345678-9), igual que
        // en Clientes; si no se puede normalizar, dejar el valor original para que
        // RutChileno lo rechace con su mensaje (no tragarlo como null).
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge(['cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput)]);

        $esGarantia = $request->input('facturacion') === 'garantia';

        // Máquina propia de la empresa (IMP. DALI): entra al taller sin ser de un
        // cliente externo, así que RUT/teléfono/correo dejan de ser obligatorios.
        $esPropia = OrdenServicio::esMaquinaPropia($request->input('cliente_nombre'));

        // "Ruta": el equipo lo recibe el conductor en ruta (no en una sucursal
        // física). El select manda el centinela 'ruta' y se escribe la ciudad en
        // el campo `ruta`. sucursal_id y ruta son mutuamente excluyentes.
        $esRuta = $request->input('sucursal_id') === 'ruta';
        if ($esRuta) {
            $request->merge(['sucursal_id' => null]);
        } else {
            $request->merge(['ruta' => null]);
        }

        // El N° de serie es obligatorio solo para tipos con serie unica
        // (dispensador/lavadora); para el resto (bombas/herramientas) es opcional.
        $serieObligatoria = in_array(
            $request->input('tipo_equipo'),
            OrdenServicio::SERIE_OBLIGATORIA_TIPOS,
            true,
        );

        $data = $request->validate([
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_rut' => [Rule::requiredIf(! $esPropia), 'nullable', 'string', 'max:20', new RutChileno],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],
            // Correo OBLIGATORIO para clientes externos (se les envia el folio y los
            // avisos); opcional para máquinas propias de la empresa (IMP. DALI).
            'cliente_email' => [Rule::requiredIf(! $esPropia), 'nullable', 'email', 'max:191'],
            // Obligatorio en el mostrador: toda orden se vincula a un producto del
            // catalogo Dali (el encargado ayuda a buscarlo). El form publico del QR
            // lo maneja aparte (alli sigue opcional).
            //
            // Al EDITAR solo se exige si la orden ya lo tenia: las ordenes que nacen
            // por QR o por lote en ruta no traen producto, y exigirlo obligaba a
            // clasificar el equipo en el catalogo para poder corregir cualquier otro
            // dato (un telefono mal escrito, por ejemplo). Quien quiera clasificarla
            // lo puede hacer igual; lo que ya no se puede es quitarle el producto a
            // una orden que si lo tenia.
            'producto_id' => [
                Rule::requiredIf($creando || filled($orden?->producto_id)),
                'nullable', 'integer', Rule::exists('productos', 'id'),
            ],
            'sucursal_id' => [Rule::requiredIf(! $esRuta), 'nullable', 'integer', Rule::exists('sucursales', 'id')],
            // Ciudad/localidad cuando se recibe en ruta (obligatoria en ese caso).
            'ruta' => [Rule::requiredIf($esRuta), 'nullable', 'string', 'max:120'],
            'fecha_ingreso' => ['required', 'date'],
            'tipo_equipo' => ['required', Rule::in(OrdenServicio::TIPOS)],
            'numero_serie' => [Rule::requiredIf($serieObligatoria), 'nullable', 'string', 'min:3', 'max:191'],
            'falla_reportada' => ['required', 'string', 'min:3'],
            // Falla del tecnico: opcional, notas aparte de las del cliente.
            'falla_tecnico' => ['nullable', 'string'],
            // El staff puede elegir el estado inicial al crear (default 'recibido'
            // si no llega); al editar es obligatorio. Siempre debe ser uno valido.
            'estado' => $creando
                ? ['nullable', Rule::in(OrdenServicio::ESTADOS)]
                : ['required', Rule::in(OrdenServicio::ESTADOS)],
            'facturacion' => ['required', Rule::in(OrdenServicio::FACTURACION)],
            // Si es garantia, el documento de compra y su fecha son obligatorios.
            // 'nullable' es clave: en reparacion el select oculto de garantia igual
            // envia garantia_doc_tipo="" (-> null) y sin nullable Rule::in lo rechaza.
            'garantia_doc_tipo' => [Rule::requiredIf($esGarantia), 'nullable', Rule::in(OrdenServicio::GARANTIA_DOC_TIPOS)],
            'garantia_doc_numero' => [Rule::requiredIf($esGarantia), 'nullable', 'string', 'max:191'],
            'garantia_doc_fecha' => [Rule::requiredIf($esGarantia), 'nullable', 'date', 'before_or_equal:fecha_ingreso'],
            'fecha_entrega' => ['nullable', 'date'],
        ]);

        if ($esGarantia) {
            // La garantia dura 6 meses desde la compra. Si al ingresar el equipo
            // ya vencio, no aplica garantia: debe registrarse como Reparacion.
            $vence = Carbon::parse($data['garantia_doc_fecha'])->addMonths(OrdenServicio::GARANTIA_MESES);
            if ($vence->lt(Carbon::parse($data['fecha_ingreso']))) {
                throw ValidationException::withMessages([
                    'garantia_doc_fecha' => 'La garantía venció el '.$vence->format('d-m-Y')
                        .' (6 meses desde la compra). Debe registrarse como «Reparación» (con cobro).',
                ]);
            }
        } else {
            // Reparacion: no se guardan datos de garantia.
            $data['garantia_doc_tipo'] = null;
            $data['garantia_doc_numero'] = null;
            $data['garantia_doc_fecha'] = null;
        }

        return $data;
    }

    /**
     * Combos para formularios y filtros. El producto (codigo) y el cliente se
     * eligen por autocompletado (endpoints JSON), no como <select>.
     */
    private function formData(?OrdenServicio $orden = null): array
    {
        return [
            'sucursales' => Sucursal::orderBy('nombre')->get(),
            'tipos' => OrdenServicio::TIPOS,
            'estados' => OrdenServicio::ESTADOS,
            'facturaciones' => OrdenServicio::FACTURACION,
            'garantiaDocTipos' => OrdenServicio::GARANTIA_DOC_TIPOS,
            // Feriados (Y-m-d) para calcular la fecha de entrega en dias habiles.
            'feriados' => array_values(config('feriados', [])),
            // Casa matriz de reparacion (Mirador, es_central): en Coquimbo y Abate
            // Molina se RECIBE pero no se repara. Se usa para rotular "se repara en
            // Mirador" cuando la recepcion fue en otra sucursal.
            'sucursalCentral' => Sucursal::firstWhere('es_central', true),
        ];
    }
}
