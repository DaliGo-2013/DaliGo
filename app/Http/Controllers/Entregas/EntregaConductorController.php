<?php

namespace App\Http\Controllers\Entregas;

use App\Http\Controllers\Controller;
use App\Models\Despacho;
use App\Services\Despachos\DespachoService;
use App\Support\ImagenComprimida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PWA del conductor (P-DSP-05, M08-MVP): SU hoja de ruta del día y la
 * confirmación de entrega con firma + foto + hora del dispositivo.
 *
 * SCOPING duro: el conductor solo ve y confirma despachos con
 * `conductor_id = su id`. Un despacho sin conductor asignado NO aparece ni se
 * puede confirmar — la asignación es explícita (la hace el jefe al crear el
 * despacho). El panel del jefe vive aparte bajo 'manage despachos'.
 *
 * El POST de confirmación es el destino de la cola offline (IndexedDB
 * `entregas`): llega como multipart (la firma y la foto son archivos) con
 * `Accept: application/json`, puede REPETIRSE tras un corte de señal, y por eso
 * es idempotente por `entrega_uuid` (ver DespachoService::confirmarEntregaConductor).
 * Los errores de negocio van como ValidationException para que la cola reciba
 * un 422 real y los clasifique como permanentes (patrón bitácora 2026-07-02).
 */
class EntregaConductorController extends Controller
{
    /**
     * Hoja de ruta: lo que este conductor tiene EN REPARTO, agrupado por zona.
     *
     * Los datos viajan inline a la vista (x-data): con la pestaña abierta la
     * hoja sigue operable sin señal y las confirmaciones se encolan. Recargar
     * sin señal cae a /offline del SW — límite aceptado del MVP (cachear HTML
     * autenticado está prohibido, SPIKE-PWA §3).
     */
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        // Scoping conductor↔hoja (R22, aditivo): un despacho EN una parada se
        // muestra solo si su hoja está EN RUTA y es de este conductor — manda
        // la hoja, no el conductor_id copiado al despacho. Un despacho SIN
        // hoja conserva la regla original (la PWA sigue viva mientras las
        // hojas se adoptan).
        $despachos = Despacho::with(['documento.cliente', 'zona', 'parada.hoja'])
            ->enReparto()
            ->where(function ($q) use ($userId) {
                $q->whereHas('parada.hoja', fn ($qq) => $qq->enRuta()->where('conductor_id', $userId))
                    ->orWhere(fn ($qq) => $qq->whereDoesntHave('parada')->where('conductor_id', $userId));
            })
            ->oldest('retirado_at')
            ->get()
            // El orden pactado de la hoja (R3) manda; los sueltos, por hora
            // de retiro, después de las paradas.
            ->sortBy(fn (Despacho $d) => [$d->parada?->orden ?? PHP_INT_MAX, $d->retirado_at])
            ->values();

        return view('entregas.index', [
            'despachos' => $despachos,
            'porZona' => $despachos->groupBy(fn (Despacho $d) => $d->zona?->nombre ?? 'Sin zona'),
        ]);
    }

    /** Confirma la entrega (destino de la cola offline; idempotente por uuid). */
    public function confirmar(Request $request, Despacho $despacho, DespachoService $service): RedirectResponse|JsonResponse
    {
        // Scoping ANTES de validar: un despacho ajeno es 403 (la cola offline lo
        // clasifica como rechazo PERMANENTE, correcto — reintentar no lo arregla).
        // Con hoja de ruta manda LA HOJA (en_ruta y de este conductor, R22);
        // sin hoja, la regla original — ver Despacho::entregablePorConductor.
        abort_unless($despacho->entregablePorConductor($request->user()), 403);

        $data = $request->validate([
            // La cola SIEMPRE lo manda (idempotencia); el form online también.
            'entrega_uuid' => ['required', 'uuid'],
            // Hora del dispositivo al momento de firmar (offline-safe).
            'capturado_at' => ['required', 'date'],
            // Sin regla 'image': el HEIC de iPhone la rompe (gotcha M12). GD
            // re-encoda y sanea al comprimir.
            'foto' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif', 'max:8192'],
            // La firma la genera nuestro canvas (PNG); JPEG por tolerancia.
            'firma' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:2048'],
            'parcial' => ['nullable', 'boolean'],
            'entrega_observacion' => ['required_if:parcial,1', 'nullable', 'string', 'max:188'],
        ]);

        $resultado = $service->confirmarEntregaConductor($despacho, $data);

        // Los ARCHIVOS después del commit: el filesystem no es transaccional
        // (patrón LoteServicio/M12). Solo en el primer procesamiento — un
        // duplicado de la cola no debe pisar la firma original. Si una imagen
        // falla, la entrega ya está confirmada: se reporta, no se revienta.
        if (! $resultado['yaExistia']) {
            try {
                $resultado['despacho']->update([
                    'foto_path' => ImagenComprimida::guardar($request->file('foto'), "despachos/entregas/{$despacho->id}"),
                    'firma_path' => ImagenComprimida::guardar($request->file('firma'), "despachos/entregas/{$despacho->id}"),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->respuesta($request, $resultado['despacho'], $resultado['yaExistia']);
    }

    private function respuesta(Request $request, Despacho $despacho, bool $yaExistia): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'despacho' => $despacho->codigo,
                'estado' => $despacho->estado,
                'duplicado' => $yaExistia,
            ]);
        }

        return redirect()
            ->route('entregas.index')
            ->with('status', $yaExistia
                ? "La entrega de {$despacho->codigo} ya estaba registrada."
                : "Entrega de {$despacho->codigo} registrada.");
    }
}
