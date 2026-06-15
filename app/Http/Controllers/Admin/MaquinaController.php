<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maquina;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaquinaController extends Controller
{
    /**
     * Listado de maquinas sopladoras con su sucursal y produccion registrada.
     */
    public function index(): View
    {
        $maquinas = Maquina::with('sucursal')
            ->withCount('registros')
            ->orderBy('nombre')
            ->get();

        return view('admin.maquinas.index', ['maquinas' => $maquinas]);
    }

    public function create(): View
    {
        return view('admin.maquinas.create', [
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maquina = Maquina::create($this->validateData($request));

        return redirect()->route('admin.maquinas.index')
            ->with('status', "Máquina {$maquina->nombre} creada.");
    }

    public function edit(Maquina $maquina): View
    {
        return view('admin.maquinas.edit', [
            'maquina' => $maquina,
            // Solo sucursales activas, pero conservando la de la maquina aunque
            // este inactiva (para no cambiar su asignacion sin querer al guardar).
            'sucursales' => Sucursal::where('activa', true)
                ->orWhere('id', $maquina->sucursal_id)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function update(Request $request, Maquina $maquina): RedirectResponse
    {
        $maquina->update($this->validateData($request, $maquina));

        return redirect()->route('admin.maquinas.index')
            ->with('status', "Máquina {$maquina->nombre} actualizada.");
    }

    /**
     * Elimina una maquina. Guarda: con produccion registrada no se borra
     * (se perderia la atribucion de las metricas); se desactiva.
     */
    public function destroy(Maquina $maquina): RedirectResponse
    {
        if ($maquina->registros()->exists()) {
            return back()->with('status', "No puedes eliminar {$maquina->nombre}: tiene producción registrada. Desactívala en su lugar.");
        }

        $maquina->delete();

        return back()->with('status', "Máquina {$maquina->nombre} eliminada.");
    }

    /**
     * Valida y normaliza los datos del formulario (los booleanos vienen de checkboxes).
     */
    private function validateData(Request $request, ?Maquina $maquina = null): array
    {
        $validated = $request->validate([
            'nombre' => [
                'required', 'string', 'max:191',
                Rule::unique('maquinas', 'nombre')
                    ->where('sucursal_id', $request->integer('sucursal_id'))
                    ->ignore($maquina),
            ],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')],
        ], [
            'nombre.unique' => 'Ya existe una máquina con ese nombre en esa sucursal.',
        ]);

        $validated['activa'] = $request->boolean('activa');

        return $validated;
    }
}
