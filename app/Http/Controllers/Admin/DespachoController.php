<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\User;
use App\Models\Zona;
use App\Services\Despachos\DespachoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Panel de despachos (jefe de bodega, permiso 'manage despachos'): crear el
 * despacho desde un documento espejado, ver el listado y la cola de bodega, e
 * imprimir/escanear el QR del retiro (P-DSP-04). La entrega con firma+foto del
 * conductor llega en P-DSP-05.
 *
 * SUPERFICIE DE ESCANEO — decisión de P-DSP-04 (el dictado pedía elegirla y
 * documentarla). Es **autenticada con `manage despachos` Y ADEMÁS firmada**, no
 * una de las dos:
 * - Solo firmada (semi-pública) sería un agujero: quien le saque una foto al QR
 *   —el propio cliente, alguien en la fila— marcaría el retiro desde su celular
 *   sin pasar por bodega.
 * - Solo autenticada perdería la integridad del link: la firma es lo que impide
 *   editar el código en la barra de direcciones para apuntar a otro despacho.
 *
 * QUÉ CIERRA ESTO Y QUÉ NO (precisado tras el gate del 28-07; la versión previa
 * de esta nota daba a entender más de lo que el código aplica):
 * - **SÍ cierra: «una carga no sale dos veces».** El 2º escaneo del mismo QR no
 *   cambia el estado y deja fila `doble_retiro` con su responsable.
 * - **NO cierra: «retirar una carga que no te corresponde».** El propio panel le
 *   entrega la URL firmada de CUALQUIER despacho a quien tenga `manage
 *   despachos`, así que entre operadores autorizados la firma no acota nada — no
 *   hay control por zona ni exigencia de tener el QR físico delante. Es alcance
 *   aceptado para v1; cerrarlo (cross-zona / posesión del QR) sería un paso
 *   propio y lo dicta el dueño.
 */
