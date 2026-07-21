<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\AgendaTrabajo;
use App\Models\Cliente;
use App\Models\ServicioTerreno;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Solicitud PÚBLICA de visita/revisión INDUSTRIAL (QR): el cliente pide que el
 * técnico industrial vaya a su planta (lavadoras, llenadoras, plantas de
 * osmosis). Elige el tipo de trabajo (Visita técnica primero: diagnóstico +
 * cotización) y opcionalmente el servicio del tarifario, deja sus datos y una
 * fecha PREFERIDA opcional. Entra a la Agenda de terreno como 'solicitado'
 * (sin fecha real): el jefe/vendedor la coordina y ahí queda agendada.
 *
 * Mismo esquema de seguridad que el ingreso por QR: GET firmado (sucursal
 * embebida), POST con honeypot, throttle del grupo.
 */
class VisitaIndustrialPublicoController extends Controller
{
    public function create(Request $request): View
    {
        $sucursal = Sucursal::where('activa', true)->findOrFail($request->integer('sucursal'));

        return view('publico.taller.create-visita', [
            'sucursal' => $sucursal,
            'tipos' => AgendaTrabajo::TIPOS,
            'servicios' => ServicioTerreno::activos()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: mismo tratamiento que el resto del flujo público.
        if (filled($request->input('sitio_web'))) {
            $sucursalId = (int) $request->input('sucursal_id');

            return redirect()->to(URL::signedRoute('visita-industrial.create', ['sucursal' => $sucursalId]));
        }

        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge([
            'cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput),
        ]);

        $data = $request->validate([
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')->where('activa', true)],
            'tipo' => ['required', Rule::in(AgendaTrabajo::TIPOS)],
            'servicio_terreno_id' => ['nullable', 'integer', Rule::exists('servicios_terreno', 'id')->where('activo', true)],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            'cliente_telefono' => ['required', 'string', 'max:30'],
            'cliente_email' => ['required', 'email', 'max:191'],
            'direccion' => ['required', 'string', 'min:3', 'max:191'],
            'ciudad' => ['required', 'string', 'min:2', 'max:191'],
            // 'today' resolvía en UTC y de noche RECHAZABA el "hoy" del cliente
            // chileno (P-TZ-01): el borde es el día de negocio, no el del server.
            'fecha_preferida' => ['nullable', 'date', 'after_or_equal:'.\App\Support\FechaNegocio::hoy()],
            'descripcion' => ['required', 'string', 'min:3'],
        ]);

        // Si la fecha preferida cae en días en que el técnico ya está ocupado o de
        // viaje, no se puede pedir para entonces: se pide elegir otra fecha.
        if (! empty($data['fecha_preferida'])
            && AgendaTrabajo::conflictos($data['fecha_preferida'], $data['fecha_preferida'])->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fecha_preferida' => 'En esa fecha el técnico no estará disponible (fuera o con la agenda ocupada). Por favor elige otra fecha preferida.',
            ]);
        }

        $trabajo = AgendaTrabajo::create([
            'tipo' => $data['tipo'],
            'fecha' => null,                    // la pone quien coordina
            'fecha_preferida' => $data['fecha_preferida'] ?? null,
            'estado' => 'solicitado',
            'servicio_terreno_id' => $data['servicio_terreno_id'] ?? null,
            'cliente_nombre' => $data['cliente_nombre'],
            'cliente_rut' => $data['cliente_rut'],
            'cliente_telefono' => $data['cliente_telefono'],
            'cliente_email' => $data['cliente_email'],
            'direccion' => $data['direccion'],
            'ciudad' => $data['ciudad'],
            'descripcion' => $data['descripcion'],
            'creado_por' => 'Cliente (QR)',
        ]);

        return redirect()->to(URL::signedRoute('visita-industrial.gracias', ['trabajo' => $trabajo->id]));
    }

    /**
     * Pantalla de "listo": confirma que la solicitud quedó registrada y que
     * lo llamarán para coordinar. Link firmado (no enumerable).
     */
    public function gracias(AgendaTrabajo $trabajo): View
    {
        return view('publico.taller.gracias-visita', [
            'trabajo' => $trabajo->load('servicio'),
        ]);
    }
}
