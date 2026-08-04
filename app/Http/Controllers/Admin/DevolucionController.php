<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\DevolucionFoto;
use App\Models\DevolucionItem;
use App\Models\Sucursal;
use App\Services\Devoluciones\Devoluciones;
use App\Support\FechaNegocio;
use App\Support\ImagenComprimida;
use Carbon\Carbon;
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

    /**
     * Informe por causa y por canal (P-M13-04, el cierre de E6): los datos
     * agregados que la biblia pide para A-12 y que hoy no existen en ninguna
     * parte. Mismo idioma que el informe de ST: período mes/año anclado en
     * FechaNegocio (solo año = año completo), whereDate en AMBOS bordes
     * (jamás whereBetween sobre casts de fecha — bitácora 2026-07-01/02),
     * COALESCE portable 5.7/SQLite para las sin evaluar, y porcentajes
     * enteros calculados ACÁ, nunca en Blade.
     */
    public function informe(Request $request): View
    {
        $v = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $anio = $v['anio'] ?? null;
        $mes = $v['mes'] ?? null;

        // Sin parámetros → el mes actual del negocio (no now(): P-TZ-01).
        if ($anio === null) {
            $anio = FechaNegocio::ahora()->year;
            $mes ??= FechaNegocio::ahora()->month;
        }

        $desde = Carbon::create($anio, $mes ?? 1, 1);
        $hasta = $mes ? $desde->copy()->endOfMonth() : $desde->copy()->endOfYear();
        [$desde, $hasta] = [$desde->toDateString(), $hasta->toDateString()];

        $delPeriodo = fn () => Devolucion::query()
            ->whereDate('created_at', '>=', $desde)
            ->whereDate('created_at', '<=', $hasta);

        $kpis = $delPeriodo()->selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN estado = 'solicitada' THEN 1 ELSE 0 END) AS por_recibir,
            SUM(CASE WHEN estado IN ('reembolsada','reingresada','rechazada') THEN 1 ELSE 0 END) AS resueltas,
            COALESCE(SUM(CASE WHEN estado = 'reembolsada' THEN monto_reembolso ELSE 0 END), 0) AS reembolsado
        ")->first();

        // Por CAUSA: las aún no evaluadas se agrupan como 'sin_evaluar' — no
        // se esconden, son la cola de trabajo de bodega.
        $porCausa = $delPeriodo()
            ->selectRaw("COALESCE(causa, 'sin_evaluar') AS clave, COUNT(*) AS cantidad")
            ->groupBy('clave')->orderByDesc('cantidad')->get()
            ->map(fn ($fila) => (object) [
                'nombre' => Devolucion::CAUSAS[$fila->clave] ?? 'Sin evaluar',
                'cantidad' => (int) $fila->cantidad,
            ]);

        // Por CANAL: el dato que la biblia pide para A-12 (de dónde vienen).
        $porCanal = $delPeriodo()
            ->selectRaw('canal AS clave, COUNT(*) AS cantidad')
            ->groupBy('clave')->orderByDesc('cantidad')->get()
            ->map(fn ($fila) => (object) [
                'nombre' => Devolucion::CANALES[$fila->clave] ?? $fila->clave,
                'cantidad' => (int) $fila->cantidad,
            ]);

        // El embudo por estado, en el ORDEN del flujo (no por frecuencia).
        $conteoEstados = $delPeriodo()
            ->selectRaw('estado, COUNT(*) AS cantidad')
            ->groupBy('estado')->pluck('cantidad', 'estado');
        $porEstado = collect(Devolucion::ESTADOS)
            ->map(fn (string $e) => (object) ['nombre' => ucfirst($e), 'cantidad' => (int) ($conteoEstados[$e] ?? 0)])
            ->filter(fn ($fila) => $fila->cantidad > 0)
            ->values();

        return view('admin.devoluciones.informe', [
            'anio' => $anio,
            'mes' => $mes,
            'anios' => range((int) FechaNegocio::ahora()->year, 2026),
            'kpis' => [
                'total' => (int) $kpis->total,
                'por_recibir' => (int) $kpis->por_recibir,
                'resueltas' => (int) $kpis->resueltas,
                'reembolsado' => (int) $kpis->reembolsado,
            ],
            'porCausa' => $porCausa,
            'porCanal' => $porCanal,
            'porEstado' => $porEstado,
            'periodoLabel' => $mes
                ? ucfirst(Carbon::create($anio, $mes, 1)->translatedFormat('F Y'))
                : 'Año '.$anio,
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
