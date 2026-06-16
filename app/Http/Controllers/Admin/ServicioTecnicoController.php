<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Servicio Tecnico (taller): ingreso de maquinas y lavadoras. Version basica =
 * CRUD (registro/listado). El cliente dueno se busca por RUT (autocompletado
 * via buscarCliente, JSON) porque hay decenas de miles de clientes y un <select>
 * no sirve. Espeja el patron de ClienteController.
 */
class ServicioTecnicoController extends Controller
{
    public function index(Request $request): View
    {
        $ordenes = $this->filteredQuery($request)
            ->with('producto')
            ->latest('fecha_ingreso')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.servicio-tecnico.index', array_merge([
            'ordenes' => $ordenes,
            'filtros' => $request->only(['q', 'estado', 'tipo_equipo', 'facturacion']),
        ], $this->formData()));
    }

    public function create(): View
    {
        return view('admin.servicio-tecnico.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $orden = OrdenServicio::create($this->validateData($request));

        return redirect()->route('admin.servicio-tecnico.index')
            ->with('status', "Orden {$orden->folio} registrada.");
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
            ->get(['id', 'rut', 'razon_social']);

        return response()->json($clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'rut' => $c->rut,
            'razon_social' => $c->razon_social,
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
            ->when($f['facturacion'] ?? null, fn (Builder $qb, $v) => $qb->where('facturacion', $v));
    }

    private function validateData(Request $request): array
    {
        // Normalizar el RUT antes de validar (forma canonica 12345678-9), igual que
        // en Clientes; si no se puede normalizar, dejar el valor original para que
        // RutChileno lo rechace con su mensaje (no tragarlo como null).
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge(['cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput)]);

        return $request->validate([
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'cliente_nombre' => ['required', 'string', 'max:191'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            'producto_id' => ['nullable', 'integer', Rule::exists('productos', 'id')],
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')],
            'fecha_ingreso' => ['required', 'date'],
            'tipo_equipo' => ['required', Rule::in(OrdenServicio::TIPOS)],
            'modelo' => ['required', 'string', 'max:191'],
            'numero_serie' => ['nullable', 'string', 'max:191'],
            'falla_reportada' => ['required', 'string'],
            'estado' => ['required', Rule::in(OrdenServicio::ESTADOS)],
            'facturacion' => ['required', Rule::in(OrdenServicio::FACTURACION)],
            'observaciones' => ['nullable', 'string'],
            'fecha_entrega' => ['nullable', 'date'],
        ]);
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
        ];
    }
}
