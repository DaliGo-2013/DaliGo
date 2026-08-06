<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
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

    /**
     * Ejes que dibuja cada silueta. Es una CONSECUENCIA del dibujo, no un dato
     * del camión: no existe columna `ejes` y no vale inventarla para esto —
     * ninguna decisión del negocio depende de este número, solo el lienzo. Si
     * algún día hace falta el conteo exacto por camión, entra como columna
     * nullable editable y este mapa pasa a ser el respaldo.
     */
    private const EJES_POR_SILUETA = [
        'semirremolque' => 3,
        'camion_hino' => 2,
        'camion' => 2,
        'camion_liviano' => 2,
    ];

    public function __construct(private CalculoDeCarga $calculo) {}

    public function index(Request $request): View
    {
        $datos = $request->validate([
            'camion_id' => ['nullable', 'integer', 'exists:camiones_simulacion,id'],
            'tipo_bulto_id' => ['nullable', 'integer', 'exists:tipos_bulto,id'],
            // Carga mixta: líneas de (producto, cantidad EN UNIDADES). Tope de 8
            // líneas: una carga real de Dali son 3-4 tipos; más que eso es un
            // error de tipeo, no un pedido.
            'lineas' => ['nullable', 'array', 'max:8'],
            'lineas.*.tipo' => ['required_with:lineas', 'integer', 'exists:tipos_bulto,id'],
            'lineas.*.cantidad' => ['required_with:lineas', 'integer', 'min:1', 'max:100000'],
            // De pie (0) o acostado (1). Es una elección de ESTIBA, no un dato del
            // producto: el mismo pack viaja de las dos formas según el camión.
            'lineas.*.acostado' => ['nullable', 'boolean'],
            'acostado' => ['nullable', 'boolean'],
            // Quién decide qué producto va al fondo: el motor por volumen (auto) o el
            // orden en que el usuario armó la lista.
            'orden' => ['nullable', 'in:auto,lista'],
        ]);

        // Catálogo PROPIO del simulador (decisión del dueño 05-08): cajas de
        // carga TIPO sembradas por el deploy, NO los vehículos de la flota. La
        // versión enganchada a la flota dependía de cargar medidas a mano y
        // producción quedó mostrando «falta medir» para todo.
        $camiones = CamionSimulacion::where('activo', true)
            ->orderByRaw('(largo_cm * ancho_cm * alto_cm) desc')
            ->get();

        $bultos = TipoBulto::where('activo', true)->orderBy('categoria')->orderBy('nombre')->get();

        $camion = isset($datos['camion_id'])
            ? $camiones->firstWhere('id', (int) $datos['camion_id'])
            : $camiones->first();

        $bulto = isset($datos['tipo_bulto_id'])
            ? $bultos->firstWhere('id', (int) $datos['tipo_bulto_id'])
            : $bultos->first();

        // Modo: con líneas es CARGA MIXTA («¿cabe esta carga?»); sin líneas es el
        // cupo máximo de un solo producto («¿cuánto entra?»). La misma pantalla
        // responde las dos preguntas, que son distintas.
        $enOrdenDeLista = ($datos['orden'] ?? 'auto') === 'lista';

        $mixta = ($camion && isset($datos['lineas']) && $datos['lineas'] !== [])
            ? $this->calcularMixta($camion, $datos['lineas'], $bultos, $enOrdenDeLista)
            : null;

        $acostado = (bool) ($datos['acostado'] ?? false);

        $resultado = ($camion && $bulto && $mixta === null)
            ? $this->calculo->cupo($camion->paraCalculo(), $bulto->paraCalculo($acostado))
            : null;

        return view('admin.carga.index', [
            'camiones' => $camiones,
            'bultos' => $bultos,
            'camion' => $camion,
            'bulto' => $bulto,
            'resultado' => $resultado,
            'mixta' => $mixta,
            'acostado' => $acostado,
            'orden' => $enOrdenDeLista ? 'lista' : 'auto',
            // Las líneas que el usuario armó, para redibujar el formulario tal
            // cual tras el GET (y como semilla del Alpine).
            'lineasSel' => collect($datos['lineas'] ?? [])
                ->map(fn (array $l) => [
                    'tipo' => (int) $l['tipo'],
                    'cantidad' => (int) $l['cantidad'],
                    // 0/1 y no true/false: es el valor del <select> del formulario, y
                    // Alpine lo compara con las opciones tal cual viene.
                    'acostado' => (int) filter_var($l['acostado'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ])
                ->values(),
            // La escena 3D se arma en el cliente con estos números: no hay ningún
            // modelo 3D — la silueta y los bultos son prismas derivados de las
            // medidas. SIEMPRE viaja como lista de bloques: el cupo máximo es el
            // caso particular de un solo bloque.
            'escena' => $this->escena($camion, $bulto, $resultado, $mixta, $acostado),
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
    private function calcularMixta(CamionSimulacion $camion, array $lineasInput, $bultos, bool $enOrdenDeLista = false): array
    {
        $modelos = [];
        $acostadas = [];
        $lineas = [];
        foreach (array_values($lineasInput) as $i => $l) {
            $modelo = $bultos->firstWhere('id', (int) $l['tipo']);
            $modelos[$i] = $modelo;
            // La estiba se elige POR LÍNEA: en la misma carga puede ir un pack de
            // botellones acostado y otro de pie. El modelo ignora el pedido si el
            // bulto no admite elegir (ver TipoBulto::puedeAcostarse).
            $acostadas[$i] = filter_var($l['acostado'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && $modelo->puedeAcostarse();
            // El vendedor habla en UNIDADES (200 botellones); el motor en BULTOS
            // (bolsas de 5). Se redondea HACIA ARRIBA: 198 botellones son 40
            // bolsas — la bolsa viaja completa o no viaja.
            $lineas[$i] = [
                'bulto' => $modelo->paraCalculo($acostadas[$i]),
                'cantidad' => (int) ceil(((int) $l['cantidad']) / max(1, $modelo->unidades)),
            ];
        }

        $resultado = $this->calculo->carga($camion->paraCalculo(), $lineas, $enOrdenDeLista);

        $filas = [];
        foreach ($modelos as $i => $modelo) {
            $r = $resultado['lineas'][$i];
            $pedidasUnidades = (int) $lineasInput[array_keys($lineasInput)[$i]]['cantidad'];

            $filas[] = [
                'modelo' => $modelo,
                'acostado' => $acostadas[$i],
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
    private function escena(?CamionSimulacion $camion, ?TipoBulto $bulto, ?array $resultado, ?array $mixta, bool $acostado = false): ?array
    {
        if (! $camion || ($resultado === null && $mixta === null)) {
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
                    'letra' => self::letra($b['linea']),
                    'nombre' => $mixta['lineas'][$b['linea']]['modelo']->nombre,
                    'forma' => $mixta['lineas'][$b['linea']]['modelo']->formaVisor(),
                    // Con el pack acostado los botellones van tumbados, y el visor los
                    // tiene que dibujar así: si no, el dibujo diría «de pie» mientras el
                    // cálculo dice «acostado» — el lienzo dejaría de ser la prueba de lo
                    // que el motor hizo, que es todo lo que aporta.
                    'acostado' => $mixta['lineas'][$b['linea']]['acostado'],
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
                'letra' => self::letra(0),
                'nombre' => $bulto->nombre,
                'forma' => $bulto->formaVisor(),
                'acostado' => $acostado && $bulto->puedeAcostarse(),
            ]] : [];
        }

        return [
            // La clave sigue llamándose 'vehiculo' porque es el contrato con
            // carga3d.js (la silueta del camión); el dato viene del catálogo
            // propio del simulador.
            'vehiculo' => [
                'nombre' => $camion->nombre,
                'largo' => $camion->largo_cm / 100,
                'ancho' => $camion->ancho_cm / 100,
                'alto' => $camion->alto_cm / 100,
                'silueta' => $this->silueta($camion),
                'ejes' => self::EJES_POR_SILUETA[$this->silueta($camion)],
            ],
            'bloques' => $bloques,
            'tope' => array_sum(array_column($bloques, 'cantidad')),
            'libre_m' => self::pisoLibre($camion->largo_cm / 100, $bloques),
        ];
    }

    /**
     * La LETRA con que se identifica un producto: A, B, C… por orden de la lista
     * que armó el usuario.
     *
     * Es el vínculo entre la lista de abajo y el lienzo, y va escrita sobre las
     * cajas. Nace de mirar EasyCargo (05-08-2026): ahí cada renglón lleva su letra
     * y cada caja la trae impresa, y por eso se distingue de un vistazo qué es
     * qué. Acá había SOLO color, que es peor de dos maneras: un color no se puede
     * nombrar en voz alta («cargá el verde» con dos verdes al lado no sirve) y con
     * ocho productos los tonos se confunden.
     *
     * El índice es el de la línea, el MISMO con que se elige el color, así que la
     * letra del lienzo y la del renglón no pueden desalinearse. Más de 26
     * productos no existe: el formulario admite 8.
     */
    public static function letra(int $linea): string
    {
        return chr(65 + ($linea % 26));
    }

    /**
     * Metros de piso LIBRES desde la puerta, en metros.
     *
     * El «Free meters» de EasyCargo, y es más accionable que el porcentaje de
     * ocupación: «te queda 1,2 m contra la puerta» se entiende sin traducir, y
     * dice si vale la pena sumar algo más al viaje.
     *
     * Se mide hasta el bloque que llega más adelante, así que el espacio que
     * informa es un rectángulo de TODO el ancho y TODO el alto: no hay nada más
     * allá de esa línea. Conservador a propósito — puede quedar piso libre al
     * costado de un bloque corto y no se cuenta, en la misma dirección que el
     * resto del motor (nunca prometer de más).
     *
     * @param  list<array{x:float,orientacion:array{largo:float},rejilla:array{largo:int}}>  $bloques
     */
    private static function pisoLibre(float $largo, array $bloques): float
    {
        $fin = 0.0;
        foreach ($bloques as $b) {
            $fin = max($fin, $b['x'] + $b['rejilla']['largo'] * $b['orientacion']['largo']);
        }

        return round(max(0, $largo - $fin), 2);
    }

    /**
     * Con qué silueta se dibuja este camión. La DECLARADA manda; si falta se
     * deduce del largo, que es lo que mejor separa un acoplado de un camión de
     * reparto y de un liviano. Un valor desconocido (dato viejo o mal escrito)
     * también cae a la deducción en vez de dejar el lienzo vacío: el visor
     * tiene que dibujar algo siempre.
     */
    private function silueta(CamionSimulacion $camion): string
    {
        if ($camion->silueta && isset(self::EJES_POR_SILUETA[$camion->silueta])) {
            return $camion->silueta;
        }

        return match (true) {
            $camion->largo_cm >= 1000 => 'semirremolque',
            $camion->largo_cm >= 600 => 'camion',
            default => 'camion_liviano',
        };
    }
}
