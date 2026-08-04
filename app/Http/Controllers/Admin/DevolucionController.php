<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\DevolucionFoto;
use App\Models\DevolucionItem;
use App\Models\Sucursal;
use App\Services\Devoluciones\Devoluciones;
use App\Support\ImagenComprimida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Devoluciones (M13) — lado interno: bodega recibe (con SUS fotos, el segundo
 * momento de evidencia), categoriza (P-M13-02) y resuelve (P-M13-03: reembolso
 * vía M14 / reingreso al kardex local / rechazo). Las rutas gatean view|manage
 * para leer y manage para mutar; el flujo de dominio vive en el servicio.
 */
class DevolucionController extends Controller
{
    public function index(Request $request): View
    {
        $estado = $request->query('estado');

        $devoluciones = Devolucion::with(['items', 'sucursal'])
            ->when(in_array($estado, Devolucion::ESTADOS, true), fn ($q) => $q->where('estado', $estado))
            ->orderBy('created_at')
            ->paginate(25)
            ->withQueryString();

        // Link firmado del formulario público por sucursal, para imprimir el
        // QR del mostrador (canvas data-qr de app.js, mismo idioma que ST).
        $linksQr = Sucursal::orderBy('nombre')->get()
            ->mapWithKeys(fn (Sucursal $s) => [$s->nombre => URL::signedRoute('devolucion.create', ['sucursal' => $s->id])]);

        return view('admin.devoluciones.index', [
            'devoluciones' => $devoluciones,
            'estado' => $estado,
            'linksQr' => $linksQr,
        ]);
    }

    public function show(Devolucion $devolucion): View
    {
        $devolucion->load(['items.producto', 'fotos', 'movimientos', 'sucursal', 'cliente', 'recibidaPor', 'resueltaPor', 'conductor']);

        return view('admin.devoluciones.show', [
            'devolucion' => $devolucion,
            'fotosCliente' => $devolucion->fotos->where('origen', DevolucionFoto::CLIENTE),
            'fotosBodega' => $devolucion->fotos->where('origen', DevolucionFoto::BODEGA),
        ]);
    }

    /**
     * Sirve una foto desde el disco PRIVADO `local`. Solo con sesión y el
     * permiso de la ruta; NO es una URL pública adivinable (patrón
     * servicio-tecnico.foto).
     */
    public function foto(DevolucionFoto $foto): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($foto->ruta), 404);

        return Storage::disk('local')->response($foto->ruta);
    }

    /** Bodega recibe físicamente + SUS fotos (el segundo momento de evidencia). */
    public function recibir(Request $request, Devolucion $devolucion, Devoluciones $servicio): RedirectResponse
    {
        $request->validate([
            'fotos' => ['required', 'array', 'min:1', 'max:6'],
            'fotos.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'max:8192'],
        ]);

        try {
            $fresca = $servicio->recibir($devolucion, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        // Fotos DESPUÉS del cambio de estado (filesystem no transaccional).
        foreach ($request->file('fotos', []) as $foto) {
            try {
                $fresca->fotos()->create([
                    'ruta' => ImagenComprimida::guardar($foto, "devoluciones/fotos/{$fresca->id}"),
                    'origen' => DevolucionFoto::BODEGA,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('admin.devoluciones.show', $fresca->id)
            ->with('status', "Devolución {$fresca->folio} recibida. El cliente fue avisado por correo.");
    }

    /** Categorización + reglas por tipo y origen (P-M13-02). */
    public function evaluar(Request $request, Devolucion $devolucion, Devoluciones $servicio): RedirectResponse
    {
        $validated = $request->validate([
            'causa' => ['required', Rule::in(array_keys(Devolucion::CAUSAS))],
            // La regla dura (transporte ⇒ transportista + seguimiento) vive en
            // el SERVICIO; acá el espejo de validación para el mensaje amable.
            'transportista' => ['nullable', 'string', 'max:64', 'required_if:causa,transporte'],
            'seguimiento' => ['nullable', 'string', 'max:64', 'required_if:causa,transporte'],
            'conductor_id' => ['nullable', Rule::exists('conductores', 'id')],
            'items' => ['required', 'array'],
            'items.*' => ['required', Rule::in(array_keys(DevolucionItem::ESTADOS_PRODUCTO))],
        ]);

        try {
            $fresca = $servicio->evaluar($devolucion, $request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        return redirect()->route('admin.devoluciones.show', $fresca->id)
            ->with('status', "Devolución {$fresca->folio} evaluada: ".Devolucion::CAUSAS[$fresca->causa].'.');
    }

    /** Resolución final (P-M13-03): reembolso (M14) / reingreso (kardex) / rechazo. */
    public function resolver(Request $request, Devolucion $devolucion, Devoluciones $servicio): RedirectResponse
    {
        $validated = $request->validate([
            'salida' => ['required', Rule::in(['reembolso', 'reingreso', 'rechazo'])],
            'monto_reembolso' => ['nullable', 'integer', 'min:1', 'max:100000000', 'required_if:salida,reembolso'],
            'resolucion_motivo' => ['nullable', 'string', 'max:2000', 'required_if:salida,rechazo'],
        ]);

        try {
            $resultado = $servicio->resolver($devolucion, $request->user(), $validated['salida'], $validated);
        } catch (InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        // El flash se bifurca por lo que el MOTOR decidió (patrón M14): el
        // llamador nunca sabe de antemano si el efecto se aplicó.
        $status = $resultado instanceof \App\Models\Aprobacion && $resultado->esPendiente()
            ? "El reembolso supera el umbral: quedó pendiente de aprobación (la devolución no cambia hasta que lo aprueben)."
            : "Devolución {$devolucion->fresh()->folio} resuelta. El cliente fue avisado por correo.";

        return redirect()->route('admin.devoluciones.show', $devolucion->id)->with('status', $status);
    }
}
