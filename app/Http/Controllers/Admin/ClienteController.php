<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\User;
use App\Rules\RutChileno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = $this->filteredQuery($request)
            ->orderBy('razon_social')
            ->paginate(25)
            ->withQueryString();

        return view('admin.clientes.index', array_merge([
            'clientes' => $clientes,
            'filtros' => $request->only(['q', 'segmento', 'activo', 'vendedor_id']),
        ], $this->formData()));
    }

    public function create(): View
    {
        return view('admin.clientes.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $cliente = Cliente::create($this->validateData($request));

        return redirect()->route('admin.clientes.index')
            ->with('status', "Cliente {$cliente->razon_social} creado.");
    }

    public function edit(Cliente $cliente): View
    {
        return view('admin.clientes.edit', array_merge(['cliente' => $cliente], $this->formData($cliente)));
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($this->validateData($request, $cliente));

        return redirect()->route('admin.clientes.index')
            ->with('status', "Cliente {$cliente->razon_social} actualizado.");
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        // Anti-zombie: un cliente enlazado a Bsale seria recreado por el proximo
        // sync (perdiendo segmento/notas/vendedor en silencio). Se desactiva alla.
        if ($cliente->bsale_client_id !== null) {
            return back()->with('status', "No puedes eliminar a {$cliente->razon_social}: viene de Bsale. Desactívalo en Bsale y el espejo lo reflejará.");
        }

        $cliente->delete();

        return back()->with('status', "Cliente {$cliente->razon_social} eliminado.");
    }

    // --- Helpers --------------------------------------------------------

    /**
     * Query de clientes con los filtros del request aplicados.
     */
    private function filteredQuery(Request $request): Builder
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'segmento' => ['nullable', Rule::in(Cliente::SEGMENTOS)],
            'activo' => ['nullable', 'in:0,1'],
            'vendedor_id' => ['nullable', 'integer'],
        ]);

        $query = Cliente::query()->with('vendedor')
            ->when($f['q'] ?? null, function (Builder $qb, $q) {
                // El rut se guarda normalizado (sin puntos): buscar con la misma forma.
                $rutQ = preg_replace('/[.\s]/', '', $q);

                $qb->where(fn (Builder $w) => $w
                    ->where('razon_social', 'like', "%{$q}%")
                    ->orWhere('rut', 'like', "%{$rutQ}%"));
            })
            ->when($f['segmento'] ?? null, fn (Builder $qb, $v) => $qb->where('segmento', $v))
            ->when($f['vendedor_id'] ?? null, fn (Builder $qb, $v) => $qb->where('vendedor_id', $v));

        if (isset($f['activo']) && $f['activo'] !== '' && $f['activo'] !== null) {
            $query->where('activo', $f['activo'] === '1');
        }

        return $query;
    }

    private function validateData(Request $request, ?Cliente $cliente = null): array
    {
        // Normalizar ANTES de validar: el unique compara (y se guarda) la forma
        // canonica 12345678-9. Si no se puede normalizar, queda el valor original
        // para que RutChileno lo rechace con su mensaje (no tragarlo como null).
        $rutInput = trim((string) $request->input('rut'));
        $request->merge(['rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput)]);

        $validated = $request->validate([
            'rut' => ['nullable', 'string', 'max:20', new RutChileno, Rule::unique('clientes', 'rut')->ignore($cliente)],
            'razon_social' => ['required', 'string', 'max:191'],
            'giro' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'telefono' => ['nullable', 'string', 'max:191'],
            'direccion' => ['nullable', 'string', 'max:191'],
            'ciudad' => ['nullable', 'string', 'max:191'],
            'comuna' => ['nullable', 'string', 'max:191'],
            'segmento' => ['nullable', Rule::in(Cliente::SEGMENTOS)],
            'notas' => ['nullable', 'string'],
            'vendedor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $validated['es_empresa'] = $request->boolean('es_empresa');
        $validated['envio_factura_email'] = $request->boolean('envio_factura_email');
        $validated['activo'] = $request->boolean('activo');

        return $validated;
    }

    /**
     * Datos compartidos por formularios y filtros: usuarios asignables como
     * vendedor (cartera). Al editar se conserva el vendedor actual aunque haya
     * perdido el rol, para no botar la asignacion en silencio.
     */
    private function formData(?Cliente $cliente = null): array
    {
        // whereHas y no User::role(): el scope de spatie LANZA RoleDoesNotExist si
        // un admin borro el rol desde la UI -> 500 en todas las paginas de clientes.
        $vendedores = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['vendedor', 'jefe_ventas']))
            ->orderBy('name')->get();

        if ($cliente?->vendedor_id && ! $vendedores->contains('id', $cliente->vendedor_id) && $cliente->vendedor) {
            $vendedores = $vendedores->push($cliente->vendedor)->sortBy('name')->values();
        }

        return [
            'vendedores' => $vendedores,
            'segmentos' => Cliente::SEGMENTOS,
        ];
    }
}
