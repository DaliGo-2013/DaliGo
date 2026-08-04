<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoBulto;
use App\Models\Vehiculo;
use App\Services\Carga\CalculoDeCarga;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Simulador de carga (módulo LOGÍSTICA, pedido del dueño 04-08-2026).
 *
 * PARA QUÉ EXISTE. Un vendedor con un cliente lejos pregunta «¿alcanza tanta
 * cantidad en tal camión?» y hoy se responde de memoria. La flota abarca de 18,9
 * a 67,7 m³ —3,6 veces— así que adivinar sale mal en las dos direcciones: se
 * manda un camión a medio llenar, o se promete carga que queda en el andén.
 *
 * NO ES OPERATIVO. No escribe despachos, no toca facturación, no registra nada:
 * es una calculadora. Por eso es el módulo más seguro de la app — si se apaga, no
 * se pierde ni un dato.
 *
 * El cálculo vive en App\Services\Carga\CalculoDeCarga (rejilla por división
 * entera, no división de volúmenes) y este controlador solo arma la pantalla.
 */
class SimuladorCargaController extends Controller
{
    /**
     * Colores de los bultos en el LIENZO 3D (RGB). Dentro del canvas el color es
     * DATO, no decoración: distingue un tipo de bulto de otro y la leyenda lo
     * traduce. Es la misma excepción sancionada que la D-013 de los squircles
     * del Inicio — la paleta de 4 sigue mandando en todo lo que es UI.
     *
     * Pública porque la leyenda de la vista pinta el punto de color de cada
     * producto con el MISMO color del lienzo: si divergieran, la leyenda mentiría.
     */
    public const COLORES_3D = [
        [234, 88, 12],    // naranjo de marca
        [59, 130, 246],   // azul
        [34, 197, 94],    // verde
        [139, 92, 246],   // violeta
        [245, 158, 11],   // ámbar
        [20, 184, 166],   // turquesa
        [236, 72, 153],   // rosa
        [107, 114, 128],  // gris
    ];

    public function __construct(private CalculoDeCarga $calculo) {}

    public function index(Request $request): View
    {
        $datos = $request->validate([
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'tipo_bulto_id' => ['nullable', 'integer', 'exists:tipos_bulto,id'],
            // Carga mixta: líneas de (producto, cantidad EN UNIDADES). Tope de 8
            // líneas: una carga real de Dali son 3-4 tipos; más que eso es un
            // error de tipeo, no un pedido.
            'lineas' => ['nullable', 'array', 'max:8'],
            'lineas.*.tipo' => ['required_with:lineas', 'integer', 'exists:tipos_bulto,id'],
            'lineas.*.cantidad' => ['required_with:lineas', 'integer', 'min:1', 'max:100000'],
        ]);

        // Solo los vehículos con las tres medidas útiles cargadas: sin ellas no
        // hay nada que calcular, y mostrarlos en el selector para que después
        // salga un cero sería prometer una función que no está.
        $vehiculos = Vehiculo::query()
            ->whereNotNull('largo_util_cm')->whereNotNull('ancho_util_cm')->whereNotNull('alto_util_cm')
            ->orderByRaw('(largo_util_cm * ancho_util_cm * alto_util_cm) desc')
            ->get();

        $bultos = TipoBulto::where('activo', true)->orderBy('categoria')->orderBy('nombre')->get();

        $sinMedidas = Vehiculo::whereNull('largo_util_cm')->count();

        $vehiculo = isset($datos['vehiculo_id'])
            ? $vehiculos->firstWhere('id', (int) $datos['vehiculo_id'])
            : $vehiculos->first();

        $bulto = isset($datos['tipo_bulto_id'])
            ? $bultos->firstWhere('id', (int) $datos['tipo_bulto_id'])
            : $bultos->first();

        // Modo: con líneas es CARGA MIXTA («¿cabe esta carga?»); sin líneas es el
        // cupo máximo de un solo producto («¿cuánto entra?»). La misma pantalla
        // responde las dos preguntas, que son distintas.
        $mixta = ($vehiculo && isset($datos['lineas']) && $datos['lineas'] !== [])
            ? $this->calcularMixta($vehiculo, $datos['lineas'], $bultos)
            : null;

        $resultado = ($vehiculo && $bulto && $mixta === null)
            ? $this->calculo->cupo($this->vehiculoParaCalculo($vehiculo), $bulto->paraCalculo())
            : null;

        return view('admin.carga.index', [
            'vehiculos' => $vehiculos,
            'bultos' => $bultos,
            'vehiculo' => $vehiculo,
            'bulto' => $bulto,
            'resultado' => $resultado,
            'mixta' => $mixta,
            'sinMedidas' => $sinMedidas,
            // Las líneas que el usuario armó, para redibujar el formulario tal
            // cual tras el GET (y como semilla del Alpine).
            'lineasSel' => collect($datos['lineas'] ?? [])
                ->map(fn (array $l) => ['tipo' => (int) $l['tipo'], 'cantidad' => (int) $l['cantidad']])
                ->values(),
            // La escena 3D se arma en el cliente con estos números: no hay ningún
            // modelo 3D — la silueta y los bultos son prismas derivados de las
            // medidas. SIEMPRE viaja como lista de bloques: el cupo máximo es el
            // caso particular de un solo bloque.
            'escena' => $this->escena($vehiculo, $bulto, $resultado, $mixta),
        ]);
    }

