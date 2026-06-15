<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoBotellon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TipoBotellonController extends Controller
{
    /**
     * Listado de tipos de botellon con su produccion registrada.
     */
    public function index(): View
    {
        $tipos = TipoBotellon::withCount('registros')->orderBy('nombre')->get();

        return view('admin.tipos-botellon.index', ['tipos' => $tipos]);
    }

    public function create(): View
    {
        return view('admin.tipos-botellon.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tipo = TipoBotellon::create($this->validateData($request));

        return redirect()->route('admin.tipos-botellon.index')
            ->with('status', "Tipo {$tipo->nombre} creado.");
    }

    public function edit(TipoBotellon $tipoBotellon): View
    {
        return view('admin.tipos-botellon.edit', ['tipo' => $tipoBotellon]);
    }

    public function update(Request $request, TipoBotellon $tipoBotellon): RedirectResponse
    {
        $tipoBotellon->update($this->validateData($request, $tipoBotellon));

        return redirect()->route('admin.tipos-botellon.index')
            ->with('status', "Tipo {$tipoBotellon->nombre} actualizado.");
    }

    /**
     * Elimina un tipo. Guarda: con produccion registrada no se borra; se desactiva.
     */
    public function destroy(TipoBotellon $tipoBotellon): RedirectResponse
    {
        if ($tipoBotellon->registros()->exists()) {
            return back()->with('status', "No puedes eliminar {$tipoBotellon->nombre}: tiene producción registrada. Desactívalo en su lugar.");
        }

        $tipoBotellon->delete();

        return back()->with('status', "Tipo {$tipoBotellon->nombre} eliminado.");
    }

    /**
     * Valida y normaliza los datos del formulario (los booleanos vienen de checkboxes).
     */
    private function validateData(Request $request, ?TipoBotellon $tipo = null): array
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:191'],
            'codigo' => ['required', 'string', 'max:191', Rule::unique('tipos_botellon', 'codigo')->ignore($tipo)],
        ]);

        $validated['activo'] = $request->boolean('activo');

        return $validated;
    }
}
