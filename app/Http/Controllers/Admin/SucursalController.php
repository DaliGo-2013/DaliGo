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
     * Elimina una sucursal. Guardas COMPLETAS (P-M04-11): cada FK con datos
     * bloquea con un mensaje que dice QUE reasignar — el cinturon RESTRICT de
     * la BD queda de respaldo, no de primera linea (un 500 no explica nada).
     * Se consulta HojaDeRuta/Devolucion/TrasladoServicio por query directa
     * (solo LECTURA de esos modulos; la relacion inversa no es de ellos).
     */
    public function destroy(Sucursal $sucursal): RedirectResponse
    {
        if ($sucursal->users()->exists()) {
            return back()->with('status', "No puedes eliminar {$sucursal->nombre}: tiene usuarios asignados.");
        }

        if ($sucursal->maquinas()->exists()) {
            return back()->with('status', "No puedes eliminar {$sucursal->nombre}: tiene máquinas asociadas.");
        }

        if ($sucursal->bodegas()->exists()) {
            return back()->with('status', "No puedes eliminar {$sucursal->nombre}: tiene bodegas asignadas. Reasígnalas primero desde Inventario.");
        }

        if (\App\Models\HojaDeRuta::where('sucursal_id', $sucursal->id)->exists()) {
            return back()->with('status', "No puedes eliminar {$sucursal->nombre}: tiene hojas de ruta registradas.");
        }

        if (\App\Models\Devolucion::where('sucursal_id', $sucursal->id)->exists()) {
            return back()->with('status', "No puedes eliminar {$sucursal->nombre}: tiene devoluciones registradas.");
        }

        if (\App\Models\TrasladoServicio::where('sucursal_origen_id', $sucursal->id)
            ->orWhere('sucursal_destino_id', $sucursal->id)->exists()) {
            return back()->with('status', "No puedes eliminar {$sucursal->nombre}: tiene traslados de servicio técnico registrados.");
        }

        $sucursal->delete();

        return back()->with('status', "Sucursal {$sucursal->nombre} eliminada.");
    }

    /**
     * Valida y normaliza los datos del formulario (los booleanos vienen de checkboxes).
     */
    private function validateData(Request $request, ?Sucursal $sucursal = null): array
    {
        // EL CODIGO SE NORMALIZA ANTES DE VALIDAR (14-08-2026). Es una llave, no un rotulo: de
        // el salen el plazo de reparacion y la lista de sucursales que reciben taller. Editando
        // una ficha se habia retipeado «MIRADOR» como «Mirador» y el plazo del correo se cayo al
        // default sin que nada se viera roto (ver Sucursal::getDiasReparacionAttribute).
        // Normalizar ANTES y no despues es lo que hace que el `unique` compare lo mismo que se
        // va a guardar; al reves, un «buzeta» pasaria la validacion y reventaria contra el
        // indice al insertar.
        $request->merge(['codigo' => Sucursal::normalizaCodigo($request->input('codigo'))]);

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
