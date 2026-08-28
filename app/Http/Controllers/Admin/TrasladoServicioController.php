<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\TrasladoServicio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Traslado de maquinas a reparar: sucursal -> casa matriz.
 *
 * Cierra el tramo que no tenia dueño (pedido del dueño 03-08-2026). Dos puntas
 * separadas a proposito:
 *  - DESPACHAR ('despachar traslado servicio'): jefe de sucursal / administrativo.
 *  - RECIBIR ('recibir traslado servicio'): tecnico, jefe de bodega, jefe de ventas.
 * Que sean dos permisos es lo que hace que la cadena de custodia signifique algo.
 */
class TrasladoServicioController extends Controller
{
    // Quién recibe cada aviso de traslado vive en AudienciasNotificacion
    // (editable por el dueño en Configuración → Avisos).

    public function index(Request $request): View
    {
        $traslados = TrasladoServicio::query()
            ->with(['origen', 'destino', 'ordenes'])
            ->withCount('ordenes')
            ->latest('despachado_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.traslados.index', [
            'traslados' => $traslados,
            // Bloque destacado arriba: lo que esta en camino y espera confirmacion.
            'enTransito' => TrasladoServicio::enTransito()
                ->with(['origen', 'destino'])
                ->withCount('ordenes')
                ->latest('despachado_at')
                ->get(),
            // Maquinas paradas en sucursal SIN despachar: el otro agujero que este
            // modulo hace visible (antes no se veian en ninguna pantalla).
            'sinDespachar' => $this->ordenesSinDespachar(),
        ]);
    }

    /** Formulario de despacho: las maquinas de sucursal que todavia no viajaron. */
    public function create(Request $request): View
    {
        return view('admin.traslados.create', [
            'ordenes' => $this->ordenesSinDespachar(),
            'central' => Sucursal::firstWhere('es_central', true),
            'conductores' => Conductor::activos()->pluck('nombre'),
            // Sugerencia de emisor: el nombre de quien esta logueado. Editable
            // porque hoy NO hay cuentas en Abate ni Coquimbo (dato del dueño) y
            // el despacho lo puede estar cargando otra persona.
            'emisorSugerido' => $request->user()->name,
        ]);
    }

