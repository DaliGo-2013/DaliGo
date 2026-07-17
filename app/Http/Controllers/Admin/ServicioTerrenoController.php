<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServicioTerreno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de servicios de terreno (tarifario en UF del técnico industrial):
 * editable por quien agenda (jefe/vendedores) para mantener precios y
 * especificaciones al día. Sin borrar: un servicio que ya no se ofrece se
 * desactiva (la agenda histórica lo sigue mostrando).
 */
class ServicioTerrenoController extends Controller
{
    public function index(): View
    {
        return view('admin.servicios-terreno.index', [
            'servicios' => ServicioTerreno::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.servicios-terreno.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $servicio = ServicioTerreno::create($this->validateData($request));

        return redirect()->route('admin.servicios-terreno.index')
            ->with('status', "Servicio «{$servicio->nombre}» creado.");
    }

    public function edit(ServicioTerreno $servicio): View
    {
        return view('admin.servicios-terreno.edit', ['servicio' => $servicio]);
    }

    public function update(Request $request, ServicioTerreno $servicio): RedirectResponse
    {
        $servicio->update($this->validateData($request, $servicio));

        return redirect()->route('admin.servicios-terreno.index')
            ->with('status', "Servicio «{$servicio->nombre}» actualizado.");
    }

    private function validateData(Request $request, ?ServicioTerreno $servicio = null): array
    {
        // Coma decimal chilena → punto (ej. "2,5" UF).
        $valor = str_replace(',', '.', trim((string) $request->input('valor_uf')));
        $request->merge(['valor_uf' => $valor === '' ? null : $valor]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:191',
                \Illuminate\Validation\Rule::unique('servicios_terreno', 'nombre')->ignore($servicio?->id)],
            'valor_uf' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'duracion' => ['nullable', 'string', 'max:191'],
            'incluye' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
        ]);

        // El checkbox llega '1'/'0' (hidden de respaldo) o ausente → boolean.
        $data['activo'] = $request->boolean('activo');

        return $data;
    }
}
