<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CotizacionCliente;
use App\Mail\DetalleTrabajoCliente;
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
use App\Rules\RutChileno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
        $ordenes = $this->filteredQuery($request)
            ->with(['producto', 'sucursal'])
            ->latest('fecha_ingreso')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.servicio-tecnico.index', array_merge([
            'ordenes' => $ordenes,
            'filtros' => $request->only(['q', 'estado', 'tipo_equipo', 'facturacion', 'sucursal_id', 'anio', 'mes']),
            // Cards de navegacion del historial (Año → Mes) sobre el listado.
            'historial' => $this->resumenHistorial($request->filled('anio') ? (int) $request->input('anio') : null),
            // Maquinas que llegaron por QR y esperan que el encargado confirme la
            // recepcion (bloque destacado arriba del listado).
            'porConfirmar' => OrdenServicio::porConfirmar()
                ->with('sucursal')
                ->latest('id')
                ->get(),
        ], $this->formData()));
    }

    /**
     * Landing de informes: dos "carpetas" para elegir — Dispensadores (taller) e
     * Industrial (servicio en terreno). Cada una entra a su propio informe.
     */
    public function informes(): View
    {
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
        $periodoLabel = $mes
            ? ucfirst(Carbon::create($anio, $mes, 1)->translatedFormat('F Y'))
            : 'Año '.$anio;

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

        // Query base reutilizable (clon por indicador). whereDate en ambos bordes
        // (portable MySQL 5.7 / SQLite) y descarta solicitudes sin fecha.
        $base = fn () => AgendaTrabajo::query()
            ->whereNotNull('fecha')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereIn('estado', ['agendado', 'realizado']);

        $total = $base()->count();

        // Cumplimiento: realizados vs pendientes (la base solo trae agendado +
        // realizado, así que pendientes = total - realizados).
        $realizados = $base()->where('estado', 'realizado')->count();
        $pendientes = $total - $realizados;
        $pctCumplimiento = $total > 0 ? (int) round($realizados / $total * 100) : 0;

        // Visitas técnicas (diagnóstico + cotización): cuántas y qué % del total.
        $visitas = $base()->where('tipo', 'visita_tecnica')->count();
        $visitasRealizadas = $base()->where('tipo', 'visita_tecnica')->where('estado', 'realizado')->count();
        $pctVisitas = $total > 0 ? (int) round($visitas / $total * 100) : 0;

        $porTipo = $base()
            ->selectRaw('tipo AS nombre, COUNT(*) AS cantidad')
            ->groupBy('tipo')->orderByDesc('cantidad')->get();

        // Clientes que más solicitan servicio industrial. Agrupa por RUT cuando
        // existe; si no, por nombre (mismo criterio que el top de dispensadores).
        $claveCliente = "COALESCE(NULLIF(cliente_rut, ''), cliente_nombre)";
        $topClientes = $base()
            ->selectRaw('MAX(cliente_nombre) AS nombre, MAX(cliente_rut) AS cliente_rut, COUNT(*) AS cantidad')
            ->groupBy(DB::raw($claveCliente))
            ->orderByDesc('cantidad')->limit(10)->get();

        // Servicios del catálogo más usados. MAX() por ONLY_FULL_GROUP_BY (5.7).
        // Los trabajos "fuera de tarifa" (servicio_terreno_id null) caen en su fila.
        $topServicios = $base()
            ->leftJoin('servicios_terreno', 'servicios_terreno.id', '=', 'agenda_trabajos.servicio_terreno_id')
            ->selectRaw('agenda_trabajos.servicio_terreno_id AS id, MAX(servicios_terreno.nombre) AS nombre, COUNT(*) AS cantidad')
            ->groupBy('agenda_trabajos.servicio_terreno_id')
            ->orderByDesc('cantidad')->limit(10)->get();

        // Uso de repuestos (en números): suma de unidades por repuesto de los
        // trabajos del período. Mismo patrón que el informe de dispensadores.
        $repuestos = AgendaTrabajoRepuesto::query()
            ->join('agenda_trabajos', 'agenda_trabajos.id', '=', 'agenda_trabajo_repuestos.agenda_trabajo_id')
            ->whereNotNull('agenda_trabajos.fecha')
            ->whereDate('agenda_trabajos.fecha', '>=', $desde)
            ->whereDate('agenda_trabajos.fecha', '<=', $hasta)
            ->selectRaw('agenda_trabajo_repuestos.nombre AS nombre, SUM(agenda_trabajo_repuestos.cantidad) AS unidades, COUNT(DISTINCT agenda_trabajo_repuestos.agenda_trabajo_id) AS trabajos')
            ->groupBy('agenda_trabajo_repuestos.nombre')
            ->orderByDesc('unidades')->get();

        $periodoLabel = $mes
            ? ucfirst(Carbon::create($anio, $mes, 1)->translatedFormat('F Y'))
            : 'Año '.$anio;

        return view('admin.servicio-tecnico.informe-industrial', [
            'anio' => $anio,
            'mes' => $mes,
            'anios' => $this->aniosDisponiblesAgenda(),
            'total' => $total,
            'realizados' => $realizados,
            'pendientes' => $pendientes,
            'pctCumplimiento' => $pctCumplimiento,
            'visitas' => $visitas,
            'visitasRealizadas' => $visitasRealizadas,
            'pctVisitas' => $pctVisitas,
            'porTipo' => $porTipo,
            'topServicios' => $topServicios,
            'topClientes' => $topClientes,
            'repuestos' => $repuestos->take(15)->values(),
            'totalUnidadesRepuestos' => (int) $repuestos->sum('unidades'),
            'totalNombresRepuestos' => $repuestos->count(),
            'periodoLabel' => $periodoLabel,
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

    /**
     * BOCETO interno: vista de seguimiento (estilo Blue Express) de las etapas
     * por las que pasa un equipo en el taller. Datos de ejemplo, sin conexión a
     * datos ni a la búsqueda del cliente todavía — es un adelanto del diseño.
     */
    public function seguimientoDemo(): View
    {
        return view('admin.servicio-tecnico.seguimiento-demo', [
            // "esperando_repuesto" NO va en el seguimiento del cliente: muchas
            // veces el técnico define en el momento si hay o no repuesto, así que
            // no es una etapa útil de cara al cliente (el estado interno sigue en ST).
            'pasos' => $this->pasosSeguimiento(['recibido', 'en_revision', 'cotizacion', 'reparado', 'entregado']),
            'pasosSinSolucion' => $this->pasosSeguimiento(['recibido', 'en_revision', 'cotizacion', 'sin_solucion']),
        ]);
    }

    /**
     * Arma los pasos de la línea de tiempo (key/label/desc/tono) a partir de los
     * estados de OrdenServicio. El label usa Str::headline (mismo criterio que el
     * <select> de la reparación); sin_solucion se pinta en rojo (danger).
     *
     * @param  list<string>  $keys
     * @return list<array{key:string,label:string,desc:string,tono:string}>
     */
    private function pasosSeguimiento(array $keys): array
    {
        $desc = [
            'recibido' => 'Recibimos tu equipo en el taller.',
            'en_revision' => 'El técnico está revisando la falla.',
            'cotizacion' => 'Te enviamos el presupuesto y esperamos tu aprobación.',
            'esperando_repuesto' => 'Pedimos el repuesto necesario para la reparación.',
            'reparado' => 'Tu equipo quedó reparado y probado.',
            'entregado' => 'Retiraste tu equipo. ¡Gracias!',
            'sin_solucion' => 'Lamentablemente el equipo no tiene reparación.',
        ];

        return array_map(fn (string $k) => [
            'key' => $k,
            'label' => Str::headline($k),
            'desc' => $desc[$k] ?? '',
            'tono' => $k === 'sin_solucion' ? 'danger' : 'brand',
        ], $keys);
    }

    public function show(OrdenServicio $orden): View
    {
        return view('admin.servicio-tecnico.show', [
            'orden' => $orden->load(['producto', 'sucursal', 'repuestos', 'fotos']),
            'sucursalCentral' => Sucursal::firstWhere('es_central', true),
        ]);
    }

    /**
     * Sirve una foto de recepcion desde el disco PRIVADO `local`. Solo para
     * usuarios con sesion y permiso de ver servicio tecnico (la ruta lo exige);
     * NO es una URL publica adivinable.
     */
    public function foto(OrdenServicioFoto $foto): StreamedResponse
    {
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
        $orden->update($this->validateData($request));

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
        return view('admin.servicio-tecnico.reparacion', [
            'orden' => $orden->load(['producto', 'repuestos']),
            'estados' => OrdenServicio::ESTADOS,
            'causasFalla' => OrdenServicio::CAUSAS_FALLA,
            // Respuestas fijas de "Trabajo realizado" agrupadas (config).
            'respuestasTrabajo' => config('servicio_tecnico.respuestas_trabajo', []),
            // Valor hora de mano de obra (precio con IVA del SKU de servicio
            // tecnico). Null si no existe/no tiene precio -> mano de obra $0.
            'precioHoraServicio' => $this->precioHoraServicio(),
            // Mapa trabajo -> horas estándar (catálogo) para mostrar en vivo la
            // mano de obra FIJA que implica el trabajo elegido (no editable).
            'tiemposMap' => TiempoReparacion::where('activo', true)->pluck('horas', 'trabajo')->map(fn ($h) => (float) $h),
        ]);
    }

    /**
     * Pestaña Cotización (ver + enviar): muestra el desglose GUARDADO de la
     * reparación (repuestos, mano de obra, descuento, total) y la tarjeta para
     * enviar la cotización al cliente + historial. Los números se ingresan en la
     * etapa de reparación; aquí solo se ven y se envían.
     */
    public function cotizacion(OrdenServicio $orden): View
    {
        return view('admin.servicio-tecnico.cotizacion', [
            'orden' => $orden->load(['producto', 'repuestos']),
            'cotizaciones' => $orden->cotizaciones()->latest('id')->get(),
            // Valor hora vigente (para mostrar cómo se compone la mano de obra fija).
            'precioHoraServicio' => $this->precioHoraServicio(),
            // Horas estándar del trabajo de esta orden (null si el trabajo no está
            // en el catálogo): la mano de obra se muestra de solo lectura.
            'horasTrabajo' => TiempoReparacion::horasDe($orden->trabajo_realizado),
        ]);
    }

    /**
     * Guarda el desglose de PRECIOS que arma la cotización: precio de cada
     * repuesto, mano de obra y descuento → total a pagar. El técnico solo deja
     * QUÉ repuestos usó (nombre + cantidad) en su parte; aquí se les pone precio.
     * Solo aplica a reparaciones (garantía no se cobra, no se cotiza).
     */
    public function guardarCotizacion(Request $request, OrdenServicio $orden): RedirectResponse
    {
        if ($orden->condicion_efectiva !== 'reparacion') {
            return back()->with('status', 'Equipo en garantía vigente: no se cotiza (no hay cobro).');
        }

        $data = $request->validate([
            'descuento_pct' => ['nullable', 'integer', Rule::in(array_merge([0], OrdenServicio::DESCUENTOS_PCT))],
            'descuento_motivo' => [Rule::requiredIf((int) $request->input('descuento_pct') > 0), 'nullable', Rule::in(array_keys(OrdenServicio::DESCUENTO_MOTIVOS))],
            'repuestos' => ['array'],
            'repuestos.*.nombre' => ['nullable', 'string', 'max:191'],
            'repuestos.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'repuestos.*.precio_unitario' => ['nullable', 'integer', 'min:0'],
        ], [
            'descuento_motivo.required' => 'Indica el motivo del descuento.',
        ]);

        // Validacion por fila: las filas vacias se ignoran; una fila con nombre
        // exige nombre (min 3) y precio (>0), porque aqui es donde se cobra. Se
        // pueden agregar repuestos con el buscador del catalogo, igual que en el
        // parte del tecnico.
        $errores = [];
        foreach ($request->input('repuestos', []) as $i => $r) {
            $nombre = trim((string) ($r['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            if (mb_strlen($nombre) < 3) {
                $errores["repuestos.{$i}.nombre"] = 'El repuesto necesita un nombre (mínimo 3 caracteres).';
            }
            if ((int) ($r['precio_unitario'] ?? 0) < 1) {
                $errores["repuestos.{$i}.precio_unitario"] = 'Indica el precio del repuesto (mayor a 0).';
            }
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $descuentoPct = (int) ($data['descuento_pct'] ?? 0);

        $orden->update([
            // Mano de obra FIJA por el trabajo (no se edita aquí): se recalcula
            // por si jefatura cambió el tiempo estándar en el ínterin.
            'mano_obra' => $this->manoObraDe($orden->trabajo_realizado),
            'descuento_pct' => $descuentoPct,
            'descuento_motivo' => $descuentoPct > 0 ? ($data['descuento_motivo'] ?? null) : null,
        ]);

        // Reemplazo de repuestos ya con precio. Nombre y cantidad vienen del parte
        // del técnico como campos ocultos (aquí son de solo lectura).
        $orden->repuestos()->delete();
        foreach ($data['repuestos'] ?? [] as $r) {
            if (empty($r['nombre'])) {
                continue;
            }
            $orden->repuestos()->create([
                'nombre' => $r['nombre'],
                'cantidad' => $r['cantidad'] ?? 1,
                'precio_unitario' => $r['precio_unitario'] ?? 0,
            ]);
        }

        $total = '$'.number_format((int) $orden->fresh()->costo_total, 0, ',', '.');

        return redirect()->route('admin.servicio-tecnico.cotizacion', $orden)
            ->with('status', "Cotización de la orden {$orden->folio} actualizada (total {$total}).");
    }

    /**
     * Valor hora de mano de obra: precio CON IVA del producto configurado como
     * "hora de servicio tecnico" (config sku_hora_servicio). Prioriza una lista
     * de precios activa; si no hay, cualquiera. Null si no existe o no tiene
     * precio (mismo criterio de precio que buscarRepuesto).
     */
    private function precioHoraServicio(): ?int
    {
        $sku = config('servicio_tecnico.sku_hora_servicio');
        if (! $sku) {
            return null;
        }

        $producto = Producto::where('sku', $sku)->with('precios.lista')->first();
        if (! $producto) {
            return null;
        }

        $pr = $producto->precios->first(fn ($x) => (bool) ($x->lista?->activa))
            ?? $producto->precios->first();

        return $pr ? (int) round((float) $pr->precio_con_iva) : null;
    }

    /**
     * Mano de obra FIJA de un trabajo = horas estándar (catálogo «Costos
     * generales de reparación») × valor hora. La fija jefatura; el técnico no la
     * edita. 0 si el trabajo no tiene tiempo estándar o no hay valor hora.
     */
    private function manoObraDe(?string $trabajo): int
    {
        $horas = TiempoReparacion::horasDe($trabajo);
        $valor = $this->precioHoraServicio();

        return ($horas !== null && $valor) ? (int) round($horas * $valor) : 0;
    }

    public function guardarReparacion(Request $request, OrdenServicio $orden): RedirectResponse
    {
        // Diagnostico final OBLIGATORIO al cerrar la orden: toda maquina que se
        // marca como 'reparado' o 'sin_solucion' debe quedar con la causa de la
        // falla (para que el informe refleje la realidad). En los estados
        // intermedios sigue siendo opcional. '' -> null por el middleware
        // ConvertEmptyStringsToNull, asi que 'Sin determinar' no pasa el required.
        $exigeDiagnostico = in_array($request->input('estado'), ['reparado', 'sin_solucion'], true);

        $data = $request->validate([
            'estado' => ['required', Rule::in(OrdenServicio::ESTADOS)],
            'trabajo_realizado' => ['nullable', 'string'],
            'causa_falla' => [Rule::requiredIf($exigeDiagnostico), 'nullable', Rule::in(OrdenServicio::CAUSAS_FALLA)],
            // Categoría de cierre: solo aplica a máquinas propias (IMP. DALI).
            'categoria' => ['nullable', Rule::in(OrdenServicio::CATEGORIAS)],
            'fecha_aviso' => ['nullable', 'date'],
            'fecha_retiro' => ['nullable', 'date'],
            'repuestos' => ['array'],
            'repuestos.*.nombre' => ['nullable', 'string', 'max:191'],
            'repuestos.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'repuestos.*.precio_unitario' => ['nullable', 'integer', 'min:0'],
        ], [
            'causa_falla.required' => 'Indica la causa de la falla (diagnóstico final) para cerrar la orden como «Reparado» o «Sin solución».',
        ]);

        // Validacion por fila: el tecnico solo declara QUE repuesto uso y cuantos;
        // si empezo a llenar una fila, exige el nombre (min 3). El PRECIO ya no se
        // ingresa aqui —se pone en la pestaña Cotización— pero se preserva si viene
        // (campo oculto) para no perderlo al re-guardar el parte del técnico.
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
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $orden->update([
            'estado' => $data['estado'],
            'trabajo_realizado' => $data['trabajo_realizado'] ?? null,
            'causa_falla' => $data['causa_falla'] ?? null,
            // La categoría solo se guarda para máquinas propias (IMP. DALI).
            'categoria' => $orden->es_propia ? ($data['categoria'] ?? null) : null,
            // Mano de obra FIJA por el trabajo (horas estándar × valor hora): el
            // técnico no la ingresa ni la edita. El descuento NO se toca aquí (lo
            // maneja la pestaña Cotización).
            'mano_obra' => $this->manoObraDe($data['trabajo_realizado'] ?? null),
            'fecha_aviso' => $data['fecha_aviso'] ?? null,
            'fecha_retiro' => $data['fecha_retiro'] ?? null,
        ]);

        // Reemplazo total de los repuestos: se borran y se recrean los que
        // tengan nombre (las filas vacias del formulario se ignoran).
        $orden->repuestos()->delete();
        foreach ($data['repuestos'] ?? [] as $r) {
            if (empty($r['nombre'])) {
                continue;
            }
            $orden->repuestos()->create([
                'nombre' => $r['nombre'],
                'cantidad' => $r['cantidad'] ?? 1,
                'precio_unitario' => $r['precio_unitario'] ?? 0,
            ]);
        }

        // Se queda en la MISMA pantalla de reparación (no vuelve al listado): así
        // el técnico puede enviar la cotización enseguida —"guarda antes de
        // enviar"— sin perder la página y con los datos ya guardados a la vista.
        return redirect()->route('admin.servicio-tecnico.reparacion', $orden)
            ->with('status', "Reparación de la orden {$orden->folio} actualizada.");
    }

    /**
     * Envía la COTIZACIÓN al cliente (P-M12-02, fase correo): congela un
     * snapshot de lo guardado en la orden, manda la carta formal con el link
     * firmado de respuesta y avisa a los roles internos. El correo es acción
     * secundaria (try/catch): si el SMTP falla, la cotización queda registrada
     * con `correo_enviado_at` null y aparece el botón "Reintentar".
     */
    public function enviarCotizacion(Request $request, OrdenServicio $orden): RedirectResponse
    {
        // Mismas condiciones que habilitan el botón (defensa server-side).
        if ($orden->condicion_efectiva !== 'reparacion') {
            return back()->with('status', 'Equipo en garantía vigente: no se cotiza (no hay cobro).');
        }
        if ($orden->estado !== 'cotizacion') {
            return back()->with('status', 'Pon la orden en etapa «Cotización» (y guarda) antes de enviar.');
        }
        if (blank($orden->cliente_email)) {
            return back()->with('status', 'La orden no tiene correo del cliente: agrégalo en los datos de recepción.');
        }
        if ((int) $orden->costo_total <= 0) {
            return back()->with('status', 'La cotización guardada está en $0: registra repuestos o mano de obra y guarda.');
        }

        $cotizacion = OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $request->user());

        $correoOk = $this->mandarCartaCotizacion($cotizacion);

        $cotizacion->avisarInternos('cotizacion.enviada', ['enviada_por' => $request->user()->name]);

        return back()->with('status', $correoOk
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

        $cotizacion->refresh()->avisarInternos('cotizacion.autorizada', [
            'pago' => $cotizacion->pago_forma_label,
            'autorizada_por' => $request->user()->name,
        ]);

        return back()->with('status', "Reparación autorizada (orden {$orden->folio}). Se avisó al técnico para proceder.");
    }

    /**
     * Sirve el comprobante de pago de una cotización desde el disco PRIVADO
     * `local` (dato sensible: transferencia). Solo con sesión y acceso al ST.
     */
    public function comprobanteCotizacion(OrdenServicioCotizacion $cotizacion): StreamedResponse
    {
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
            ->map(function (Producto $p) {
                // El precio de venta que encuentre: prioriza una lista activa; si no, cualquiera.
                $pr = $p->precios->first(fn ($x) => (bool) ($x->lista?->activa)) ?? $p->precios->first();

                return [
                    'nombre' => $p->nombre,
                    'sku' => $p->sku,
                    'precio' => $pr ? (int) round((float) $pr->precio_con_iva) : null,
                ];
            });

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
            'tipo_equipo' => ['nullable', Rule::in(OrdenServicio::TIPOS)],
            'facturacion' => ['nullable', Rule::in(OrdenServicio::FACTURACION)],
            // Sucursal de RECEPCION (donde se ingreso el equipo). El historial es
            // compartido por las 3 sucursales; este filtro deja ver "que se ingreso
            // en Coquimbo/Abate/Mirador". La reparacion siempre es en Mirador.
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')],
            // Periodo del historial (cards Año → Mes del listado).
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
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
            ->when($f['tipo_equipo'] ?? null, fn (Builder $qb, $v) => $qb->where('tipo_equipo', $v))
            ->when($f['facturacion'] ?? null, fn (Builder $qb, $v) => $qb->where('facturacion', $v))
            ->when($f['sucursal_id'] ?? null, fn (Builder $qb, $v) => $qb->where('sucursal_id', $v))
            // Periodo Año/Mes: rango de fechas con whereDate en ambos bordes
            // (portable MySQL 5.7 / SQLite y usa el indice; nada de YEAR() en
            // SQL). Mes sin año asume el año actual.
            ->when($f['anio'] ?? $f['mes'] ?? null, function (Builder $qb) use ($f) {
                $anio = (int) ($f['anio'] ?? \App\Support\FechaNegocio::ahora()->year);
                $mes = isset($f['mes']) ? (int) $f['mes'] : null;
                $desde = Carbon::create($anio, $mes ?? 1, 1);
                $hasta = $mes ? $desde->copy()->endOfMonth() : $desde->copy()->endOfYear();
                $qb->whereDate('fecha_ingreso', '>=', $desde->toDateString())
                    ->whereDate('fecha_ingreso', '<=', $hasta->toDateString());
            });
    }

    /**
     * Resumen para las cards de navegacion del historial (Año → Mes) del
     * listado. Una sola query liviana (solo fecha y condicion) agrupada en
     * PHP: el volumen es bajo (cientos de ordenes por año) y evita SQL de
     * fechas no portable entre MySQL 5.7 y el SQLite de los tests.
     */
    private function resumenHistorial(?int $anioActivo): array
    {
        $ordenes = OrdenServicio::query()
            ->whereNotNull('fecha_ingreso')
            ->get(['fecha_ingreso', 'facturacion']);

        // Reparacion = total - garantia (igual que condicion_efectiva: las
        // ordenes viejas con facturacion NULL cuentan como reparacion).
        $anios = $ordenes
            ->groupBy(fn (OrdenServicio $o) => $o->fecha_ingreso->year)
            ->map(function ($grupo) {
                $garantia = $grupo->where('facturacion', 'garantia')->count();

                return [
                    'total' => $grupo->count(),
                    'garantia' => $garantia,
                    'reparacion' => $grupo->count() - $garantia,
                ];
            })
            ->sortKeysDesc();

        $meses = null;
        if ($anioActivo !== null) {
            $delAnio = $ordenes->filter(fn (OrdenServicio $o) => $o->fecha_ingreso->year === $anioActivo);
            $meses = collect(range(1, 12))
                ->mapWithKeys(fn (int $m) => [$m => $delAnio->filter(fn (OrdenServicio $o) => $o->fecha_ingreso->month === $m)->count()])
                ->all();
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

    private function validateData(Request $request, bool $creando = false): array
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
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
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
