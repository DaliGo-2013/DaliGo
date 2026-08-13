<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\AgendaTrabajo;
use App\Models\Cliente;
use App\Models\ServicioTerreno;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use App\Support\FechaNegocio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Solicitud PÚBLICA de visita/revisión INDUSTRIAL (QR): el cliente pide que el
 * técnico industrial vaya a su planta (lavadoras, llenadoras, plantas de
 * osmosis). SIEMPRE es una visita técnica —diagnóstico + cotización—: el cliente
 * NO elige el tipo de trabajo (pedido del técnico industrial, 13-08-2026; ver
 * AgendaTrabajo::TIPO_PUBLICO), porque no puede saber si lo suyo es mantención,
 * reparación o instalación, y elegir mal desviaba la visita. El trabajo que
 * salga de la visita lo agenda después el vendedor o el jefe de ventas por el
 * flujo interno, hablando con el cliente. Acá solo indica —opcional— el servicio
 * del tarifario, deja sus datos y una fecha PREFERIDA opcional. Entra a la
 * Agenda de terreno como 'solicitado' (sin fecha real): el jefe/vendedor la
 * coordina y ahí queda agendada.
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
            'servicios' => ServicioTerreno::activos()->get(),
            // Volver a la pantalla principal del QR (firmada) para elegir otro
            // modo de ingreso (por unidad / por cantidad).
            'urlInicio' => URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursal->id]),
        ]);
    }

    /**
     * ¿Está libre ese día? — lo que alimenta el cartel en vivo del campo de fecha.
     *
     * Pedido del dueño (13-08-2026): «cuando el cliente ingrese una fecha que diga si está
     * disponible u ocupada, un cartel de advertencia que no se puede ese día o varios días».
     * El chequeo YA EXISTÍA en `store()` pero recién al enviar: el cliente llenaba nombre,
     * RUT, teléfono, correo, dirección y ciudad, apretaba Enviar y ahí se enteraba. Esto es
     * el MISMO `AgendaTrabajo::disponibilidad()` —que a su vez es el mismo `conflictos()`—
     * adelantado al momento de elegir la fecha. Un criterio, no dos.
     *
     * DEVUELVE SOLO FECHAS Y BOOLEANOS. Es un endpoint público y sin firma: contestar
     * «ocupado porque el técnico está en Aguas Claras, en Talca» sería contarle a cualquiera
     * para quién trabaja la empresa y dónde. El mensaje del admin sí nombra cliente y ciudad,
     * y ahí está bien — del otro lado hay alguien con permiso.
     */
    public function disponibilidad(Request $request): JsonResponse
    {
        $hoy = FechaNegocio::hoy();

        $data = $request->validate([
            // La ventana se acota a un año: sin tope, esto es un recorrido gratis por la
            // agenda de la empresa hacia el futuro infinito.
            'fecha' => ['required', 'date', 'after_or_equal:'.$hoy,
                'before_or_equal:'.Carbon::parse($hoy)->addYear()->toDateString()],
        ]);

        $d = AgendaTrabajo::disponibilidad($data['fecha']);

        // Las etiquetas se arman ACÁ y no en el navegador: los meses en castellano y el «de»
        // los pone Carbon con locale forzado, no una tabla de nombres copiada en el JS que se
        // desincroniza con el resto de la app. Y el locale va explícito porque la app corre
        // con APP_LOCALE=en por defecto.
        $enEspanol = fn (string $f) => Carbon::parse($f)->locale('es');

        return response()->json([
            'ocupado' => $d['ocupado'],
            'dias' => $d['dias'],
            'proximo_libre' => $d['proximo_libre'],
            'etiqueta_tramo' => $d['ocupado']
                ? ($d['dias'] > 1
                    ? 'del '.$enEspanol($d['desde'])->translatedFormat('j').' al '.$enEspanol($d['hasta'])->translatedFormat('j \d\e F')
                    : 'el '.$enEspanol($d['desde'])->translatedFormat('j \d\e F'))
                : null,
            'etiqueta_proximo' => $d['proximo_libre']
                ? $enEspanol($d['proximo_libre'])->translatedFormat('l j \d\e F')
                : null,
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
            // `tipo` NO se valida porque NO se acepta: lo público es siempre una
            // visita técnica (ver AgendaTrabajo::TIPO_PUBLICO). Se fija abajo, en
            // el servidor, y no se lee de la petición — este POST no lleva firma,
            // así que esconder el campo del formulario no seria una defensa: un
            // `tipo=instalacion` enviado a mano se agendaria solo.
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
            // Disponibilidad libre del cliente (cuándo puede/no): la usa quien coordina.
            'disponibilidad' => ['nullable', 'string', 'max:1000'],
        ]);

        // Si la fecha preferida cae en días en que el técnico ya está ocupado o de
        // viaje, no se puede pedir para entonces: se pide elegir otra fecha.
        if (! empty($data['fecha_preferida'])
            && AgendaTrabajo::conflictos($data['fecha_preferida'], $data['fecha_preferida'])->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fecha_preferida' => 'En esa fecha el técnico no estará disponible (fuera o con la agenda ocupada). Por favor elige otra fecha preferida.',
            ]);
        }

        // Si el RUT ya está en el catálogo, la solicitud nace ENLAZADA a esa
        // ficha (invisible para el cliente): quien coordina la reconoce de una y
        // reutiliza los datos guardados. Si no está, queda sin enlazar (null).
        $clienteCatalogo = Cliente::buscarPorRut($data['cliente_rut']);

        $trabajo = AgendaTrabajo::create([
            'tipo' => AgendaTrabajo::TIPO_PUBLICO,
            'fecha' => null,                    // la pone quien coordina
            'fecha_preferida' => $data['fecha_preferida'] ?? null,
            'estado' => 'solicitado',
            'servicio_terreno_id' => $data['servicio_terreno_id'] ?? null,
            'cliente_id' => $clienteCatalogo?->id,
            'cliente_nombre' => $data['cliente_nombre'],
            'cliente_rut' => $data['cliente_rut'],
            'cliente_telefono' => $data['cliente_telefono'],
            'cliente_email' => $data['cliente_email'],
            'direccion' => $data['direccion'],
            'ciudad' => $data['ciudad'],
            'descripcion' => $data['descripcion'],
            'disponibilidad' => $data['disponibilidad'] ?? null,
            'creado_por' => 'Cliente (QR)',
        ]);

        // Aviso a ventas (jefe + vendedores) de que hay una solicitud por
        // coordinar. Secundario: si el aviso falla, la solicitud ya quedó
        // registrada y el cliente no debe ver un error.
        try {
            $trabajo->notificarPorCoordinar();
        } catch (\Throwable $e) {
            report($e);
        }

        // La sucursal viaja firmada en el link de "gracias" (el trabajo no la
        // persiste) para poder ofrecer "Volver al inicio" del QR desde ahí.
        return redirect()->to(URL::signedRoute('visita-industrial.gracias', [
            'trabajo' => $trabajo->id,
            'sucursal' => $data['sucursal_id'],
        ]));
    }

    /**
     * Pantalla de "listo": confirma que la solicitud quedó registrada y que
     * lo llamarán para coordinar. Link firmado (no enumerable).
     */
    public function gracias(Request $request, AgendaTrabajo $trabajo): View
    {
        // Sucursal embebida en el link firmado → botón "Volver al inicio" del QR.
        $sucursalId = $request->integer('sucursal');

        return view('publico.taller.gracias-visita', [
            'trabajo' => $trabajo->load('servicio'),
            'urlInicio' => $sucursalId
                ? URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursalId])
                : null,
        ]);
    }
}
