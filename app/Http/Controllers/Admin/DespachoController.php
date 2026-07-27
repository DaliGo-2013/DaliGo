<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\User;
use App\Models\Zona;
use App\Services\Despachos\DespachoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Panel de despachos (jefe de bodega, permiso 'manage despachos'):
 * crear el despacho desde un documento espejado y ver la cola/listado.
 * El escaneo del QR (retiro) llega en P-DSP-04; la entrega en P-DSP-05.
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
            ->whereDoesntHave('despachos')
            ->where(fn ($q) => $q->whereNull('cancellation_status')->orWhere('cancellation_status', 0))
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
}
