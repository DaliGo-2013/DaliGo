<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ingreso PUBLICO a servicio tecnico por QR (P-M12-01, piloto).
 *
 * El cliente escanea un QR pegado en el mostrador (uno por sucursal) que abre
 * este formulario en SU celular, SIN login. Al enviar se crea una orden real
 * (fuente 'qr', estado 'recibido') pero SIN confirmar: el encargado del
 * mostrador la valida y recibe la maquina fisica desde el panel de admin, y ahi
 * recien se le manda el correo con el folio.
 *
 * Seguridad: el GET del QR es un link FIRMADO (URL::signedRoute) que lleva el
 * sucursal_id embebido -> no se puede alterar la sucursal sin invalidar la firma.
 * El grupo tiene throttle y el form un honeypot anti-bot. Este flujo SOLO
 * escribe: nunca lista ni muestra otras ordenes. Endurecer el POST con token
 * firmado queda para F3 (P-F3-01, ver docs/RUTA-MAESTRA.md).
 */
class IngresoTallerPublicoController extends Controller
{
    /**
     * Formulario publico. La sucursal viene firmada en el link del QR.
     */
    public function create(Request $request): View
    {
        $sucursal = Sucursal::where('activa', true)->findOrFail($request->integer('sucursal'));

        return view('publico.taller.create', [
            'sucursal' => $sucursal,
            'tipos' => OrdenServicio::TIPOS,
        ]);
    }

    /**
     * Crea la orden por QR. Validacion LIVIANA (el encargado completa lo demas):
     * el cliente no decide garantia/reparacion ni sube documentos.
     */
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: campo oculto que un humano deja vacio. Si viene lleno es un
        // bot -> cortamos sin crear nada (respuesta identica a la de exito para
        // no darle pistas).
        if (filled($request->input('sitio_web'))) {
            $sucursalId = (int) $request->input('sucursal_id');

            return redirect()->to(URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursalId]));
        }

        // Normalizar el RUT (opcional en lo publico) a la forma canonica 12345678-9.
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge([
            'cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput),
        ]);

        $data = $request->validate([
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')->where('activa', true)],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_email' => ['required', 'email', 'max:191'],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],
            'cliente_rut' => ['nullable', 'string', 'max:20', new RutChileno],
            'tipo_equipo' => ['required', Rule::in(OrdenServicio::TIPOS)],
            'numero_serie' => ['required', 'string', 'min:3', 'max:191'],
            'falla_reportada' => ['required', 'string', 'min:3'],
        ]);

        $sucursal = Sucursal::findOrFail($data['sucursal_id']);
        $hoy = now()->toDateString();

        $orden = OrdenServicio::create([
            'cliente_nombre' => $data['cliente_nombre'],
            'cliente_email' => $data['cliente_email'],
            'cliente_telefono' => $data['cliente_telefono'] ?? null,
            'cliente_rut' => $data['cliente_rut'] ?? null,
            'sucursal_id' => $sucursal->id,
            'tipo_equipo' => $data['tipo_equipo'],
            'numero_serie' => $data['numero_serie'],
            'falla_reportada' => $data['falla_reportada'],
            'fecha_ingreso' => $hoy,
            'fecha_entrega' => $sucursal->fechaEntregaEstimada($hoy)->toDateString(),
            'estado' => 'recibido',
            'fuente' => 'qr',
            'confirmada_at' => null,
        ]);

        // El correo NO se manda aqui: sale cuando el encargado confirma la
        // recepcion (asi el cliente recibe datos ya verificados).
        return redirect()->to(URL::signedRoute('ingreso-taller.gracias', ['orden' => $orden->id]));
    }

    /**
     * Pantalla de "listo" que el cliente le muestra al encargado. Link firmado:
     * no se pueden enumerar folios de otras ordenes.
     */
    public function gracias(OrdenServicio $orden): View
    {
        return view('publico.taller.gracias', [
            'orden' => $orden->load('sucursal'),
        ]);
    }
}
