<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\LoteServicio;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use App\Support\ImagenComprimida;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ingreso por LOTE de Servicio Técnico (M08, subset): un conductor retira en
 * ruta varias máquinas de UNA empresa y las carga de una. Se elige la empresa +
 * los defaults del lote UNA vez y cada máquina es una fila liviana → crea N
 * órdenes en una transacción. Idempotente por `lote_uuid` (reenvío offline).
 * Las órdenes entran `fuente='ruta'` SIN confirmar (se confirman al llegar a
 * Mirador, como el QR).
 */
class LoteServicioController extends Controller
{
    public function create(): View
    {
        return view('admin.servicio-tecnico.lote.create', [
            'sucursales' => Sucursal::recepcionServicioTecnico()->get(),
            'ciudades' => config('servicio_tecnico.ciudades_ruta', []),
            'tipos' => OrdenServicio::TIPOS,
            'facturaciones' => OrdenServicio::FACTURACION,
            'sucursalCentral' => Sucursal::firstWhere('es_central', true),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Normaliza el RUT de la empresa antes de validar (igual que ST).
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge(['cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput)]);

        $data = $request->validate([
            'lote_uuid' => ['nullable', 'uuid'],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            'cliente_email' => ['nullable', 'email', 'max:191'],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],
            'origen_ciudad' => ['required', Rule::in(config('servicio_tecnico.ciudades_ruta', []))],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')],
            'fecha_ingreso' => ['required', 'date'],
            'tipo_default' => ['nullable', Rule::in(OrdenServicio::TIPOS)],
            'facturacion_default' => ['nullable', Rule::in(OrdenServicio::FACTURACION)],
            'falla_default' => ['nullable', 'string'],
            'capturado_at' => ['nullable', 'date'],
            'maquinas' => ['required', 'array', 'min:1'],
            // La foto de respaldo es opcional; mismas reglas que el QR (NO 'image': falla con HEIC).
            'maquinas.*.foto' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'max:8192'],
        ]);

        // Idempotencia: si el lote ya existe (reenvío de la cola offline), se
        // devuelve sin duplicar. El unique(lote_uuid) es la red ante carreras.
        if (! empty($data['lote_uuid'])) {
            $existente = LoteServicio::where('lote_uuid', $data['lote_uuid'])->first();
            if ($existente) {
                return $this->respuesta($request, $existente, yaExistia: true);
            }
        }

        // Validación por fila con herencia de los defaults del lote (patrón de
        // errores por fila del import de Productos: se acumulan y se lanzan).
        $filas = [];
        $errores = [];
        foreach ((array) $request->input('maquinas', []) as $i => $m) {
            $productoId = $m['producto_id'] ?? null;
            $tipo = trim((string) ($m['tipo'] ?? '')) ?: (string) ($data['tipo_default'] ?? '');
            $serie = trim((string) ($m['numero_serie'] ?? ''));
            $falla = trim((string) ($m['falla_reportada'] ?? '')) ?: trim((string) ($data['falla_default'] ?? ''));
            $facturacion = trim((string) ($m['facturacion'] ?? '')) ?: (string) ($data['facturacion_default'] ?? '');
            $modelo = trim((string) ($m['modelo'] ?? '')) ?: null;

            if (! $productoId || ! Producto::whereKey($productoId)->exists()) {
                $errores["maquinas.{$i}.producto_id"] = 'Elige el código del catálogo para esta máquina.';
            }
            if (! in_array($tipo, OrdenServicio::TIPOS, true)) {
                $errores["maquinas.{$i}.tipo"] = 'Tipo de equipo inválido.';
            }
            if (in_array($tipo, OrdenServicio::SERIE_OBLIGATORIA_TIPOS, true) && mb_strlen($serie) < 3) {
                $errores["maquinas.{$i}.numero_serie"] = 'El N° de serie es obligatorio (mín. 3) para este tipo.';
            }
            if (mb_strlen($falla) < 3) {
                $errores["maquinas.{$i}.falla_reportada"] = 'Indica la falla (mín. 3) o define una "falla común" en los valores por defecto.';
            }
            if (! in_array($facturacion, OrdenServicio::FACTURACION, true)) {
                $errores["maquinas.{$i}.facturacion"] = 'Condición inválida.';
            }

            $filas[$i] = [
                'producto_id' => $productoId,
                'tipo_equipo' => $tipo,
                'numero_serie' => $serie !== '' ? $serie : null,
                'modelo' => $modelo,
                'falla_reportada' => $falla,
                'facturacion' => $facturacion,
            ];
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $sucursal = Sucursal::findOrFail($data['sucursal_id']);
        $fecha = $data['fecha_ingreso'];
        $fechaEntrega = $sucursal->fechaEntregaEstimada($fecha)->toDateString();

        $ordenesPorFila = [];

        try {
            $lote = DB::transaction(function () use ($data, $request, $sucursal, $fecha, $fechaEntrega, $filas, &$ordenesPorFila) {
                $lote = LoteServicio::create([
                    'lote_uuid' => $data['lote_uuid'] ?? null,
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'cliente_nombre' => $data['cliente_nombre'],
                    'cliente_rut' => $data['cliente_rut'],
                    'cliente_email' => $data['cliente_email'] ?? null,
                    'cliente_telefono' => $data['cliente_telefono'] ?? null,
                    'origen_ciudad' => $data['origen_ciudad'],
                    'sucursal_id' => $sucursal->id,
                    'conductor_id' => $request->user()->id,
                    'fecha_ingreso' => $fecha,
                    'tipo_default' => $data['tipo_default'] ?? null,
                    'facturacion_default' => $data['facturacion_default'] ?? null,
                    'falla_default' => $data['falla_default'] ?? null,
                    'total_ordenes' => count($filas),
                    'capturado_at' => $data['capturado_at'] ?? null,
                ]);

                foreach ($filas as $i => $fila) {
                    // El correo va a nivel LOTE (a la empresa), no por orden ->
                    // cliente_email null evita N correos al confirmar en Mirador.
                    $orden = $lote->ordenes()->create([
                        'cliente_id' => $lote->cliente_id,
                        'cliente_nombre' => $lote->cliente_nombre,
                        'cliente_rut' => $lote->cliente_rut,
                        'cliente_telefono' => $lote->cliente_telefono,
                        'cliente_email' => null,
                        'producto_id' => $fila['producto_id'],
                        'sucursal_id' => $sucursal->id,
                        'fecha_ingreso' => $fecha,
                        'fecha_entrega' => $fechaEntrega,
                        'tipo_equipo' => $fila['tipo_equipo'],
                        'modelo' => $fila['modelo'],
                        'numero_serie' => $fila['numero_serie'],
                        'falla_reportada' => $fila['falla_reportada'],
                        'estado' => 'recibido',
                        'facturacion' => $fila['facturacion'],
                        'fuente' => OrdenServicio::FUENTE_RUTA,
                        'recibida_por' => $request->user()->name,
                        'confirmada_at' => null,
                    ]);
                    $ordenesPorFila[$i] = $orden->id;
                }

                return $lote;
            });
        } catch (QueryException $e) {
            // Carrera: otro request creó el mismo lote_uuid entre el check y el
            // insert. Se trata como éxito idempotente.
            if (! empty($data['lote_uuid']) && ($existente = LoteServicio::where('lote_uuid', $data['lote_uuid'])->first())) {
                return $this->respuesta($request, $existente, yaExistia: true);
            }
            throw $e;
        }

        // Fotos DESPUÉS del commit (el filesystem no es transaccional): se
        // comprimen (GD) y van al disco privado, una por una (memoria del hosting).
        foreach ($filas as $i => $fila) {
            $foto = $request->file("maquinas.{$i}.foto");
            if ($foto && isset($ordenesPorFila[$i]) && ($orden = OrdenServicio::find($ordenesPorFila[$i]))) {
                try {
                    $orden->fotos()->create([
                        'ruta' => ImagenComprimida::guardar($foto, "ordenes-servicio/fotos/{$orden->id}"),
                    ]);
                } catch (\Throwable $e) {
                    report($e); // una foto que falle no tumba el lote ya creado
                }
            }
        }

        return $this->respuesta($request, $lote, yaExistia: false);
    }

    private function respuesta(Request $request, LoteServicio $lote, bool $yaExistia): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'lote' => $lote->codigo,
                'ordenes' => (int) $lote->total_ordenes,
                'duplicado' => $yaExistia,
            ]);
        }

        $destino = $lote->sucursal?->nombre ?? 'Mirador';
        $msg = $yaExistia
            ? "El lote {$lote->codigo} ya estaba registrado."
            : "Lote {$lote->codigo} registrado: {$lote->total_ordenes} equipo(s). Se confirman al llegar a {$destino}.";

        return redirect()->route('admin.servicio-tecnico.index')->with('status', $msg);
    }

    /**
     * Autocompletado de la empresa por RUT o razón social (JSON). Mismo contrato
     * que ServicioTecnicoController::buscarCliente.
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
            ->get(['id', 'rut', 'razon_social', 'telefono', 'email']);

        return response()->json($clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'rut' => $c->rut,
            'razon_social' => $c->razon_social,
            'telefono' => $c->telefono,
            'email' => $c->email,
            'label' => ($c->rut ? $c->rut.' — ' : '').$c->razon_social,
        ]));
    }

    /**
     * Autocompletado del código Dali (producto). Solo equipos de taller
     * (scopeEquipoTaller), como el buscador del QR: el conductor trae equipos.
     */
    public function buscarProducto(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

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
}
