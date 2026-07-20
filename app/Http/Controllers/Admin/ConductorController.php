<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Conductores (choferes de ruta): administrables desde la app (los usa el
 * selector del ingreso por lote). Sin borrar: uno que ya no maneja se desactiva
 * (los lotes históricos guardan el nombre denormalizado).
 */
class ConductorController extends Controller
{
    public function index(): View
    {
        return view('admin.conductores.index', [
            'conductores' => Conductor::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.conductores.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $conductor = Conductor::create($this->validateData($request));

        return redirect()->route('admin.conductores.index')
            ->with('status', "Conductor «{$conductor->nombre}» agregado.");
    }

    public function edit(Conductor $conductor): View
    {
        return view('admin.conductores.edit', ['conductor' => $conductor]);
    }

    public function update(Request $request, Conductor $conductor): RedirectResponse
    {
        $conductor->update($this->validateData($request, $conductor));

        return redirect()->route('admin.conductores.index')
            ->with('status', "Conductor «{$conductor->nombre}» actualizado.");
    }

    private function validateData(Request $request, ?Conductor $conductor = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:191', Rule::unique('conductores', 'nombre')->ignore($conductor?->id)],
        ]);

        // Checkbox '1'/'0' (hidden de respaldo) o ausente → boolean.
        $data['activo'] = $request->boolean('activo');

        return $data;
    }
}
