<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Molde;
use App\Models\MoldeMantencion;
use App\Models\TipoBotellon;
use App\Services\Produccion\Moldes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Moldes (P-M11-12): ficha estilo M18 — CRUD + registrar mantención desde
 * la ficha. Permiso: `manage production` (el del módulo, cero permisos
 * nuevos). El ciclo ideal NO se edita acá: vive en la receta (la ficha lo
 * muestra con enlace).
 */
class MoldeController extends Controller
{
    public function index(): View
    {
        return view('admin.moldes.index', [
            'moldes' => Molde::with(['tipoBotellon', 'mantenciones'])->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.moldes.create', [
            'molde' => new Molde(['estado' => Molde::ESTADO_ACTIVO]),
            'tipos' => TipoBotellon::activos()->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $molde = Molde::create($this->datosValidados($request));

        return redirect()->route('admin.moldes.show', $molde)
            ->with('status', "Molde {$molde->nombre} creado.");
    }

    public function show(Molde $molde): View
    {
        $molde->load(['tipoBotellon.producto', 'mantenciones' => fn ($q) => $q->latest('id'), 'mantenciones.usuario']);

        return view('admin.moldes.show', ['molde' => $molde]);
    }

    public function edit(Molde $molde): View
    {
        return view('admin.moldes.edit', [
            'molde' => $molde,
            'tipos' => TipoBotellon::activos()->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Molde $molde): RedirectResponse
    {
        $molde->update($this->datosValidados($request, $molde));

        return redirect()->route('admin.moldes.show', $molde)
            ->with('status', "Molde {$molde->nombre} actualizado.");
    }

    /** Registrar una mantención desde la ficha: resetea el contador y re-arma el aviso. */
    public function mantencionStore(Request $request, Molde $molde, Moldes $moldes): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(MoldeMantencion::TIPOS))],
            'nota' => ['nullable', 'string', 'max:191'],
        ], [], ['tipo' => 'tipo de mantención', 'nota' => 'nota']);

        $moldes->registrarMantencion($molde, $request->user(), $data['tipo'], $data['nota'] ?? null);

        return redirect()->route('admin.moldes.show', $molde)
            ->with('status', "Mantención registrada: el contador de {$molde->nombre} vuelve a cero.");
    }

    private function datosValidados(Request $request, ?Molde $molde = null): array
    {
        $validated = $request->validate([
            'nombre' => [
                'required', 'string', 'max:191',
                Rule::unique('moldes', 'nombre')
                    ->where('tipo_botellon_id', $request->integer('tipo_botellon_id'))
                    ->ignore($molde),
            ],
            'tipo_botellon_id' => ['required', 'integer', Rule::exists('tipos_botellon', 'id')],
            'cavidades' => ['nullable', 'integer', 'min:1', 'max:64'],
            'umbral_mantencion' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'estado' => ['required', Rule::in(array_keys(Molde::ESTADOS))],
            'notas' => ['nullable', 'string', 'max:191'],
        ], [
            'nombre.unique' => 'Ya existe un molde con ese nombre para ese tipo de botellón.',
        ], [
            'tipo_botellon_id' => 'tipo de botellón',
            'umbral_mantencion' => 'umbral de mantención',
        ]);

        return $validated;
    }
}
