<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\LoteServicio;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use App\Support\ImagenComprimida;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ingreso PUBLICO a servicio tecnico por QR (P-M12-01, piloto).
 *
 * El cliente escanea un QR pegado en el mostrador (uno por sucursal) que abre
 * este formulario en SU celular, SIN login. Al enviar se crea una orden real
 * (fuente 'qr', estado 'recibido') pero SIN confirmar: el encargado del
 * mostrador la valida y recibe la maquina fisica desde el panel de admin, y ahi
 * recien se le manda el correo con el folio.
 *
 * Seguridad: el GET del QR es un link FIRMADO (URL::signedRoute) que lleva el
 * sucursal_id embebido -> no se puede alterar la sucursal sin invalidar la firma.
 * El grupo tiene throttle y el form un honeypot anti-bot. Este flujo SOLO
 * escribe: nunca lista ni muestra otras ordenes. Endurecer el POST con token
 * firmado queda para F3 (P-F3-01, ver docs/RUTA-MAESTRA.md).
 */
class IngresoTallerPublicoController extends Controller
{
    /**
     * Formulario publico. La sucursal viene firmada en el link del QR.
     */
    public function create(Request $request): View
    {
        $sucursal = Sucursal::where('activa', true)->findOrFail($request->integer('sucursal'));

        return view('publico.taller.create', [
            'sucursal' => $sucursal,
            'tipos' => OrdenServicio::TIPOS,
            // Link firmado al ingreso por cantidad (varias máquinas de una vez).
            'urlLote' => URL::signedRoute('ingreso-taller.lote.create', ['sucursal' => $sucursal->id]),
        ]);
    }

    /**
     * Formulario público de ingreso POR CANTIDAD: el cliente escribe sus datos
     * UNA vez y agrega N máquinas (cada una queda como una orden con su propio
     * folio, para que el técnico informe cada equipo por separado).
     */
    public function createLote(Request $request): View
    {
        $sucursal = Sucursal::where('activa', true)->findOrFail($request->integer('sucursal'));

        return view('publico.taller.create-lote', [
            'sucursal' => $sucursal,
            'tipos' => OrdenServicio::TIPOS,
            'facturaciones' => OrdenServicio::FACTURACION,
            'garantiaDocTipos' => OrdenServicio::GARANTIA_DOC_TIPOS,
        ]);
    }

