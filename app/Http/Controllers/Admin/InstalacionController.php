<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Instalacion;
use App\Rules\RutChileno;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Registro de instalaciones en terreno del técnico industrial (Carlos Tablante).
 * Plasma su Excel "INSTALACION DE MAQUINAS": listado editable de instalaciones/
 * puestas en marcha con datos comerciales. Lo usan el técnico industrial, jefes
 * de venta y admin (permiso 'gestionar instalaciones').
 */
class InstalacionController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->only(['q', 'categoria', 'anio']);

        return view('admin.instalaciones.index', [
            'instalaciones' => $this->filtradas($request),
            'filtros' => $filtros,
            // Años disponibles para el filtro (portable MySQL/SQLite: el año sale
            // del string 'YYYY-MM-DD' sin funciones de fecha del motor).
            'anios' => Instalacion::query()->select('fecha')->distinct()->orderByDesc('fecha')->pluck('fecha')
                ->map(fn ($f) => (int) substr((string) $f, 0, 4))->unique()->values(),
            'categorias' => Instalacion::CATEGORIAS,
        ]);
    }

    public function create(): View
    {
        return view('admin.instalaciones.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['creado_por'] = $request->user()->name;

        $instalacion = Instalacion::create($data);

        return redirect()->route('admin.instalaciones.index')
            ->with('status', "Instalación registrada: {$instalacion->cliente_nombre} ({$instalacion->fecha->format('d-m-Y')}).");
    }

    public function edit(Instalacion $instalacion): View
    {
        return view('admin.instalaciones.edit', array_merge(['instalacion' => $instalacion], $this->formData()));
    }

    public function update(Request $request, Instalacion $instalacion): RedirectResponse
    {
        $instalacion->update($this->validateData($request));

        return redirect()->route('admin.instalaciones.index')
            ->with('status', "Instalación de {$instalacion->cliente_nombre} actualizada.");
    }

    public function destroy(Instalacion $instalacion): RedirectResponse
    {
        $instalacion->delete();

        return back()->with('status', 'Instalación eliminada del registro.');
    }

    /**
     * Autocompletado del cliente por RUT o razón social (JSON). Mismo contrato
     * que la agenda de terreno; al elegir se rellenan nombre/rut/comuna (editables).
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
            ->get(['id', 'rut', 'razon_social', 'ciudad']);

        return response()->json($clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'rut' => $c->rut,
            'razon_social' => $c->razon_social,
            'ciudad' => $c->ciudad,
            'label' => ($c->rut ? $c->rut.' — ' : '').$c->razon_social,
        ]));
    }

    // --- Helpers --------------------------------------------------------

    /**
     * @return LengthAwarePaginator<Instalacion>
     */
    private function filtradas(Request $request): LengthAwarePaginator
    {
        $q = trim((string) $request->input('q', ''));

        return Instalacion::query()
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('cliente_nombre', 'like', "%{$q}%")
                ->orWhere('cliente_rut', 'like', "%{$q}%")
                ->orWhere('producto', 'like', "%{$q}%")
                ->orWhere('n_factura', 'like', "%{$q}%")
                ->orWhere('vendedor', 'like', "%{$q}%")))
            ->when($request->filled('categoria'), fn (Builder $b) => $b->where('categoria', $request->input('categoria')))
            ->when($request->filled('anio'), fn (Builder $b) => $b->whereYear('fecha', (int) $request->input('anio')))
            ->latest('fecha')->latest('id')
            ->paginate(25)
            ->withQueryString();
    }

    private function validateData(Request $request): array
    {
        // RUT opcional: se normaliza a la forma canónica si viene.
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge([
            'cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput),
            'cliente_id' => $request->input('cliente_id') ?: null,
        ]);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'cliente_nombre' => ['required', 'string', 'min:2', 'max:191'],
            'cliente_rut' => ['nullable', 'string', 'max:20', new RutChileno],
            'comuna_region' => ['nullable', 'string', 'max:191'],
            'categoria' => ['required', Rule::in(Instalacion::CATEGORIAS)],
            'producto' => ['nullable', 'string', 'max:191'],
            'dias' => ['nullable', 'integer', 'min:0', 'max:365'],
            'vendedor' => ['nullable', 'string', 'max:191'],
            'n_factura' => ['nullable', 'string', 'max:50'],
            'fecha_factura' => ['nullable', 'date'],
            'forma_pago' => ['nullable', Rule::in(Instalacion::FORMAS_PAGO)],
            'fecha_pago' => ['nullable', 'date'],
        ]);

        // Checkboxes: SI/NO del Excel.
        $data['instalacion'] = $request->boolean('instalacion');
        $data['puesta_en_marcha'] = $request->boolean('puesta_en_marcha');

        return $data;
    }

    private function formData(): array
    {
        return [
            'categorias' => Instalacion::CATEGORIAS,
            'formasPago' => Instalacion::FORMAS_PAGO,
            'vendedores' => Instalacion::VENDEDORES_SUGERIDOS,
        ];
    }
}
