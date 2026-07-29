<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DteEmitido;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Services\Dte\CandadoDeEmision;
use App\Services\Dte\EmisorDte;
use App\Services\Dte\EstadoSii;
use App\Services\Dte\FormaPago;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Módulo de Facturación (M05).
 *
 * Existe ANTES de poder emitir, y eso es deliberado: mientras no se emite, la
 * información útil no es la lista de documentos (está vacía) sino **qué falta para
 * poder emitir**. De ahí las dos pantallas: `index` (lo emitido + de dónde se puede
 * emitir) y `estado` (el checklist de lo que falta).
 *
 * Regla que ordena todo este módulo: **nada que aparezca en pantalla finge
 * funcionar.** Cada origen de documento o hace algo real o dice, en su tarjeta,
 * qué le falta. Un botón que parece emitir y no emite es peor que no tenerlo.
 */
class DteController extends Controller
{
    public function __construct(private EmisorDte $emisor) {}

    /**
     * Documentos emitidos + los orígenes desde los que se puede crear uno.
     */
    public function index(Request $request): View
    {
        return view('admin.dte.index', [
            'documentos' => $this->filtrados($request),
            'filtros' => $request->only(['tipo_dte', 'estado_sii']),
            'origenes' => $this->origenesDeDocumento(),
            'ordenesListas' => $this->ordenesListasParaFacturar(),
            'bloqueo' => CandadoDeEmision::motivoDelBloqueo(),
        ]);
    }