    /**
     * Despacha el traslado: congela el conteo, marca las ordenes como en viaje y
     * avisa al taller. Todo en transaccion — un traslado a medio crear dejaria
     * maquinas sin sucursal responsable, que es justo el problema que resuelve.
     */
    public function store(Request $request): RedirectResponse
    {
        $central = Sucursal::firstWhere('es_central', true);
        abort_if($central === null, 422, 'No hay una sucursal marcada como casa matriz: sin destino no se puede despachar.');

        $data = $request->validate([
            'ordenes' => ['required', 'array', 'min:1'],
            'ordenes.*' => ['integer'],
            'emisor_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'conductor' => ['nullable', 'string', 'max:191'],
            'observaciones_envio' => ['nullable', 'string', 'max:2000'],
        ], [
            'ordenes.required' => 'Elige al menos una máquina para despachar.',
        ]);

        // Se revalidan contra el servidor: solo ordenes que de verdad estan en una
        // sucursal que no repara y sin traslado. Confiar en los ids del formulario
        // permitiria despachar una maquina ya despachada (o de otra sucursal).
        $ordenes = $this->ordenesSinDespachar()->whereIn('id', $data['ordenes']);

        if ($ordenes->isEmpty()) {
            return back()->withInput()
                ->with('status', 'Ninguna de las máquinas elegidas está pendiente de despacho (¿ya se despachó?).');
        }

        // Un traslado = un origen. Si se eligieron maquinas de dos sucursales, se
        // arma un traslado por sucursal: el responsable de la entrega es de UNA.
        $traslados = [];
        DB::transaction(function () use ($ordenes, $data, $central, $request, &$traslados) {
            foreach ($ordenes->groupBy('sucursal_id') as $sucursalId => $delOrigen) {
                $traslado = TrasladoServicio::create([
                    'sucursal_origen_id' => $sucursalId,
                    'sucursal_destino_id' => $central->id,
                    'estado' => TrasladoServicio::EN_TRANSITO,
                    'emisor_id' => $request->user()->id,
                    'emisor_nombre' => $data['emisor_nombre'],
                    'conductor' => $data['conductor'] ?? null,
                    'despachado_at' => now(),
                    'observaciones_envio' => $data['observaciones_envio'] ?? null,
                    'total_enviado' => $delOrigen->count(),
                ]);

                OrdenServicio::whereIn('id', $delOrigen->pluck('id'))
                    ->update(['traslado_id' => $traslado->id]);

                $traslados[] = $traslado;
            }
        });

        // Avisos: secundarios (try/catch) — un correo caido no puede deshacer un
        // despacho ya registrado.
        foreach ($traslados as $traslado) {
            try {
                $this->avisarDespacho($traslado->fresh(['origen', 'destino']));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $total = $ordenes->count();
        $cuantos = count($traslados);

        return redirect()->route('admin.traslados.index')->with('status', $cuantos === 1
            ? "Traslado {$traslados[0]->codigo} despachado con {$total} máquina(s). El taller ya recibió el aviso."
            : "Se despacharon {$cuantos} traslados ({$total} máquinas en total), uno por sucursal de origen.");
    }

    public function show(TrasladoServicio $traslado): View
    {
        return view('admin.traslados.show', [
            'traslado' => $traslado->load(['origen', 'destino', 'emisor', 'receptor', 'ordenes.producto']),
        ]);
    }

    /**
     * Confirma la recepcion. El receptor marca QUE maquinas llegaron de verdad:
     * si son menos que las despachadas, queda registrado como diferencia y sale
     * el aviso con los dos nombres. Eso es lo que reemplaza a la discusion.
     */
    public function recibir(Request $request, TrasladoServicio $traslado): RedirectResponse
    {
        if ($traslado->recibido) {
            return back()->with('status', "El traslado {$traslado->codigo} ya estaba recibido por {$traslado->receptor_nombre}.");
        }

        $data = $request->validate([
            // Sin 'required': recibir CERO maquinas es un resultado posible (y el
            // mas grave). Exigir al menos una obligaria a mentir para poder cerrar.
            'recibidas' => ['array'],
            'recibidas.*' => ['integer'],
            'receptor_nombre' => ['required', 'string', 'min:3', 'max:191'],
            'observaciones_recepcion' => ['nullable', 'string', 'max:2000'],
        ]);

        $idsDelTraslado = $traslado->ordenes->pluck('id');
        $llegaron = $idsDelTraslado->intersect($data['recibidas'] ?? []);

        DB::transaction(function () use ($traslado, $llegaron, $data, $request) {
            if ($llegaron->isNotEmpty()) {
                OrdenServicio::whereIn('id', $llegaron)->update(['traslado_recibida_at' => now()]);
            }

            $traslado->update([
                'estado' => TrasladoServicio::RECIBIDO,
                'receptor_id' => $request->user()->id,
                'receptor_nombre' => $data['receptor_nombre'],
                'recibido_at' => now(),
                'observaciones_recepcion' => $data['observaciones_recepcion'] ?? null,
                'total_recibido' => $llegaron->count(),
            ]);
        });

        $traslado->refresh()->load(['origen', 'destino', 'ordenes']);

        try {
            $traslado->tiene_diferencia
                ? $this->avisarDiferencias($traslado)
                : $this->avisarRecepcion($traslado);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('admin.traslados.show', $traslado)->with('status', $traslado->tiene_diferencia
            ? "Recepción registrada CON DIFERENCIAS: salieron {$traslado->total_enviado} y llegaron {$traslado->total_recibido}. Se avisó a jefatura y a {$traslado->origen->nombre}."
            : "Traslado {$traslado->codigo} recibido completo ({$traslado->total_recibido} máquinas). Ya se pueden reparar.");
    }

    // ------------------------------------------------------------------
    // Apoyo
    // ------------------------------------------------------------------

    /**
     * Maquinas paradas en una sucursal que NO repara y sin traslado: las que
     * tienen que viajar y todavia no salieron.
     *
     * @return \Illuminate\Support\Collection<int, OrdenServicio>
     */
    private function ordenesSinDespachar()
    {
        return OrdenServicio::query()
            ->whereNull('traslado_id')
            ->whereNull('traslado_recibida_at')
            ->whereNotNull('sucursal_id')
            ->whereHas('sucursal', fn ($q) => $q->where('es_central', false))
            // Las entregadas o cerradas no tienen para que viajar.
            ->whereNotIn('estado', ['entregado', 'sin_solucion'])
            ->with(['sucursal', 'producto'])
            ->orderBy('sucursal_id')
            ->orderBy('fecha_ingreso')
            ->get();
    }

    /** @return array<string, mixed> Placeholders comunes de las 3 plantillas. */
    private function datos(TrasladoServicio $traslado): array
    {
        return [
            'codigo' => $traslado->codigo,
            'origen' => $traslado->origen?->nombre ?? '—',
            'destino' => $traslado->destino?->nombre ?? '—',
            'emisor' => $traslado->emisor_nombre,
            'receptor' => $traslado->receptor_nombre ?: '—',
            'conductor' => $traslado->conductor ?: 'sin conductor registrado',
            'total' => $traslado->total_enviado,
            'recibidas' => $traslado->total_recibido ?? 0,
            'faltantes' => $traslado->faltantes,
            'observacion' => $traslado->observaciones_recepcion ?: 'sin observaciones',
            'url' => route('admin.traslados.show', $traslado),
        ];
    }

    private function despachar(string $evento, TrasladoServicio $traslado, array $extra = []): void
    {
        $dispatcher = app(\App\Services\Notificaciones\NotificacionDispatcher::class);
        $datos = array_merge($this->datos($traslado), $extra);

        \App\Support\AudienciasNotificacion::destinatarios($evento)
            ->each(fn (User $u) => $dispatcher->despachar($evento, $traslado, $u, $datos));
    }

    private function avisarDespacho(TrasladoServicio $traslado): void
    {
        $this->despachar('traslado.despachado', $traslado);
    }

    private function avisarRecepcion(TrasladoServicio $traslado): void
    {
        // De vuelta a quien despacho: cierra el circulo (salio, llego).
        $this->despachar('traslado.recibido', $traslado);
    }

    private function avisarDiferencias(TrasladoServicio $traslado): void
    {
        $detalle = $traslado->ordenesFaltantes()
            ->map(fn (OrdenServicio $o) => $o->folio.' ('.($o->cliente_nombre ?: 'sin cliente').')')
            ->implode(', ');

        $this->despachar('traslado.diferencias', $traslado, [
            'faltantes_detalle' => $detalle !== '' ? $detalle : '—',
        ]);
    }
}
