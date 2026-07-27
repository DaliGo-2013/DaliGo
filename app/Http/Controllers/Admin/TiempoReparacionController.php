<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TiempoReparacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Costos generales de reparación": catálogo de tiempos estándar por trabajo.
 * Jefatura fija las horas que lleva cada trabajo del taller; con eso la mano de
 * obra de las órdenes se calcula sola y el técnico no la puede modificar. No se
 * borra (un trabajo que ya no aplica se desactiva); el histórico lo conserva.
 */
class TiempoReparacionController extends Controller
{
    public function index(): View
    {
        return view('admin.tiempos-reparacion.index', [
            // Agrupados por su grupo (Reparada / Sin solución…) para leerse como el catálogo.
            'porGrupo' => TiempoReparacion::orderBy('grupo')->orderBy('trabajo')->get()->groupBy('grupo'),
            'valorHora' => $this->valorHora(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tiempos-reparacion.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tiempo = TiempoReparacion::create($this->validateData($request));

        return redirect()->route('admin.tiempos-reparacion.index')
            ->with('status', "Trabajo «{$tiempo->trabajo}» agregado ({$tiempo->horas_fmt} h).");
    }

    public function edit(TiempoReparacion $tiempo): View
    {
        return view('admin.tiempos-reparacion.edit', ['tiempo' => $tiempo]);
    }

    public function update(Request $request, TiempoReparacion $tiempo): RedirectResponse
    {
        $tiempo->update($this->validateData($request, $tiempo));

        return redirect()->route('admin.tiempos-reparacion.index')
            ->with('status', "Trabajo «{$tiempo->trabajo}» actualizado ({$tiempo->horas_fmt} h).");
    }

    private function validateData(Request $request, ?TiempoReparacion $tiempo = null): array
    {
        // Coma decimal chilena → punto (ej. "1,5" h).
        $horas = str_replace(',', '.', trim((string) $request->input('horas')));
        $request->merge(['horas' => $horas === '' ? null : $horas]);

        $data = $request->validate([
            'trabajo' => ['required', 'string', 'max:191',
                Rule::unique('tiempos_reparacion', 'trabajo')->ignore($tiempo?->id)],
            'horas' => ['required', 'numeric', 'min:0', 'max:24'],
            'grupo' => ['nullable', 'string', 'max:191'],
        ]);

        $data['activo'] = $request->boolean('activo');

        return $data;
    }

    /** Valor hora vigente (para mostrar la mano de obra que implica cada tiempo). */
    private function valorHora(): ?int
    {
        $sku = config('servicio_tecnico.sku_hora_servicio');
        if (! $sku) {
            return null;
        }

        $producto = \App\Models\Producto::where('sku', $sku)->with('precios.lista')->first();
        if (! $producto) {
            return null;
        }

        $pr = $producto->precios->first(fn ($x) => (bool) ($x->lista?->activa)) ?? $producto->precios->first();

        return $pr ? (int) round((float) $pr->precio_con_iva) : null;
    }
}