class DespachoController extends Controller
{
    public function index(Request $request): View
    {
        $estado = $request->query('estado');

        $despachos = Despacho::with(['documento.cliente', 'zona', 'conductor'])
            ->when(in_array($estado, Despacho::ESTADOS, true), fn ($q) => $q->where('estado', $estado))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.despachos.index', [
            'despachos' => $despachos,
            'estados' => Despacho::ESTADOS,
            'filtroEstado' => $estado,
        ]);
    }

    public function create(): View
    {
        // Documentos espejados recientes SIN despacho, los más nuevos primero.
        // No anulados según el espejo (la verdad fresca la exige el service
        // contra Bsale al crear; esto solo evita ofrecer basura evidente).
        $documentos = DocumentoVenta::with('cliente')
            ->sinDespacho()
            ->vigentes()
            ->latest('emitido_at')
            ->limit(100)
            ->get();

        return view('admin.despachos.create', [
            'documentos' => $documentos,
            'zonas' => Zona::where('activa', true)->orderBy('nombre')->get(),
            'conductores' => User::role('conductor')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, DespachoService $service): RedirectResponse
    {
        $data = $request->validate([
            'documento_venta_id' => ['required', Rule::exists('documentos_venta', 'id')],
            'zona_id' => ['nullable', Rule::exists('zonas', 'id')->where('activa', true)],
            // Mismo scope que ofrece el selector (bitácora 2026-06-30, M-3):
            // solo usuarios con rol conductor, no cualquier user id.
            'conductor_id' => [
                'nullable',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && ! User::find($value)?->hasRole('conductor')) {
                        $fail('El usuario elegido no es conductor.');
                    }
                },
            ],
            'transportista' => ['nullable', 'string', 'max:191'],
        ]);

        $documento = DocumentoVenta::findOrFail($data['documento_venta_id']);

        // El service re-verifica contra Bsale (DTE anulado NO se despacha) y
        // lanza ValidationException con el mensaje para el form si algo falla.
        $despacho = $service->crearDesdeDocumento($documento, $data);

        return redirect()
            ->route('admin.despachos.index')
            ->with('status', "Despacho {$despacho->codigo} creado (folio {$documento->folio}).");
    }

    /**
     * Página imprimible con el QR del despacho (P-DSP-04). El QR apunta al link
     * FIRMADO del escaneo, con el CÓDIGO embebido y no el id: `URL::signedRoute`
     * hace que el código no se pueda alterar y `DSP-XXXXXXXX` que no se pueda
     * adivinar el del vecino (a diferencia de un id secuencial).
     *
     * Se dibuja en el cliente con `canvas[data-qr]` → `dibujarQrsMostrador` de
     * app.js (el chunk 'qrcode' ya viaja en el bundle desde M12): cero JS nuevo
     * y cero dependencia nueva de servidor.
     */
    public function qr(Despacho $despacho): View
    {
        $despacho->load(['documento.cliente', 'zona']);

        return view('admin.despachos.qr', [
            'despacho' => $despacho,
            'url' => $despacho->urlFicha(),
        ]);
    }

    /**
     * Pantalla de escaneo (GET, solo LEE). Muestra qué es el despacho y su
     * estado; el retiro lo confirma el POST de abajo.
     *
     * Por qué GET no muta: un escáner de bodega o un F5 repiten el GET, y un
     * GET que marcara el retiro convertiría cada recarga en un "segundo
     * escaneo" con su alerta de doble retiro. Alertas falsas = alerta que nadie
     * mira, justo lo contrario de lo que este paso construye.
     */
    public function escanear(string $codigo): View
    {
        $despacho = $this->porCodigo($codigo);

        return view('admin.despachos.escanear', [
            'despacho' => $despacho,
            'urlRetiro' => route('admin.despachos.retiro', $despacho->codigo),
        ]);
    }

    /**
     * Confirma el retiro (POST): es EL escaneo que deja fila en
     * `escaneos_despacho`, válido o no. No redirige a una pantalla neutra —
     * vuelve a la misma con el veredicto, porque el operador tiene la
     * mercadería en la mano y necesita leer "entrega" o "NO entregues" ahí.
     */
    public function retiro(string $codigo, DespachoService $service): RedirectResponse
    {
        $despacho = $this->porCodigo($codigo);
        $resultado = $service->validarRetiro($despacho, request()->user());

        // Vuelve a la ficha por su URL FIRMADA (la pantalla exige firma).
        return redirect()->to($despacho->urlFicha())->with('escaneo', $resultado['resultado']);
    }

    /**
     * Cola de bodega ("McDonald's"): los despachos preparados esperando retiro,
     * en pantalla de monitor. Se refresca sola por polling — ver colaConteo().
     */
    public function cola(): View
    {
        // La firma sale del MISMO helper que la del JSON: si se calcularan por
        // separado podrían divergir y el monitor recargaría en loop (o nunca).
        return view('admin.despachos.cola', [
            'firma' => $this->firmaDeLaCola(),
            'despachos' => Despacho::with(['documento.cliente', 'zona'])
                ->pendienteDeRetiro()
                ->oldest('id')   // el que espera hace más rato, primero
                ->limit(12)
                ->get(),
            'total' => Despacho::pendienteDeRetiro()->count(),
        ]);
    }

    /**
     * Conteo liviano (JSON) para el poll de la cola: la pantalla se recarga solo
     * cuando el número cambió. Mismo patrón que `porConfirmarConteo()` de
     * Servicio Técnico — un monitor de bodega colgado todo el día no puede
     * recargar HTML completo cada pocos segundos.
     */
    public function colaConteo(): JsonResponse
    {
        return response()->json([
            'total' => Despacho::pendienteDeRetiro()->count(),
            'firma' => $this->firmaDeLaCola(),
        ]);
    }

    /**
     * Huella del CONTENIDO de la cola, no solo su tamaño.
     *
     * Con el total pelado, si en la misma ventana del poll entra una carga y sale
     * otra, el número no cambia → el monitor no recarga y sigue mostrando una
     * carga YA RETIRADA como «Esperando»… con el número correcto, así que parece
     * fresco. En bodega eso es peor que un monitor congelado (hallazgo 4 del gate
     * del Director, 28-07).
     */
    private function firmaDeLaCola(): string
    {
        return md5(Despacho::pendienteDeRetiro()->orderBy('id')->pluck('codigo')->implode('|'));
    }

    /** Cierra el despacho: entrega total, o PARCIAL con el saldo pendiente. */
    public function entrega(Request $request, Despacho $despacho, DespachoService $service): RedirectResponse
    {
        $data = $request->validate([
            'parcial' => ['nullable', 'boolean'],
            'entrega_observacion' => ['nullable', 'string', 'max:188'],
        ]);

        $parcial = (bool) ($data['parcial'] ?? false);
        $service->registrarEntrega($despacho, $parcial, $data['entrega_observacion'] ?? null);

        return redirect()
            ->route('admin.despachos.index')
            ->with('status', $parcial
                ? "Despacho {$despacho->codigo}: entrega PARCIAL registrada."
                : "Despacho {$despacho->codigo}: entrega registrada.");
    }

    /** El despacho por su código del QR (no por id: no enumerable). */
    private function porCodigo(string $codigo): Despacho
    {
        return Despacho::with(['documento.cliente', 'zona', 'conductor', 'escaneos.operador'])
            ->where('codigo', $codigo)
            ->firstOrFail();
    }

}
