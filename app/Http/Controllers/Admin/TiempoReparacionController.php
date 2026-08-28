<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\OrdenServicio;
use App\Models\TiempoReparacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Costos generales de reparación": catálogo de tiempos estándar por trabajo.
 * Jefatura fija las horas que lleva cada trabajo del taller; con eso la mano de
 * obra de las órdenes se calcula sola y el técnico no la puede modificar. No se
 * borra (un trabajo que ya no aplica se desactiva); el histórico lo conserva.
 */
class TiempoReparacionController extends Controller
{
    /**
     * Cuántas órdenes recientes se revisan para armar la lista de «escritos a mano». Ver
     * trabajosEscritosAMano(): es un techo declarado, no un número al azar.
     */
    private const ORDENES_REVISADAS = 500;

    public function index(): View
    {
        return view('admin.tiempos-reparacion.index', [
            // Agrupados por su grupo (Reparada / Sin solución…) para leerse como el catálogo.
            'porGrupo' => TiempoReparacion::orderBy('grupo')->orderBy('trabajo')->get()->groupBy('grupo'),
            'valorHora' => $this->valorHora(),
            'topeHoras' => TiempoReparacion::topeHoras(),
            // Lo que los técnicos escribieron a mano y no está en el catálogo. Es la cola de
            // trabajo de jefatura: cada línea que se repite es un candidato a entrar acá, así el
            // catálogo se calibra con el uso real en vez de adivinar combinaciones.
            'escritosAMano' => $this->trabajosEscritosAMano(),
            'ordenesRevisadas' => self::ORDENES_REVISADAS,
        ]);
    }

    /**
     * Los trabajos que los técnicos escribieron a mano, con cuántas veces apareció cada uno y
     * cuándo fue la última. Los más repetidos primero: son los que más urge catalogar.
     *
     * Se comparan NORMALIZADOS (minúscula, espacios colapsados) porque los escribe gente
     * distinta: «Cambio de estanque», «cambio de estanque» y «cambio  de estanque» son el mismo
     * trabajo y contarlos por separado escondería justamente al que más se repite. Se muestra la
     * forma más reciente, que es la que el técnico va a reconocer.
     *
     * @return \Illuminate\Support\Collection<int, array{texto: string, veces: int, ultima: ?string}>
     */
    private function trabajosEscritosAMano(): \Illuminate\Support\Collection
    {
        $enCatalogo = TiempoReparacion::pluck('trabajo')
            ->map(fn ($t) => $this->normaliza(TiempoReparacion::sinRemate($t)))
            ->filter()
            ->all();

        return OrdenServicio::query()
            ->whereNotNull('trabajos_extra')
            ->where('trabajos_extra', '!=', '')
            ->orderByDesc('id')
            // Techo explícito: esta lista responde «qué se está escribiendo seguido AHORA», no el
            // histórico completo, y sin límite crecería para siempre (la agrupación se hace en
            // PHP porque hay que partir el texto por líneas y normalizarlo, así que todo lo que
            // se traiga se carga en memoria). La pantalla DECLARA el recorte en vez de truncar en
            // silencio: un listado cortado sin avisar se lee como «esto fue todo lo que pasó».
            ->limit(self::ORDENES_REVISADAS)
            ->get(['id', 'trabajos_extra', 'updated_at'])
            ->flatMap(fn (OrdenServicio $o) => collect($o->trabajosExtraLista())
                ->map(fn ($linea) => ['texto' => $linea, 'fecha' => $o->updated_at]))
            // Ya catalogado = ya resuelto: no tiene por qué seguir apareciendo como pendiente.
            ->reject(fn ($x) => in_array($this->normaliza($x['texto']), $enCatalogo, true))
            ->groupBy(fn ($x) => $this->normaliza($x['texto']))
            ->map(fn ($grupo) => [
                'texto' => $grupo->first()['texto'],   // la forma más reciente (el orden viene por id desc)
                'veces' => $grupo->count(),
                'ultima' => $grupo->first()['fecha']?->enChile()->format('d-m-Y'),
            ])
            ->sortByDesc('veces')
            ->values();
    }

