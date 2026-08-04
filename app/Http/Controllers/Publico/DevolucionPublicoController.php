<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Devolucion;
use App\Models\DevolucionFoto;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use App\Services\Devoluciones\Devoluciones;
use App\Support\ImagenComprimida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Devolución declarada por el CLIENTE desde su celular (M13 · P-M13-01,
 * flujo A-12). Sin login: llega por el QR/link del mostrador o del correo.
 *
 * Seguridad (frontera hostil, PLAN-M13 §1.1 — la variante ENDURECIDA):
 * - GET y POST firmados (URL::signedRoute con la sucursal embebida): alterar
 *   cualquier parámetro invalida la firma. No espera al hardening P-F3-01.
 * - Grupo con throttle PROPIO (12,1): el limitador de invitados no distingue
 *   rutas, y compartir el 6,1 del QR dejaba fuera al cliente que reintenta.
 * - Honeypot `sitio_web` con respuesta IDÉNTICA al éxito (no dar pistas).
 * - Binding del "gracias" por token de 64 (el id jamás viaja): sin enumerar.
 * - Este flujo SOLO escribe: nunca lista ni muestra otras devoluciones.
 * - Fotos: nunca la regla `image` (HEIC de iPhone); mimetypes + max:8192 y
 *   GD re-encoda (sanea) al disco PRIVADO. Techos VERIFICADOS en el server
 *   (docs/qa/INFRA/2026-08-03): post_max_size=30M, max_file_uploads=20 —
 *   2 fotos × 8M + campos entra con holgura.
 */
class DevolucionPublicoController extends Controller
{
    public function create(Request $request): View
    {
        $sucursal = Sucursal::findOrFail((int) $request->query('sucursal'));

        return view('publico.devolucion.create', [
            'sucursal' => $sucursal,
            'canales' => Devolucion::CANALES,
            'fotosMin' => max(1, (int) Configuracion::get('devolucion_fotos_min', 2)),
            // El POST va firmado: la firma viaja en el action del form.
            'urlStore' => URL::signedRoute('devolucion.store', ['sucursal' => $sucursal->id]),
        ]);
    }

    public function store(Request $request, Devoluciones $servicio): RedirectResponse
    {
        $sucursalId = (int) $request->query('sucursal');

        // Honeypot: campo oculto que un humano deja vacío. Si viene lleno es
        // un bot → cortamos sin crear nada (respuesta idéntica a la de éxito
        // para no darle pistas).
        if (filled($request->input('sitio_web'))) {
            return redirect()->to(URL::signedRoute('devolucion.create', ['sucursal' => $sucursalId]));
        }

        // Normalizar el RUT ANTES de validar (idioma de la casa): si no se
        // puede normalizar queda el valor original para que RutChileno lo
        // rechace con su mensaje, no tragarlo como null.
        $rutInput = trim((string) $request->input('cliente_rut'));
        $request->merge(['cliente_rut' => $rutInput === '' ? null : (Cliente::normalizarRut($rutInput) ?? $rutInput)]);

        $fotosMin = max(1, (int) Configuracion::get('devolucion_fotos_min', 2));

        $validated = $request->validate([
            'cliente_nombre' => ['required', 'string', 'max:191'],
            'cliente_rut' => ['nullable', 'string', 'max:20', new RutChileno],
            'cliente_email' => ['required', 'email', 'max:191'],
            'cliente_telefono' => ['nullable', 'string', 'max:32'],
            'canal' => ['required', Rule::in(array_keys(Devolucion::CANALES))],
            'folio_referencia' => ['nullable', 'string', 'max:64'],
            'producto' => ['required', 'string', 'max:191'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'motivo' => ['required', 'string', 'max:2000'],
            // Sin la regla `image` (falla con HEIC de iPhone): mimetype +
            // tamaño, y GD re-encoda (sanea) después.
            'fotos' => ['required', 'array', "size:{$fotosMin}"],
            'fotos.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'max:8192'],
        ]);

        $devolucion = $servicio->registrar($validated + [
            'sucursal_id' => Sucursal::findOrFail($sucursalId)->id,
            'ip' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        // Fotos DESPUÉS del commit (el filesystem no es transaccional), de a
        // una: una foto que falle no tumba la devolución ya creada.
        foreach ($request->file('fotos', []) as $foto) {
            try {
                $devolucion->fotos()->create([
                    'ruta' => ImagenComprimida::guardar($foto, "devoluciones/fotos/{$devolucion->id}"),
                    'origen' => DevolucionFoto::CLIENTE,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Aviso interno al final (try/catch dentro): un aviso que falle NO
        // debe tumbar el envío del cliente, que ya tiene su folio.
        $servicio->avisarSolicitada($devolucion);

        return redirect()->to(URL::signedRoute('devolucion.gracias', ['devolucion' => $devolucion->token]));
    }

    public function gracias(Devolucion $devolucion): View
    {
        // Solo SU devolución (binding por token + firma) y solo lo mínimo:
        // folio + resumen. Nada de terceros, nada de la app.
        return view('publico.devolucion.gracias', [
            'devolucion' => $devolucion->load('items'),
            'urlInicio' => URL::signedRoute('devolucion.create', ['sucursal' => $devolucion->sucursal_id]),
        ]);
    }
}