    /**
     * Corre la carga mixta y arma el paquete para la vista: cada línea con su
     * modelo, lo pedido y lo cargado EN UNIDADES (el idioma del vendedor), y el
     * motivo de lo que quedó afuera.
     *
     * @param  array<int, array{tipo: int|string, cantidad: int|string}>  $lineasInput
     * @param  \Illuminate\Support\Collection<int, TipoBulto>  $bultos
     * @return array{resultado: array, lineas: list<array<string, mixed>>, cabeTodo: bool, peligrosas: list<TipoBulto>}
     */
    private function calcularMixta(Vehiculo $vehiculo, array $lineasInput, $bultos): array
    {
        $modelos = [];
        $lineas = [];
        foreach (array_values($lineasInput) as $i => $l) {
            $modelo = $bultos->firstWhere('id', (int) $l['tipo']);
            $modelos[$i] = $modelo;
            // El vendedor habla en UNIDADES (200 botellones); el motor en BULTOS
            // (bolsas de 5). Se redondea HACIA ARRIBA: 198 botellones son 40
            // bolsas — la bolsa viaja completa o no viaja.
            $lineas[$i] = [
                'bulto' => $modelo->paraCalculo(),
                'cantidad' => (int) ceil(((int) $l['cantidad']) / max(1, $modelo->unidades)),
            ];
        }

        $resultado = $this->calculo->carga($this->vehiculoParaCalculo($vehiculo), $lineas);

        $filas = [];
        foreach ($modelos as $i => $modelo) {
            $r = $resultado['lineas'][$i];
            $pedidasUnidades = (int) $lineasInput[array_keys($lineasInput)[$i]]['cantidad'];

            $filas[] = [
                'modelo' => $modelo,
                'pedidas_unidades' => $pedidasUnidades,
                // Cargadas se reporta capado a lo pedido: si pidió 198 y la
                // última bolsa completa las 200, decir «cargadas 200» confunde.
                'cargadas_unidades' => min($r['unidades_colocadas'], $pedidasUnidades),
                'bultos_colocados' => $r['colocados'],
                'bultos_pedidos' => $r['pedidos'],
                'motivo' => $r['motivo'],
            ];
        }

        return [
            'resultado' => $resultado,
            'lineas' => $filas,
            'cabeTodo' => $resultado['cabe_todo'],
            'peligrosas' => array_values(array_filter($modelos, fn (TipoBulto $m) => $m->peligrosa)),
        ];
    }

    /**
     * La escena del visor: vehículo + lista de bloques (posición, orientación,
     * rejilla, cantidad, color y nombre). En cupo máximo es UN bloque; en mixta,
     * los que el acomodo por zonas haya puesto — ordenados fondo → puerta para
     * que la animación cargue como se carga de verdad.
     */
    private function escena(?Vehiculo $vehiculo, ?TipoBulto $bulto, ?array $resultado, ?array $mixta): ?array
    {
        if (! $vehiculo || ($resultado === null && $mixta === null)) {
            return null;
        }

        $m = fn (int $cm) => $cm / 100;

        if ($mixta !== null) {
            $bloques = collect($mixta['resultado']['bloques'])
                ->sortBy([['x', 'asc'], ['y', 'asc']])
                ->values()
                ->map(fn (array $b) => [
                    'x' => $m($b['x']),
                    'y' => $m($b['y']),
                    'orientacion' => array_map($m, $b['orientacion']),
                    'rejilla' => $b['rejilla'],
                    'cantidad' => $b['cantidad'],
                    'color' => self::COLORES_3D[$b['linea'] % count(self::COLORES_3D)],
                    'nombre' => $mixta['lineas'][$b['linea']]['modelo']->nombre,
                ])
                ->all();
        } else {
            $bloques = $resultado['bultos'] > 0 ? [[
                'x' => 0,
                'y' => 0,
                'orientacion' => array_map($m, $resultado['orientacion']),
                'rejilla' => $resultado['rejilla'],
                'cantidad' => $resultado['bultos'],
                'color' => self::COLORES_3D[0],
                'nombre' => $bulto->nombre,
            ]] : [];
        }

        return [
            'vehiculo' => [
                'nombre' => $vehiculo->alias ?: trim($vehiculo->marca.' '.$vehiculo->modelo) ?: $vehiculo->ppu,
                'largo' => $vehiculo->largo_util_cm / 100,
                'ancho' => $vehiculo->ancho_util_cm / 100,
                'alto' => $vehiculo->alto_util_cm / 100,
                'ejes' => 2,
            ],
            'bloques' => $bloques,
            'tope' => array_sum(array_column($bloques, 'cantidad')),
        ];
    }

    /** @return array{largo:int,ancho:int,alto:int,peso_max_kg:int|null,pasillo:int} */
    private function vehiculoParaCalculo(Vehiculo $v): array
    {
        return [
            'largo' => (int) $v->largo_util_cm,
            'ancho' => (int) $v->ancho_util_cm,
            'alto' => (int) $v->alto_util_cm,
            'peso_max_kg' => $v->capacidad_carga_kg ? (int) $v->capacidad_carga_kg : null,
            'pasillo' => (int) ($v->pasillo_cm ?? 0),
        ];
    }
}
