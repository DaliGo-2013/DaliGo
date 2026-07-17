<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgendaTrabajo;
use App\Models\Cliente;
use App\Models\ServicioTerreno;
use App\Models\User;
use App\Rules\RutChileno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Agenda del técnico industrial (servicio en terreno): el jefe o los
 * vendedores agendan mantenciones/reparaciones/instalaciones (plantas de
 * osmosis, llenadoras, lavadoras) con el cliente y lo que hay que hacer;
 * el técnico ve su mes (lista agrupada por día) y marca lo realizado.
 */
class AgendaTrabajoController extends Controller
{
    public function index(Request $request): View
    {
        $v = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $anio = isset($v['anio']) ? (int) $v['anio'] : now()->year;
        $mes = isset($v['mes']) ? (int) $v['mes'] : now()->month;
        $cursor = Carbon::create($anio, $mes, 1);

        $trabajos = AgendaTrabajo::delMes($anio, $mes)
            ->with(['servicio', 'tecnico'])
            ->get();

        return view('admin.agenda-terreno.index', [
            'trabajos' => $trabajos,
            'anio' => $anio,
            'mes' => $mes,
            'mesLabel' => ucfirst($cursor->translatedFormat('F Y')),
            'anterior' => ['anio' => $cursor->copy()->subMonth()->year, 'mes' => $cursor->copy()->subMonth()->month],
            'siguiente' => ['anio' => $cursor->copy()->addMonth()->year, 'mes' => $cursor->copy()->addMonth()->month],
        ]);
    }

    public function create(): View
    {
        return view('admin.agenda-terreno.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['creado_por'] = $request->user()->name;

        $trabajo = AgendaTrabajo::create($data);

        return redirect()->route('admin.agenda-terreno.index', ['anio' => $trabajo->fecha->year, 'mes' => $trabajo->fecha->month])
            ->with('status', "Trabajo agendado para el {$trabajo->fecha->format('d-m-Y')} ({$trabajo->tipo_label}, {$trabajo->cliente_nombre}).");
    }

    public function edit(AgendaTrabajo $trabajo): View
    {
        return view('admin.agenda-terreno.edit', array_merge(
            ['trabajo' => $trabajo->load(['servicio', 'tecnico'])],
            $this->formData()
        ));
    }

    public function update(Request $request, AgendaTrabajo $trabajo): RedirectResponse
    {
        $data = $this->validateData($request, editando: true);

        $trabajo->update($data);

        return redirect()->route('admin.agenda-terreno.index', ['anio' => $trabajo->fecha->year, 'mes' => $trabajo->fecha->month])
            ->with('status', 'Trabajo actualizado.');
    }

    public function destroy(AgendaTrabajo $trabajo): RedirectResponse
    {
        $trabajo->delete();

        return back()->with('status', 'Trabajo eliminado de la agenda.');
    }

    /**
     * Cambia SOLO el estado (el técnico marca realizado desde la lista, sin
     * entrar al formulario). Permiso: quien ve la agenda.
     */
    public function estado(Request $request, AgendaTrabajo $trabajo): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(AgendaTrabajo::ESTADOS)],
            'notas_tecnico' => ['nullable', 'string'],
        ]);

        $trabajo->update($data);

        return back()->with('status', "Trabajo de {$trabajo->cliente_nombre} marcado como {$data['estado']}.");
    }

    /**
     * Autocompletado del cliente por RUT o razón social (JSON). Mismo contrato
     * que los buscadores de ST; permiso propio de la agenda (los vendedores no
     * tienen 'manage servicio tecnico').
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
            ->get(['id', 'rut', 'razon_social', 'telefono', 'email', 'direccion', 'ciudad']);

        return response()->json($clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'rut' => $c->rut,
            'razon_social' => $c->razon_social,
            'telefono' => $c->telefono,
            'email' => $c->email,
            'direccion' => $c->direccion,
            'ciudad' => $c->ciudad,
            'label' => ($c->rut ? $c->rut.' — ' : '').$c->razon_social,
        ]));
    }

    // --- Helpers --------------------------------------------------------

    private function validateData(Request $request, bool $editando = false): array
    {
        // Normalizar el RUT (opcional aquí) a la forma canónica.
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge([
            'cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput),
        ]);

        return $request->validate([
            'tipo' => ['required', Rule::in(AgendaTrabajo::TIPOS)],
            'fecha' => ['required', 'date'],
            'estado' => $editando
                ? ['required', Rule::in(AgendaTrabajo::ESTADOS)]
                : ['nullable', Rule::in(AgendaTrabajo::ESTADOS)],
            'servicio_terreno_id' => ['nullable', 'integer', Rule::exists('servicios_terreno', 'id')],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_rut' => ['nullable', 'string', 'max:20', new RutChileno],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],
            'cliente_email' => ['nullable', 'email', 'max:191'],
            'direccion' => ['nullable', 'string', 'max:191'],
            'ciudad' => ['nullable', 'string', 'max:191'],
            'tecnico_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'descripcion' => ['nullable', 'string'],
            'notas_tecnico' => ['nullable', 'string'],
        ]);
    }

    /**
     * Combos del formulario: servicios activos del catálogo y técnicos
     * industriales (rol) para asignar.
     */
    private function formData(): array
    {
        return [
            'tipos' => AgendaTrabajo::TIPOS,
            'estados' => AgendaTrabajo::ESTADOS,
            'servicios' => ServicioTerreno::activos()->get(),
            'tecnicos' => User::role('tecnico_industrial')->orderBy('name')->get(),
        ];
    }
}