    private function normaliza(string $texto): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $texto)));
    }

    /** El tope de horas de mano de obra por orden. Lo fija jefatura acá mismo. */
    public function actualizarTope(Request $request): RedirectResponse
    {
        $horas = str_replace(',', '.', trim((string) $request->input('tope_horas')));
        $request->merge(['tope_horas' => $horas === '' ? null : $horas]);

        $data = $request->validate([
            // El rango no es decorativo: por debajo de 0,5 h ninguna reparación se pagaría, y
            // por encima de 24 el «tope» dejaría de topar nada (el trabajo más largo del
            // catálogo son 1,5 h). Se valida acá y `horasACobrar` no vuelve a mirarlo: su piso
            // ya garantiza que un tope chico no cobre menos que un trabajo individual.
            'tope_horas' => ['required', 'numeric', 'min:0.5', 'max:24'],
        ], [
            'tope_horas.required' => 'Indica el tope de horas.',
            'tope_horas.min' => 'El tope no puede ser menor a :min h.',
            'tope_horas.max' => 'El tope no puede pasar de :max h.',
        ]);

        // `Configuracion::set()` usa `firstOrFail()`: la clave tiene que EXISTIR. La siembra
        // ConfiguracionSeeder (que corre en cada deploy), pero se asegura acá para que la
        // pantalla no reviente en una base que todavía no la tiene — y con el mismo tipo y grupo
        // que declara el seeder, o el valor se serializaría distinto según quién la creó primero.
        Configuracion::firstOrCreate(
            ['clave' => TiempoReparacion::CLAVE_TOPE_HORAS],
            [
                'valor' => (string) TiempoReparacion::TOPE_HORAS_DEFAULT,
                'tipo' => Configuracion::TIPO_DECIMAL,
                'grupo' => 'servicio_tecnico',
                'descripcion' => 'Tope de horas de mano de obra por orden de servicio técnico.',
            ],
        );

        Configuracion::set(TiempoReparacion::CLAVE_TOPE_HORAS, (float) $data['tope_horas']);

        return redirect()->route('admin.tiempos-reparacion.index')
            ->with('status', 'Tope de mano de obra actualizado: '.TiempoReparacion::fmt((float) $data['tope_horas']).' h por orden.');
    }

    public function create(): View
    {
        return view('admin.tiempos-reparacion.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tiempo = TiempoReparacion::create($this->validateData($request));

        return redirect()->route('admin.tiempos-reparacion.index')
            ->with('status', "Trabajo «{$tiempo->trabajo}» agregado ({$tiempo->horas_fmt} h).");
    }

    public function edit(TiempoReparacion $tiempo): View
    {
        return view('admin.tiempos-reparacion.edit', ['tiempo' => $tiempo]);
    }

    public function update(Request $request, TiempoReparacion $tiempo): RedirectResponse
    {
        $tiempo->update($this->validateData($request, $tiempo));

        return redirect()->route('admin.tiempos-reparacion.index')
            ->with('status', "Trabajo «{$tiempo->trabajo}» actualizado ({$tiempo->horas_fmt} h).");
    }

    private function validateData(Request $request, ?TiempoReparacion $tiempo = null): array
    {
        // Coma decimal chilena → punto (ej. "1,5" h).
        $horas = str_replace(',', '.', trim((string) $request->input('horas')));
        $request->merge(['horas' => $horas === '' ? null : $horas]);

        $data = $request->validate([
            'trabajo' => ['required', 'string', 'max:191',
                Rule::unique('tiempos_reparacion', 'trabajo')->ignore($tiempo?->id)],
            'horas' => ['required', 'numeric', 'min:0', 'max:24'],
            'grupo' => ['nullable', 'string', 'max:191'],
        ]);

        $data['activo'] = $request->boolean('activo');

        return $data;
    }

    /**
     * Valor hora vigente (para mostrar la mano de obra que implica cada tiempo),
     * de la lista oficial de ventas. Ver Producto::precioVentaConIva.
     */
    private function valorHora(): ?int
    {
        $sku = config('servicio_tecnico.sku_hora_servicio');
        if (! $sku) {
            return null;
        }

        return \App\Models\Producto::where('sku', $sku)->with('precios.lista')->first()?->precioVentaConIva();
    }
}
