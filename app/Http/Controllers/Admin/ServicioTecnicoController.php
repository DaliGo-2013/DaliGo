<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
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
            ->with(['cliente', 'sucursal', 'tecnico'])
            ->latest('fecha_ingreso')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.servicio-tecnico.index', array_merge([
            'ordenes' => $ordenes,
            'filtros' => $request->only(['q', 'estado', 'tipo_equipo', 'tecnico_id']),
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
            ['orden' => $orden->load('cliente')],
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
            'tecnico_id' => ['nullable', 'integer'],
        ]);

        return OrdenServicio::query()
            ->when($f['q'] ?? null, function (Builder $qb, $q) {
                $rutQ = preg_replace('/[.\s]/', '', $q);

                $qb->where(function (Builder $w) use ($q, $rutQ) {
                    $w->where('marca', 'like', "%{$q}%")
                        ->orWhere('modelo', 'like', "%{$q}%")
                        ->orWhere('numero_serie', 'like', "%{$q}%")
                        ->orWhereHas('cliente', fn (Builder $c) => $c
                            ->where('razon_social', 'like', "%{$q}%")
                            ->orWhere('rut', 'like', "%{$rutQ}%"));
                });
            })
            ->when($f['estado'] ?? null, fn (Builder $qb, $v) => $qb->where('estado', $v))
            ->when($f['tipo_equipo'] ?? null, fn (Builder $qb, $v) => $qb->where('tipo_equipo', $v))
            ->when($f['tecnico_id'] ?? null, fn (Builder $qb, $v) => $qb->where('tecnico_id', $v));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')],
            'tecnico_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'fecha_ingreso' => ['required', 'date'],
            'tipo_equipo' => ['required', Rule::in(OrdenServicio::TIPOS)],
            'marca' => ['nullable', 'string', 'max:191'],
            'modelo' => ['nullable', 'string', 'max:191'],
            'numero_serie' => ['nullable', 'string', 'max:191'],
            'falla_reportada' => ['nullable', 'string'],
            'accesorios' => ['nullable', 'string'],
            'estado' => ['required', Rule::in(OrdenServicio::ESTADOS)],
            'observaciones' => ['nullable', 'string'],
            'fecha_entrega' => ['nullable', 'date'],
        ]);
    }

    /**
     * Combos para formularios y filtros. Tecnicos: whereHas y no User::role()
     * porque el scope de spatie LANZA RoleDoesNotExist si un admin borro el rol
     * desde la UI -> 500 en todas las pantallas del modulo. Incluye admin (un
     * admin tambien atiende/asigna). Al editar se conserva el tecnico actual
     * aunque haya perdido el rol, para no botar la asignacion en silencio.
     */
    private function formData(?OrdenServicio $orden = null): array
    {
        $tecnicos = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['tecnico', 'admin']))
            ->orderBy('name')->get();

        if ($orden?->tecnico_id && ! $tecnicos->contains('id', $orden->tecnico_id) && $orden->tecnico) {
            $tecnicos = $tecnicos->push($orden->tecnico)->sortBy('name')->values();
        }

        return [
            'tecnicos' => $tecnicos,
            'sucursales' => Sucursal::orderBy('nombre')->get(),
            'tipos' => OrdenServicio::TIPOS,
            'estados' => OrdenServicio::ESTADOS,
        ];
    }
}
