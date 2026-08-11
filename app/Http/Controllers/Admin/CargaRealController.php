<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CamionSimulacion;
use App\Models\CargaReal;
use App\Models\TipoBulto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * CARGAS REALES: lo que entró de verdad, al lado de lo que el simulador había dicho.
 *
 * PARA QUÉ EXISTE. El motor calcula un TECHO por rejilla exacta y lo dice en cada
 * pantalla: la estiba real no es una rejilla perfecta. `CalculoDeCarga::conFactor()` está
 * escrito desde el primer día para castigar ese techo, y no se usaba porque el factor **se
 * calibra contando una carga real** y no había dónde anotarla.
 *
 * Lo que cuesta no tenerla quedó demostrado el 11-08-2026: el dueño reportó 480 botellones
 * acostados en el HD35, el cálculo daba 360, y para hacer cerrar ese número se dedujo un
 * ancho de 204 cm que sobrevivió cuatro días hasta que la huincha dio 200. Con esta
 * pantalla, ese 480 habría sido UNA FILA con su fecha y su estiba —comparable, discutible,
 * y visiblemente sola frente a los otros tres cupos que sí cerraban— en vez de una
 * corrección de medidas.
 *
 * NO TOCA EL MOTOR. Acá solo se registra y se compara; el cálculo sigue devolviendo su
 * techo. Aplicar el factor al número que se le muestra al cliente es un paso aparte y
 * deliberado: primero hay que juntar cargas suficientes para que el promedio signifique
 * algo, y eso lo decide el dueño mirando esta tabla.
 */
class CargaRealController extends Controller
{
    /** Debajo de esto, un promedio es una anécdota y no un factor. */
    public const MINIMO_PARA_PROMEDIAR = 3;

    public function index(): View
    {
        $cargas = CargaReal::with(['camion', 'bulto', 'usuario'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('admin.cargas-reales.index', [
            'cargas' => $cargas,
            'resumen' => $this->resumen($cargas),
            'camiones' => CamionSimulacion::where('activo', true)->orderBy('nombre')->get(),
            'bultos' => TipoBulto::where('activo', true)->orderBy('categoria')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'camion_simulacion_id' => ['required', 'integer', 'exists:camiones_simulacion,id'],
            'tipo_bulto_id' => ['required', 'integer', 'exists:tipos_bulto,id'],
            'estiba' => ['required', 'in:'.implode(',', array_keys(TipoBulto::ESTIBAS_ELEGIBLES))],
            // Los dos en UNIDADES sueltas, el idioma del vendedor. Sin tope superior
            // artificial más allá de lo razonable: un contenedor lleva 1.620 botellones y
            // nadie va a anotar 100.000, pero tampoco hay motivo para rechazarlo.
            'simulado' => ['required', 'integer', 'min:1', 'max:100000'],
            'real' => ['required', 'integer', 'min:0', 'max:100000'],
            'observaciones' => ['nullable', 'string', 'max:300'],
        ], [], [
            'simulado' => 'lo que dijo el simulador',
            'real' => 'lo que entró',
        ]);

        CargaReal::create($datos + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.cargas-reales.index')
            ->with('status', 'Carga registrada. El factor se recalcula solo.');
    }

    public function destroy(CargaReal $cargasReale): RedirectResponse
    {
        $cargasReale->delete();

        return back()->with('status', 'Carga borrada del historial.');
    }

    /**
     * El promedio por COMBINACIÓN (camión + producto + estiba), que es la única unidad en
     * la que un factor significa algo: la misma bolsa da 420 de pie y 360 acostada en el
     * mismo camión, así que promediar todo junto mezclaría peras con manzanas.
     *
     * Se calcula sobre las filas que ya están cargadas en memoria en vez de con un GROUP
     * BY: son como mucho 100 y así el número de la tabla y el del resumen no pueden
     * discrepar por un filtro que se olvidó de replicar.
     *
     * @param  Collection<int, CargaReal>  $cargas
     * @return list<array<string, mixed>>
     */
    private function resumen(Collection $cargas): array
    {
        return $cargas
            ->filter(fn (CargaReal $c) => $c->factor() !== null)
            ->groupBy(fn (CargaReal $c) => $c->camion_simulacion_id.'|'.$c->tipo_bulto_id.'|'.$c->estiba)
            ->map(function (Collection $grupo) {
                $primera = $grupo->first();
                $factor = round($grupo->avg(fn (CargaReal $c) => $c->factor()), 4);

                return [
                    'camion' => $primera->camion?->nombre ?? '—',
                    'bulto' => $primera->bulto?->nombre ?? '—',
                    'estiba' => $primera->estiba,
                    'veces' => $grupo->count(),
                    'factor' => $factor,
                    // Con una o dos cargas el promedio es una anécdota. Se muestra igual
                    // —esconderlo sería peor— pero la pantalla lo dice.
                    'confiable' => $grupo->count() >= self::MINIMO_PARA_PROMEDIAR,
                    'ultima' => $grupo->max('fecha'),
                ];
            })
            ->sortByDesc('veces')
            ->values()
            ->all();
    }
}
