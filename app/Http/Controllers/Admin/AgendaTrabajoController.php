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
        $prev = $cursor->copy()->subMonth();
        $next = $cursor->copy()->addMonth();

        $trabajos = AgendaTrabajo::delMes($anio, $mes)
            ->with(['servicio', 'tecnico'])
            ->get();

        return view('admin.agenda-terreno.index', [
            'trabajos' => $trabajos,
            // Solicitudes del cliente (QR) esperando coordinación (sin fecha).
            'porCoordinar' => AgendaTrabajo::porCoordinar()->with('servicio')->get(),
            'anio' => $anio,
            'mes' => $mes,
            'mesLabel' => ucfirst($cursor->translatedFormat('F Y')),
            'anterior' => ['anio' => $prev->year, 'mes' => $prev->month],
            'siguiente' => ['anio' => $next->year, 'mes' => $next->month],
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
            // Incluye el servicio ACTUAL del trabajo aunque esté inactivo: si no
            // estuviera en el select, guardar cualquier edición lo desvincularía
            // en silencio (x-model resetea a la opción vacía).
            $this->formData($trabajo)
        ));
    }

    public function update(Request $request, AgendaTrabajo $trabajo): RedirectResponse
    {
        $data = $this->validateData($request, editando: true);

        $trabajo->update($data);

        // Una solicitud puede seguir sin fecha: se vuelve al mes actual.
        $destino = $trabajo->fecha ?? now();

        return redirect()->route('admin.agenda-terreno.index', ['anio' => $destino->year, 'mes' => $destino->month])
            ->with('status', 'Trabajo actualizado.');
    }

    public function destroy(AgendaTrabajo $trabajo): RedirectResponse
    {
        $trabajo->delete();

        return back()->with('status', 'Trabajo eliminado de la agenda.');
    }

    /**
     * Cambia SOLO el estado (el técnico marca realizado desde la lista, sin
     * entrar al formulario). Quien solo VE la agenda (técnico industrial) puede
     * únicamente cerrar: agendado → realizado; cancelar o reabrir exige el
     * permiso de agendar (jefe/vendedores).
     */
    public function estado(Request $request, AgendaTrabajo $trabajo): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(AgendaTrabajo::ESTADOS)],
            'notas_tecnico' => ['nullable', 'string'],
        ]);

        if (! $request->user()->can('agendar servicio terreno')
            && ! ($trabajo->estado === 'agendado' && $data['estado'] === 'realizado')) {
            abort(403, 'Solo puedes marcar como realizado un trabajo agendado.');
        }

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
            // Cliente NUEVO (no elegido de la lista): el hidden puede traer 0 →
            // se trata como null para que `exists` no rechace el agendamiento.
            'cliente_id' => $request->input('cliente_id') ?: null,
        ]);

        return $request->validate([
            'tipo' => ['required', Rule::in(AgendaTrabajo::TIPOS)],
            // Una SOLICITUD del cliente aún no tiene fecha real (se pone al
            // coordinar); en cualquier otro estado la fecha es obligatoria.
            'fecha' => [Rule::requiredIf(fn () => $request->input('estado') !== 'solicitado'), 'nullable', 'date'],
            'fecha_preferida' => ['nullable', 'date'],
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
     * Combos del formulario: servicios del catálogo (activos + el actual del
     * trabajo aunque esté inactivo, para no desvincularlo al editar) y técnicos
     * industriales (rol) para asignar. `serviciosJs` es el mapa que consume el
     * Alpine del detalle en vivo (construido UNA vez para create y edit).
     */
    private function formData(?AgendaTrabajo $trabajo = null): array
    {
        $servicios = ServicioTerreno::activos()->get();
        if ($trabajo?->servicio && ! $servicios->contains('id', $trabajo->servicio_terreno_id)) {
            $servicios->push($trabajo->servicio);
        }

        return [
            'tipos' => AgendaTrabajo::TIPOS,
            'estados' => AgendaTrabajo::ESTADOS,
            'servicios' => $servicios,
            'serviciosJs' => $servicios->keyBy('id')->map(fn (ServicioTerreno $s) => [
                'valor_uf' => $s->valor_uf_fmt,
                'duracion' => $s->duracion,
                'incluye' => $s->incluye,
                'observaciones' => $s->observaciones,
            ]),
            'tecnicos' => User::role('tecnico_industrial')->orderBy('name')->get(),
        ];
    }
}
