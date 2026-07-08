<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\IngresoTallerRecibido;
use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioRepuesto;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
            'filtros' => $request->only(['q', 'estado', 'tipo_equipo', 'facturacion', 'sucursal_id']),
            // Maquinas que llegaron por QR y esperan que el encargado confirme la
            // recepcion (bloque destacado arriba del listado).
            'porConfirmar' => OrdenServicio::porConfirmar()
                ->with('sucursal')
                ->latest('id')
                ->get(),
        ], $this->formData()));
    }

    public function create(): View
    {
        return view('admin.servicio-tecnico.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request, creando: true);

        // Al registrar, el mostrador no decide estado ni fecha: toda orden nueva
        // parte en 'recibido' y la fecha estimada la fija el servidor segun la
        // sucursal (el formulario los muestra pero no los deja editar).
        $data['estado'] = 'recibido';
        $data['fecha_entrega'] = Sucursal::findOrFail($data['sucursal_id'])
            ->fechaEntregaEstimada($data['fecha_ingreso'])->toDateString();

        $orden = OrdenServicio::create($data);

        return redirect()->route('admin.servicio-tecnico.index')
            ->with('status', "Orden {$orden->folio} registrada.");
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

            $fresh->update(['confirmada_at' => now()]);

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
        return view('admin.servicio-tecnico.show', [
            'orden' => $orden->load(['producto', 'sucursal', 'repuestos']),
            'sucursalCentral' => Sucursal::firstWhere('es_central', true),
        ]);
    }

    public function edit(OrdenServicio $orden): View
    {
        return view('admin.servicio-tecnico.edit', array_merge(
            ['orden' => $orden->load('producto')],
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
        ]);
    }

    public function guardarReparacion(Request $request, OrdenServicio $orden): RedirectResponse
    {
        // Garantia vencida o sin documento = reparacion (se cobra): exige precio.
        $esReparacion = $orden->condicion_efectiva === 'reparacion';

        $data = $request->validate([
            'estado' => ['required', Rule::in(OrdenServicio::ESTADOS)],
            'trabajo_realizado' => ['nullable', 'string'],
            'mano_obra' => ['nullable', 'integer', 'min:0'],
            'fecha_aviso' => ['nullable', 'date'],
            'fecha_retiro' => ['nullable', 'date'],
            'repuestos' => ['array'],
            'repuestos.*.nombre' => ['nullable', 'string', 'max:191'],
            'repuestos.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'repuestos.*.precio_unitario' => ['nullable', 'integer', 'min:0'],
        ]);

        // Validacion por fila: si el tecnico empezo a llenar un repuesto, exige
        // nombre (min 3) y, cuando se cobra (reparacion), un precio mayor a 0.
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
            if ($esReparacion && $precio < 1) {
                $errores["repuestos.{$i}.precio_unitario"] = 'Indica el precio del repuesto (mayor a 0).';
            }
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $orden->update([
            'estado' => $data['estado'],
            'trabajo_realizado' => $data['trabajo_realizado'] ?? null,
            'mano_obra' => $data['mano_obra'] ?? null,
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

        return redirect()->route('admin.servicio-tecnico.index')
            ->with('status', "Reparación de la orden {$orden->folio} actualizada.");
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

        $historial = OrdenServicioRepuesto::query()
            ->where('nombre', 'like', "%{$q}%")
            ->distinct()
            ->orderBy('nombre')
            ->limit(15)
            ->pluck('nombre');

        $comunes = collect(self::REPUESTOS_COMUNES)
            ->filter(fn (string $n) => mb_stripos($n, $q) !== false);

        $nombres = $historial->merge($comunes)
            ->map(fn (string $n) => trim($n))
            ->filter()
            ->unique(fn (string $n) => mb_strtolower($n))
            ->take(15)
            ->values();

        return response()->json($nombres->map(fn (string $n) => ['nombre' => $n]));
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
        ]);

        return OrdenServicio::query()
            ->when($f['q'] ?? null, function (Builder $qb, $q) {
                $rutQ = preg_replace('/[.\s]/', '', $q);

                $qb->where(function (Builder $w) use ($q, $rutQ) {
                    $w->where('cliente_nombre', 'like', "%{$q}%")
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
            ->when($f['sucursal_id'] ?? null, fn (Builder $qb, $v) => $qb->where('sucursal_id', $v));
    }

    private function validateData(Request $request, bool $creando = false): array
    {
        // Normalizar el RUT antes de validar (forma canonica 12345678-9), igual que
        // en Clientes; si no se puede normalizar, dejar el valor original para que
        // RutChileno lo rechace con su mensaje (no tragarlo como null).
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge(['cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput)]);

        $esGarantia = $request->input('facturacion') === 'garantia';

        $data = $request->validate([
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],
            // Obligatorio en el mostrador: toda orden se vincula a un producto del
            // catalogo Dali (el encargado ayuda a buscarlo). El form publico del QR
            // lo maneja aparte (alli sigue opcional).
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')],
            'fecha_ingreso' => ['required', 'date'],
            'tipo_equipo' => ['required', Rule::in(OrdenServicio::TIPOS)],
            'numero_serie' => ['required', 'string', 'min:3', 'max:191'],
            'falla_reportada' => ['required', 'string', 'min:3'],
            // Al crear, el estado no viene del formulario (store lo fuerza a
            // 'recibido'); si igual llega, que al menos sea uno valido.
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
