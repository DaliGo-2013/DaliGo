<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SucursalController extends Controller
{
    /**
     * Listado de sucursales con conteo de usuarios asignados.
     */
    public function index(): View
    {
        $sucursales = Sucursal::withCount('users')->orderBy('nombre')->get();

        return view('admin.sucursales.index', ['sucursales' => $sucursales]);
    }

    public function create(): View
    {
        return view('admin.sucursales.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $sucursal = Sucursal::create($this->validateData($request));

        return redirect()->route('admin.sucursales.index')
            ->with('status', "Sucursal {$sucursal->nombre} creada.");
    }

    public function edit(Sucursal $sucursal): View
    {
        return view('admin.sucursales.edit', ['sucursal' => $sucursal]);
    }

    public function update(Request $request, Sucursal $sucursal): RedirectResponse
    {
        $sucursal->update($this->validateData($request, $sucursal));

        return redirect()->route('admin.sucursales.index')
            ->with('status', "Sucursal {$sucursal->nombre} actualizada.");
    }

    /**
     * Elimina una sucursal. Guarda: no se puede borrar si tiene usuarios asignados.
     */
    public function destroy(Sucursal $sucursal): RedirectResponse
    {
        if ($sucursal->users()->exists()) {
            return back()->with('status', "No puedes eliminar {$sucursal->nombre}: tiene usuarios asignados.");
        }

        $sucursal->delete();

        return back()->with('status', "Sucursal {$sucursal->nombre} eliminada.");
    }

    /**
     * Valida y normaliza los datos del formulario (los booleanos vienen de checkboxes).
     */
    private function validateData(Request $request, ?Sucursal $sucursal = null): array
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:191'],
            'codigo' => ['required', 'string', 'max:191', Rule::unique('sucursales', 'codigo')->ignore($sucursal)],
            'ciudad' => ['nullable', 'string', 'max:191'],
            'direccion' => ['nullable', 'string', 'max:191'],
        ]);

        $validated['es_central'] = $request->boolean('es_central');
        $validated['activa'] = $request->boolean('activa');

        return $validated;
    }
}