    /**
     * Estado de la conexión con el emisor: qué está listo y qué falta.
     *
     * Es la traducción a pantalla de lo que hoy vive en documentos y en la cabeza
     * de quien programó. Mientras no se emite, es la pantalla más útil del módulo.
     */
    public function estado(): View
    {
        return view('admin.dte.estado', [
            'emisor' => $this->emisor->nombre(),
            'ambiente' => CandadoDeEmision::ambiente(),
            'bloqueo' => CandadoDeEmision::motivoDelBloqueo(),
            'faltantes' => $this->faltantesDeConfiguracion(),
            'emitidos' => DteEmitido::count(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<DteEmitido>
     */
    private function filtrados(Request $request): LengthAwarePaginator
    {
        $f = $request->validate([
            'tipo_dte' => ['nullable', 'integer', Rule::in(array_keys(DteEmitido::TIPO_ETIQUETAS))],
            'estado_sii' => ['nullable', Rule::in(EstadoSii::TODOS)],
        ]);

        return DteEmitido::query()
            ->when($f['tipo_dte'] ?? null, fn (Builder $q, $v) => $q->where('tipo_dte', $v))
            ->when($f['estado_sii'] ?? null, fn (Builder $q, $v) => $q->where('estado_sii', $v))
            ->with(['ordenServicio:id,codigo', 'sucursal:id,nombre'])
            ->latest('emitido_at')->latest('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Los orígenes de documento, al estilo del menú «Nuevo» de Bsale — pero cada
     * uno con su estado REAL. El que no está disponible dice por qué.
     *
     * @return list<array{titulo:string,detalle:string,disponible:bool,motivo:?string,url:?string}>
     */
    private function origenesDeDocumento(): array
    {
        return [
            [
                'titulo' => 'Desde una orden de servicio',
                'detalle' => 'Boleta o factura de una reparación ya cotizada. Toma los repuestos y la mano de obra de la orden.',
                'disponible' => true,
                'motivo' => null,
                'url' => null, // se elige la orden en la lista de abajo
            ],
            [
                'titulo' => 'Boleta de mostrador',
                'detalle' => 'Venta rápida sin orden de servicio previa.',
                'disponible' => false,
                'motivo' => 'Necesita el punto de venta (módulo M06, hoy en Bsale y sin fecha).',
                'url' => null,
            ],
            [
                'titulo' => 'Guía de despacho',
                'detalle' => 'Traslado de mercadería, y la devolución de un equipo reparado en garantía.',
                'disponible' => false,
                'motivo' => 'En espera: desde el 1-nov-2026 el SII exige datos del transporte (chofer, patente, horarios) '
                    .'y la integración de Bsale todavía no tiene dónde registrarlos. Construirla ahora sería hacerla dos veces.',
                'url' => null,
            ],
            [
                'titulo' => 'Nota de crédito',
                'detalle' => 'Anula un documento ya emitido. Es el único modo: los documentos electrónicos no se borran.',
                'disponible' => false,
                'motivo' => 'Requiere un documento emitido para anular. Hoy no hay ninguno.',
                'url' => null,
            ],
        ];
    }

    /**
     * Órdenes que hoy se podrían facturar: cobrables (no en garantía vigente), con
     * monto y sin documento emitido todavía. Es la lista real de trabajo pendiente
     * de facturar y la entrada a la pantalla del documento.
     *
     * El monto y la condición se filtran EN PHP y no en SQL a propósito:
     * `costo_total` y `condicion_efectiva` son accessors que dependen de los
     * repuestos y de la vigencia de la garantía, y reproducirlos en SQL portable
     * (MySQL 5.7 + SQLite) significaría duplicar reglas de negocio en dos lugares
     * que después driftean. Se acota la consulta a las 60 más recientes sin
     * documento y de ahí se toman 10: es un atajo del panel, no un listado.
     *
     * @return \Illuminate\Support\Collection<int, OrdenServicio>
     */
    private function ordenesListasParaFacturar()
    {
        return OrdenServicio::query()
            ->whereDoesntHave('dtesEmitidos')
            ->with('repuestos')
            ->latest('fecha_ingreso')->latest('id')
            ->limit(60)
            ->get()
            ->filter(fn (OrdenServicio $orden) => $orden->condicion_efectiva === 'reparacion'
                && (int) $orden->costo_total > 0)
            ->take(10)
            ->values();
    }

    /**
     * Qué falta en `config/dte.php` para poder emitir. Cada faltante es una fila
     * del checklist de la pantalla de estado.
     *
     * @return list<array{titulo:string,detalle:string,listo:bool}>
     */
    private function faltantesDeConfiguracion(): array
    {
        $tipos = (array) config('dte.bsale.tipos_documento', []);
        $oficinas = (array) config('dte.bsale.oficinas', []);
        $medios = (array) config('dte.bsale.medios_pago', []);

        $sucursales = Sucursal::where('activa', true)->pluck('codigo')->filter()->all();
        $sucursalesSinOficina = array_values(array_diff($sucursales, array_keys($oficinas)));

        return [
            [
                'titulo' => 'Tipos de documento de Bsale',
                'detalle' => $tipos
                    ? 'Configurados: '.implode(', ', array_map(
                        fn ($codigo) => DteEmitido::TIPO_ETIQUETAS[$codigo] ?? $codigo,
                        array_keys($tipos)
                    ))
                    : 'Sin configurar. Se usa el código del SII, que es ambiguo si la empresa tiene más de una serie del mismo documento.',
                'listo' => $tipos !== [],
            ],
            [
                'titulo' => 'Oficinas por sucursal',
                'detalle' => $sucursalesSinOficina === []
                    ? 'Todas las sucursales activas tienen su oficina de Bsale.'
                    : 'Faltan: '.implode(', ', $sucursalesSinOficina).'. Sin esto no se puede emitir desde esas sucursales.',
                'listo' => $sucursales !== [] && $sucursalesSinOficina === [],
            ],
            [
                'titulo' => 'Medios de pago',
                'detalle' => $medios
                    ? 'Configurados: '.implode(', ', array_map(
                        fn ($forma) => FormaPago::etiqueta($forma),
                        array_keys($medios)
                    ))
                    : 'Sin configurar. Contabilidad definió que el pago se registra al emitir, así que sin esto no se emite.',
                'listo' => $medios !== [],
            ],
            [
                'titulo' => 'Autorización para emitir',
                'detalle' => CandadoDeEmision::permitido()
                    ? 'La emisión está habilitada. Los documentos que se creen son REALES.'
                    : 'Deshabilitada, que es el estado correcto hasta que Gerencia autorice la primera emisión por escrito.',
                'listo' => CandadoDeEmision::permitido(),
            ],
        ];
    }

}
