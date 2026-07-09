<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Rules\RutChileno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

        // El N° de serie es obligatorio solo para tipos con serie unica
        // (dispensador/lavadora); para bombas/herramientas es opcional.
        $serieObligatoria = in_array(
            $request->input('tipo_equipo'),
            OrdenServicio::SERIE_OBLIGATORIA_TIPOS,
            true,
        );

        // Si el cliente marca Garantia, el documento de compra que la respalda es
        // obligatorio (factura/boleta + N° + fecha, no futura).
        $esGarantia = $request->input('facturacion') === 'garantia';

        $data = $request->validate([
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')->where('activa', true)],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'cliente_email' => ['required', 'email', 'max:191'],
            'cliente_telefono' => ['required', 'string', 'max:30'],
            'cliente_rut' => ['required', 'string', 'max:20', new RutChileno],
            'producto_id' => ['nullable', 'integer', Rule::exists('productos', 'id')],
            'tipo_equipo' => ['required', Rule::in(OrdenServicio::TIPOS)],
            'numero_serie' => [Rule::requiredIf($serieObligatoria), 'nullable', 'string', 'min:3', 'max:191'],
            // Condicion (garantia/reparacion): el cliente la indica; el mostrador
            // la verifica al confirmar (y pide el documento de garantia si aplica).
            'facturacion' => ['required', Rule::in(OrdenServicio::FACTURACION)],
            'garantia_doc_tipo' => [Rule::requiredIf($esGarantia), 'nullable', Rule::in(OrdenServicio::GARANTIA_DOC_TIPOS)],
            'garantia_doc_numero' => [Rule::requiredIf($esGarantia), 'nullable', 'string', 'max:191'],
            'garantia_doc_fecha' => [Rule::requiredIf($esGarantia), 'nullable', 'date', 'before_or_equal:today'],
            'falla_reportada' => ['required', 'string', 'min:3'],
        ]);

        $sucursal = Sucursal::findOrFail($data['sucursal_id']);
        $hoy = now()->toDateString();

        $orden = OrdenServicio::create([
            'cliente_nombre' => $data['cliente_nombre'],
            'cliente_email' => $data['cliente_email'],
            'cliente_telefono' => $data['cliente_telefono'] ?? null,
            'cliente_rut' => $data['cliente_rut'] ?? null,
            'producto_id' => $data['producto_id'] ?? null,
            'sucursal_id' => $sucursal->id,
            'tipo_equipo' => $data['tipo_equipo'],
            'numero_serie' => $data['numero_serie'] ?? null,
            'facturacion' => $data['facturacion'],
            'garantia_doc_tipo' => $data['garantia_doc_tipo'] ?? null,
            'garantia_doc_numero' => $data['garantia_doc_numero'] ?? null,
            'garantia_doc_fecha' => $data['garantia_doc_fecha'] ?? null,
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
     * Autocompletado PUBLICO del producto Dali (codigo) por SKU o nombre, para
     * que el cliente complete el codigo del equipo desde el catalogo. Mismo
     * contrato JSON que Admin\ServicioTecnicoController::buscarProducto, pero sin
     * auth (throttle propio en la ruta; solo lee SKU/nombre, minimo 2 caracteres,
     * limite 15).
     */
    public function buscarProducto(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $productos = Producto::query()
            ->where(fn (Builder $w) => $w
                ->where('sku', 'like', "%{$q}%")
                ->orWhere('nombre', 'like', "%{$q}%"))
            ->orderBy('sku')
            ->limit(15)
            ->get(['id', 'sku', 'nombre']);

        return response()->json($productos->map(fn (Producto $p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'nombre' => $p->nombre,
            'label' => $p->sku.' — '.$p->nombre,
        ]));
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