    /**
     * Crea el lote público: N órdenes (una por máquina, cada una con su folio)
     * con los datos del cliente escritos una sola vez. Mismas reglas que el
     * ingreso por unidad (2 fotos obligatorias por máquina, condición con
     * documento de garantía si aplica), en transacción (todo o nada).
     */
    public function storeLote(Request $request): RedirectResponse
    {
        // Honeypot: mismo tratamiento que el ingreso por unidad.
        if (filled($request->input('sitio_web'))) {
            $sucursalId = (int) $request->input('sucursal_id');

            return redirect()->to(URL::signedRoute('ingreso-taller.lote.create', ['sucursal' => $sucursalId]));
        }

        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge([
            'cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput),
        ]);

        $esGarantia = $request->input('facturacion') === 'garantia';

        $data = $request->validate([
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')->where('activa', true)],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_email' => ['required', 'email', 'max:191'],
            'cliente_telefono' => ['required', 'string', 'max:30'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            // Condición y (si aplica) documento de garantía: UNA vez para todo el
            // lote (las máquinas de una empresa suelen compartir la compra).
            'facturacion' => ['required', Rule::in(OrdenServicio::FACTURACION)],
            'garantia_doc_tipo' => [Rule::requiredIf($esGarantia), 'nullable', Rule::in(OrdenServicio::GARANTIA_DOC_TIPOS)],
            'garantia_doc_numero' => [Rule::requiredIf($esGarantia), 'nullable', 'string', 'max:191'],
            'garantia_doc_fecha' => [Rule::requiredIf($esGarantia), 'nullable', 'date', 'before_or_equal:today'],
            'tipo_default' => ['required', Rule::in(OrdenServicio::TIPOS)],
            'maquinas' => ['required', 'array', 'min:1', 'max:30'],
            // 2 fotos de respaldo POR MÁQUINA (mismo estándar del ingreso por
            // unidad). Sin regla `image` (HEIC); GD re-encoda después.
            'maquinas.*.fotos' => ['required', 'array', 'size:2'],
            'maquinas.*.fotos.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'max:8192'],
        ]);

        // Validación por fila (patrón del lote del conductor): tipo hereda el
        // default, serie obligatoria según el tipo efectivo, código OBLIGATORIO
        // y falla/estado POR MÁQUINA (golpes, rayas, caja, piezas faltantes —
        // el detalle de cada equipo importa; pedido del dueño).
        $filas = [];
        $errores = [];
        foreach ((array) $request->input('maquinas', []) as $i => $m) {
            $tipo = trim((string) ($m['tipo'] ?? '')) ?: (string) $data['tipo_default'];
            $serie = trim((string) ($m['numero_serie'] ?? ''));
            $falla = trim((string) ($m['falla_reportada'] ?? ''));
            $productoId = $m['producto_id'] ?? null;

            if (! in_array($tipo, OrdenServicio::TIPOS, true)) {
                $errores["maquinas.{$i}.tipo"] = 'Tipo de equipo inválido.';
            }
            if (in_array($tipo, OrdenServicio::SERIE_OBLIGATORIA_TIPOS, true) && mb_strlen($serie) < 3) {
                $errores["maquinas.{$i}.numero_serie"] = 'El N° de serie es obligatorio (mín. 3) para este tipo.';
            }
            if (! $productoId || ! Producto::whereKey($productoId)->exists()) {
                $errores["maquinas.{$i}.producto_id"] = 'Elige el código del catálogo para esta máquina.';
            }
            if (mb_strlen($falla) < 3) {
                $errores["maquinas.{$i}.falla_reportada"] = 'Describe la falla y el estado de esta máquina (mín. 3).';
            }

            $filas[$i] = [
                'tipo_equipo' => $tipo,
                'numero_serie' => $serie !== '' ? $serie : null,
                'falla_reportada' => $falla,
                'producto_id' => $productoId,
            ];
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $sucursal = Sucursal::findOrFail($data['sucursal_id']);
        $hoy = now()->toDateString();
        $fechaEntrega = $sucursal->fechaEntregaEstimada($hoy)->toDateString();

        $ordenesPorFila = [];

        $lote = DB::transaction(function () use ($data, $sucursal, $hoy, $fechaEntrega, $filas, &$ordenesPorFila) {
            $lote = LoteServicio::create([
                'cliente_nombre' => $data['cliente_nombre'],
                'cliente_rut' => $data['cliente_rut'],
                'cliente_email' => $data['cliente_email'],
                'cliente_telefono' => $data['cliente_telefono'],
                'sucursal_id' => $sucursal->id,
                'fecha_ingreso' => $hoy,
                'tipo_default' => $data['tipo_default'],
                'facturacion_default' => $data['facturacion'],
                'total_ordenes' => count($filas),
            ]);

            foreach ($filas as $i => $fila) {
                // Cada máquina = una orden con su PROPIO folio. El cliente va en
                // cada orden (correo incluido: al confirmar cada una en el
                // mostrador se le envía su folio, como el ingreso por unidad).
                $orden = $lote->ordenes()->create([
                    'cliente_nombre' => $data['cliente_nombre'],
                    'cliente_rut' => $data['cliente_rut'],
                    'cliente_email' => $data['cliente_email'],
                    'cliente_telefono' => $data['cliente_telefono'],
                    'producto_id' => $fila['producto_id'],
                    'sucursal_id' => $sucursal->id,
                    'tipo_equipo' => $fila['tipo_equipo'],
                    'numero_serie' => $fila['numero_serie'],
                    'facturacion' => $data['facturacion'],
                    'garantia_doc_tipo' => $data['garantia_doc_tipo'] ?? null,
                    'garantia_doc_numero' => $data['garantia_doc_numero'] ?? null,
                    'garantia_doc_fecha' => $data['garantia_doc_fecha'] ?? null,
                    'falla_reportada' => $fila['falla_reportada'],
                    'fecha_ingreso' => $hoy,
                    'fecha_entrega' => $fechaEntrega,
                    'estado' => 'recibido',
                    'fuente' => 'qr',
                    'confirmada_at' => null,
                ]);
                $ordenesPorFila[$i] = $orden->id;
            }

            return $lote;
        });

        // Fotos DESPUÉS del commit (el filesystem no es transaccional): 2 por
        // máquina, comprimidas (GD) al disco privado, de a una (memoria).
        foreach ($filas as $i => $fila) {
            $fotos = $request->file("maquinas.{$i}.fotos", []);
            if (isset($ordenesPorFila[$i]) && ($orden = OrdenServicio::find($ordenesPorFila[$i]))) {
                foreach ($fotos as $foto) {
                    try {
                        $orden->fotos()->create([
                            'ruta' => ImagenComprimida::guardar($foto, "ordenes-servicio/fotos/{$orden->id}"),
                        ]);
                    } catch (\Throwable $e) {
                        report($e); // una foto que falle no tumba el lote ya creado
                    }
                }
            }
        }

        return redirect()->to(URL::signedRoute('ingreso-taller.lote.gracias', ['lote' => $lote->id]));
    }

    /**
     * Pantalla de "listo" del lote: lista los folios (uno por máquina) para que
     * el cliente se la muestre al encargado. Link firmado (no enumerable).
     */
    public function graciasLote(LoteServicio $lote): View
    {
        return view('publico.taller.gracias-lote', [
            'lote' => $lote->load(['sucursal', 'ordenes']),
        ]);
    }

    /**
     * Crea la orden por QR. Validacion LIVIANA (el encargado completa lo demas):
     * el cliente no decide garantia/reparacion ni sube documentos.
     */
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: campo oculto que un humano deja vacio. Si viene lleno es un
        // bot -> cortamos sin crear nada (respuesta identica a la de exito para
        // no darle pistas).
        if (filled($request->input('sitio_web'))) {
            $sucursalId = (int) $request->input('sucursal_id');

            return redirect()->to(URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursalId]));
        }

