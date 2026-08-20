<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Despacho;
use App\Models\DocumentoVenta;
use App\Models\HojaDeRuta;
use App\Models\HojaRutaParada;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Services\Despachos\HojaRutaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * La hoja de ruta digital (P-DSP-08). Ricardo la ARMA eligiendo documentos
 * (permiso 'manage hojas ruta'); las 3 llaves de la cadena R11 son rutas y
 * permisos SEPARADOS (autorizar pagos ruta / autorizar ruta / autorizar
 * carga) — la secuencia la protege el service bajo lock, el "quién puede"
 * lo gatea cada ruta (D-014: menú y gate son el mismo permiso).
 */
class HojaRutaController extends Controller
{
    public function index(Request $request): View
    {
        $estado = $request->query('estado');

        $hojas = HojaDeRuta::with(['zona', 'conductor', 'sucursal'])
            ->withCount('paradas')
            ->when(in_array($estado, HojaDeRuta::ESTADOS, true), fn ($q) => $q->where('estado', $estado))
            ->latest('folio')
            ->paginate(25)
            ->withQueryString();

        return view('admin.hojas-ruta.index', [
            'hojas' => $hojas,
            'estados' => HojaDeRuta::ESTADOS,
            'filtroEstado' => $estado,
        ]);
    }

    public function create(): View
    {
        // Candidatos a parada: documentos vigentes que no tienen despacho o
        // cuyo despacho sigue disponible (sin hoja y con el ciclo abierto).
        // La verdad fresca la re-exige el service contra Bsale al crear.
        $documentos = DocumentoVenta::with('cliente')
            ->vigentes()
            ->where(function ($q) {
                $q->whereDoesntHave('despachos')
                    ->orWhereHas('despachos', function ($qq) {
                        $qq->whereDoesntHave('parada')
                            ->whereNotIn('estado', [Despacho::ENTREGADO, Despacho::ENTREGA_PARCIAL]);
                    });
            })
            ->latest('emitido_at')
            ->limit(100)
            ->get();

        return view('admin.hojas-ruta.create', [
            'documentos' => $documentos,
            'zonas' => Zona::where('activa', true)->orderBy('nombre')->get(),
            'sucursales' => \App\Models\Sucursal::where('activa', true)->orderBy('nombre')->get(),
            'vehiculos' => Vehiculo::activos()->orderBy('ppu')->get(),
            'conductores' => User::role('conductor')->orderBy('name')->get(),
            'estadosCobro' => HojaRutaParada::ESTADOS_COBRO,
        ]);
    }

    public function store(Request $request, HojaRutaService $service): RedirectResponse
    {
        $data = $request->validate([
            'sucursal_id' => ['required', Rule::exists('sucursales', 'id')->where('activa', true)],
            'zona_id' => ['required', Rule::exists('zonas', 'id')->where('activa', true)],
            // Mismo scope que ofrece el selector: solo vehículos ACTIVOS de
            // la flota M18 (bitácora 2026-06-30, M-3).
            'vehiculo_id' => ['required', Rule::exists('vehiculos', 'id')->where('estado', Vehiculo::ESTADO_ACTIVO)],
            'conductor_id' => [
                'required',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && ! User::find($value)?->hasRole('conductor')) {
                        $fail('El usuario elegido no es conductor.');
                    }
                },
            ],
            'peoneta_nombre' => ['nullable', 'string', 'max:191'],
            'documentos' => ['required', 'array', 'min:1'],
            'documentos.*' => ['integer', Rule::exists('documentos_venta', 'id')],
            'cobros' => ['array'],
            'cobros.*' => ['nullable', Rule::in(HojaRutaParada::ESTADOS_COBRO)],
        ]);

        $hoja = $service->crear($data);

        // Si un documento no pasa (anulado en Bsale, ya en otra hoja), la
        // ValidationException vuelve al form; la hoja creada queda en
        // borrador sin paradas y se puede completar o quedar vacía — el
        // folio ya consumido no se reusa (correlativo, R25).
        $service->generarParadas($hoja, array_map('intval', $data['documentos']), $data['cobros'] ?? []);

        return redirect()
            ->route('admin.hojas-ruta.show', $hoja)
            ->with('status', "Hoja de ruta folio {$hoja->folio} creada con {$hoja->paradas()->count()} paradas.");
    }

    public function show(HojaDeRuta $hoja): View
    {
        $hoja->load(['zona', 'sucursal', 'conductor', 'paradas.despacho.documento.cliente'])
            ->load(['pagosOkPor', 'rutaAutorizadaPor', 'cargadaPor', 'enRutaPor']);

        return view('admin.hojas-ruta.show', ['hoja' => $hoja]);
    }

    /** Llave 1 · jefe de ventas (permiso 'autorizar pagos ruta'). */
    public function autorizarPagos(Request $request, HojaDeRuta $hoja, HojaRutaService $service): RedirectResponse
    {
        $service->autorizarPagos($hoja, $request->user());

        return $this->vuelta($hoja, 'Pagos autorizados (llave 1 de '.HojaDeRuta::TOTAL_LLAVES.').');
    }

    /** Llave 2 · jefe de despacho (permiso 'autorizar ruta'). */
    public function autorizarRuta(Request $request, HojaDeRuta $hoja, HojaRutaService $service): RedirectResponse
    {
        $service->autorizarRuta($hoja, $request->user());

        return $this->vuelta($hoja, 'Ruta autorizada (llave 2 de '.HojaDeRuta::TOTAL_LLAVES.').');
    }

    /** Llave 3 · jefe de bodega (permiso 'autorizar carga'). */
    public function autorizarCarga(Request $request, HojaDeRuta $hoja, HojaRutaService $service): RedirectResponse
    {
        $service->autorizarCarga($hoja, $request->user());

        return $this->vuelta($hoja, 'Carga autorizada (llave 3 de '.HojaDeRuta::TOTAL_LLAVES.').');
    }

    /** La salida del camión: la registra bodega (mismo permiso que la llave 3). */
    public function salir(Request $request, HojaDeRuta $hoja, HojaRutaService $service): RedirectResponse
    {
        $service->salirARuta($hoja, $request->user());

        return $this->vuelta($hoja, 'El camión salió a ruta: la hora de salida quedó registrada.');
    }

    /** Reordena las paradas (R3): recibe los ids en el orden nuevo. */
    public function orden(Request $request, HojaDeRuta $hoja, HojaRutaService $service): RedirectResponse
    {
        $data = $request->validate([
            'paradas' => ['required', 'array', 'min:1'],
            'paradas.*' => ['integer'],
        ]);

        $service->reordenar($hoja, array_map('intval', $data['paradas']));

        return $this->vuelta($hoja, 'Orden de paradas actualizado.');
    }

    private function vuelta(HojaDeRuta $hoja, string $mensaje): RedirectResponse
    {
        return redirect()->route('admin.hojas-ruta.show', $hoja)->with('status', $mensaje);
    }
}