        // Normalizar el RUT (opcional en lo publico) a la forma canonica 12345678-9.
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge([
            'cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput),
        ]);

        // El N° de serie es obligatorio solo para tipos con serie unica
        // (dispensador/lavadora); para bombas/herramientas es opcional.
        $serieObligatoria = in_array(
            $request->input('tipo_equipo'),
            OrdenServicio::SERIE_OBLIGATORIA_TIPOS,
            true,
        );

        // Si el cliente marca Garantia, el documento de compra que la respalda es
        // obligatorio (factura/boleta + N° + fecha, no futura).
        $esGarantia = $request->input('facturacion') === 'garantia';

        $data = $request->validate([
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')->where('activa', true)],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_email' => ['required', 'email', 'max:191'],
            'cliente_telefono' => ['required', 'string', 'max:30'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            'producto_id' => ['nullable', 'integer', Rule::exists('productos', 'id')],
            'tipo_equipo' => ['required', Rule::in(OrdenServicio::TIPOS)],
            'numero_serie' => [Rule::requiredIf($serieObligatoria), 'nullable', 'string', 'min:3', 'max:191'],
            // Condicion (garantia/reparacion): el cliente la indica; el mostrador
            // la verifica al confirmar (y pide el documento de garantia si aplica).
            'facturacion' => ['required', Rule::in(OrdenServicio::FACTURACION)],
            'garantia_doc_tipo' => [Rule::requiredIf($esGarantia), 'nullable', Rule::in(OrdenServicio::GARANTIA_DOC_TIPOS)],
            'garantia_doc_numero' => [Rule::requiredIf($esGarantia), 'nullable', 'string', 'max:191'],
            'garantia_doc_fecha' => [Rule::requiredIf($esGarantia), 'nullable', 'date', 'before_or_equal:today'],
            'falla_reportada' => ['required', 'string', 'min:3'],
            // Exactamente 2 fotos de respaldo del estado fisico del equipo. No se
            // usa la regla `image` (falla con HEIC de iPhone); se valida por mimetype
            // y tamano, y luego GD re-encoda (saneando el archivo). Endpoint publico:
            // ya hay throttle + honeypot en el grupo.
            'fotos' => ['required', 'array', 'size:2'],
            'fotos.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'max:8192'],
        ]);

        $sucursal = Sucursal::findOrFail($data['sucursal_id']);
        $hoy = now()->toDateString();

        $orden = OrdenServicio::create([
            'cliente_nombre' => $data['cliente_nombre'],
            'cliente_email' => $data['cliente_email'],
            'cliente_telefono' => $data['cliente_telefono'] ?? null,
            'cliente_rut' => $data['cliente_rut'] ?? null,
            'producto_id' => $data['producto_id'] ?? null,
            'sucursal_id' => $sucursal->id,
            'tipo_equipo' => $data['tipo_equipo'],
            'numero_serie' => $data['numero_serie'] ?? null,
            'facturacion' => $data['facturacion'],
            'garantia_doc_tipo' => $data['garantia_doc_tipo'] ?? null,
            'garantia_doc_numero' => $data['garantia_doc_numero'] ?? null,
            'garantia_doc_fecha' => $data['garantia_doc_fecha'] ?? null,
            'falla_reportada' => $data['falla_reportada'],
            'fecha_ingreso' => $hoy,
            'fecha_entrega' => $sucursal->fechaEntregaEstimada($hoy)->toDateString(),
            'estado' => 'recibido',
            'fuente' => 'qr',
            'confirmada_at' => null,
        ]);

        // Guardar las 2 fotos: se comprimen (GD) y van al disco PRIVADO `local`.
        foreach ($request->file('fotos', []) as $foto) {
            $orden->fotos()->create([
                'ruta' => ImagenComprimida::guardar($foto, "ordenes-servicio/fotos/{$orden->id}"),
            ]);
        }

        // El correo NO se manda aqui: sale cuando el encargado confirma la
        // recepcion (asi el cliente recibe datos ya verificados).
        return redirect()->to(URL::signedRoute('ingreso-taller.gracias', ['orden' => $orden->id]));
    }

    /**
     * Autocompletado PUBLICO del producto Dali (codigo) por SKU o nombre, para
     * que el cliente complete el codigo del equipo desde el catalogo. Mismo
     * contrato JSON que Admin\ServicioTecnicoController::buscarProducto, pero sin
     * auth (throttle propio en la ruta; solo lee SKU/nombre, minimo 2 caracteres,
     * limite 15).
     */
    public function buscarProducto(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Solo equipos de taller (dispensadores, lavadoras, bombas, herramientas):
        // el cliente no debe ver accesorios/repuestos del catálogo. El buscador
        // del mostrador (staff) NO aplica este filtro.
        $productos = Producto::query()
            ->equipoTaller()
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
     * Pantalla de "listo" que el cliente le muestra al encargado. Link firmado:
     * no se pueden enumerar folios de otras ordenes.
     */
    public function gracias(OrdenServicio $orden): View
    {
        return view('publico.taller.gracias', [
            'orden' => $orden->load('sucursal'),
        ]);
    }
}
