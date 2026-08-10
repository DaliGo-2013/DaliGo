<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Services\Carga\CalculoDeCarga;
use App\Services\Carga\PalletSimulado;
use App\Services\Carga\PlanDeCargaExcel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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
    /**
     * Peso de la madera de un pallet vacío, en kg. Un EPAL pesa 22-25 kg; se toma el
     * borde ALTO porque el peso recorta el cupo y conviene que recorte de más antes que
     * de menos.
     */
    private const PESO_MADERA_KG = 25.0;

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
            // El producto sale del catálogo… O es un BULTO A MEDIDA («cubicar»,
            // pedido del dueño 07-08 mirando EasyCargo): se escriben las medidas a
            // mano y entra en la carga como cualquier otro. Por eso `tipo` pasa a
            // ser nullable y lo exige una regla condicional: una línea es válida si
            // trae UNA de las dos cosas, nunca ninguna.
            'lineas.*.tipo' => ['nullable', 'integer', 'exists:tipos_bulto,id'],
            'lineas.*.cantidad' => ['required_with:lineas', 'integer', 'min:1', 'max:100000'],
            // --- Bulto a medida. DESCARTABLE a propósito (decisión del dueño
            // 07-08): vive solo en esta simulación y NO se guarda en el catálogo.
            // El catálogo es de donde salen los cupos que se le prometen a un
            // cliente, y dejar que cualquier prueba siembre medidas ahí es
            // exactamente lo que la regla «no se inventan números» evita.
            //
            // Los topes no son decorativos: en centímetros enteros (§2.5) y con un
            // máximo que ningún camión del catálogo supera, para que un cero de más
            // no genere un bulto de 30 m que el motor tenga que descartar igual.
            'lineas.*.medida_nombre' => ['nullable', 'string', 'max:60'],
            'lineas.*.medida_largo' => ['nullable', 'integer', 'min:1', 'max:1500'],
            'lineas.*.medida_ancho' => ['nullable', 'integer', 'min:1', 'max:300'],
            'lineas.*.medida_alto' => ['nullable', 'integer', 'min:1', 'max:300'],
            'lineas.*.medida_peso' => ['nullable', 'numeric', 'min:0', 'max:30000'],
            // Cómo viaja el pack: de pie, acostado de costado, o acostado con el pico a
            // la puerta (ver TipoBulto::ESTIBAS). Es una elección de ESTIBA y no un dato
            // del producto: el mismo pack viaja de las tres formas según el camión.
            'lineas.*.estiba' => ['nullable', 'in:'.implode(',', array_keys(TipoBulto::ESTIBAS_ELEGIBLES))],
            'estiba' => ['nullable', 'in:'.implode(',', array_keys(TipoBulto::ESTIBAS_ELEGIBLES))],
            // Quién decide qué producto va al fondo: el motor por volumen (auto) o el
            // orden en que el usuario armó la lista.
            'orden' => ['nullable', 'in:auto,lista'],
            // SOBRE PALLET: se arma un pallet con un producto y después se sube al
            // camión. Las medidas son editables porque el dueño lo pidió así («deja la
            // opción de modificar», «un botón para ajustar medidas»): las dos estándar
            // que dictó son el punto de partida, no una jaula.
            'sobre_pallet' => ['nullable', 'boolean'],
            'pallet_tipo' => ['nullable', 'in:'.implode(',', array_keys(PalletSimulado::TIPOS))],
            'pallet_largo' => ['nullable', 'integer', 'min:40', 'max:300'],
            'pallet_ancho' => ['nullable', 'integer', 'min:40', 'max:300'],
            'pallet_alto' => ['nullable', 'integer', 'min:'.PalletSimulado::ALTO_MIN, 'max:'.PalletSimulado::ALTO_MAX],
            'pallet_base' => ['nullable', 'integer', 'min:5', 'max:30'],
            // Tope de apilado para ESTA simulación (pisa el del catálogo). Es lo que
            // dejaba el hueco arriba de la carga.
            'apilado' => ['nullable', 'integer', 'min:1', 'max:30'],
            // Cantidad A PROBAR en «¿Cuánto entra?» (pedido del dueño 06-08: «me falta
            // la opción de cuánto cargo, 1, 20, 50, para realizar la prueba»). En
            // unidades sueltas, como todo el formulario. Vacío = el máximo, como antes.
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // APROVECHAR EL ESPACIO QUE SOBRA (pedido del dueño 10-08: «que se pueda
            // cargar el camión completo hasta la puerta y que se ocupe todo el
            // espacio posible»). Deja que el motor GIRE el bulto en las regiones que
            // sobraron, que es lo que se hace a mano: el grueso acostado y, en la
            // franja de la puerta, las bolsas paradas y cruzadas.
            //
            // Opt-in, como el tope de apilado: apagado no mueve ni un número de los
            // verificados, y el candado de consistencia entre pestañas sigue en pie.
            'aprovechar' => ['nullable', 'boolean'],
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

        // EN PALLET SOLO VAN CAJAS (dueño, 07-08-2026: «los botellones nunca van a ir
        // en pallet, solo cajas»). No es un límite del motor —palletiza cualquier
        // bulto que quepa— sino cómo se trabaja en bodega.
        //
        // El selector se arma con esta lista, y además hay que CORREGIR el producto
        // elegido: se llega a este modo desde los otros dos con un `tipo_bulto_id` ya
        // puesto, así que sin esto el pallet se calculaba con la bolsa de botellones
        // y devolvía «0 pallets» — que se lee como que la app falló, cuando en
        // realidad ese producto no va en pallet (mide 130 cm y el pallet 120).
        $paletizables = $bultos->where('categoria', 'cajas')->values();
        $sobrePallet = (bool) ($datos['sobre_pallet'] ?? false);
        if ($sobrePallet && ($bulto === null || $bulto->categoria !== 'cajas')) {
            $bulto = $paletizables->first();
        }

        // Modo: con líneas es CARGA MIXTA («¿cabe esta carga?»); sin líneas es el
        // cupo máximo de un solo producto («¿cuánto entra?»). La misma pantalla
        // responde las dos preguntas, que son distintas.
        $enOrdenDeLista = ($datos['orden'] ?? 'auto') === 'lista';
        $aprovechar = (bool) ($datos['aprovechar'] ?? false);

        $mixta = ($camion && isset($datos['lineas']) && $datos['lineas'] !== [])
            ? $this->calcularMixta($camion, $datos['lineas'], $bultos, $enOrdenDeLista, $aprovechar)
            : null;

        $estiba = $datos['estiba'] ?? 'auto';

        // SOBRE PALLET es un tercer modo: se arma un pallet y se sube al camión.
        $pallet = PalletSimulado::desdeFormulario(
            $datos['pallet_tipo'] ?? null,
            $datos['pallet_largo'] ?? null,
            $datos['pallet_ancho'] ?? null,
            $datos['pallet_alto'] ?? null,
            $datos['pallet_base'] ?? null,
        );

        $apilado = isset($datos['apilado']) ? (int) $datos['apilado'] : null;

        $enPallet = ($camion && $bulto && $mixta === null && $sobrePallet)
            ? $this->calcularEnPallet($camion, $bulto, $pallet, $estiba, $apilado)
            : null;

        $resultado = ($camion && $bulto && $mixta === null && $enPallet === null)
            ? $this->calculo->cupo($camion->paraCalculo(), $bulto->paraCalculo($estiba, $apilado))
            : null;

        // MULTI-CAMIÓN: la MISMA pregunta que se está haciendo, respondida para
        // todos los camiones a la vez. La pregunta real de Comercial no es «¿entra
        // en este?» sino «¿en cuál conviene mandarlo?», y hasta ahora había que
        // cambiar de camión y recalcular de a uno para saberlo.
        //
        // No hay motor nuevo: es el mismo cálculo verificado corrido N veces. Con
        // tres camiones y rejilla entera cuesta microsegundos, así que se hace
        // siempre y no detrás de un botón.
        $comparativa = $this->compararCamiones(
            $camiones, $camion, $bulto, $bultos, $datos, $estiba, $apilado, $pallet, $sobrePallet, $enOrdenDeLista,
        );

        // La PRUEBA: «¿me entran 50?» encima del cupo máximo. No toca el motor — capa
        // el resultado y el dibujo a lo pedido, y el veredicto sale de comparar.
        $cantidad = isset($datos['cantidad']) ? (int) $datos['cantidad'] : null;
        $prueba = null;
        if ($resultado !== null && $cantidad !== null) {
            $porBulto = max(1, $bulto->unidades);
            $prueba = [
                'pedidas' => $cantidad,
                'caben' => $cantidad <= $resultado['unidades'],
                'cargadas' => min($cantidad, $resultado['unidades']),
                // La bolsa viaja completa o no viaja: 23 unidades de a 5 son 5 bolsas.
                'bultos' => min((int) ceil($cantidad / $porBulto), $resultado['bultos']),
            ];
        }

        return view('admin.carga.index', [
            'camiones' => $camiones,
            'bultos' => $bultos,
            // Lo que se puede paletizar: solo cajas. Va aparte de `bultos` para que
            // los otros dos modos sigan ofreciendo el catálogo completo.
            'paletizables' => $paletizables,
            // «¿En cuál conviene?»: la misma pregunta resuelta para toda la flota.
            'comparativa' => $comparativa,
            'camion' => $camion,
            'bulto' => $bulto,
            'resultado' => $resultado,
            'mixta' => $mixta,
            'estiba' => $estiba,
            'orden' => $enOrdenDeLista ? 'lista' : 'auto',
            'aprovechar' => $aprovechar,
            'pallet' => $pallet,
            'enPallet' => $enPallet,
            'apilado' => $apilado,
            'prueba' => $prueba,
            'cantidad' => $cantidad,
            // Las líneas que el usuario armó, para redibujar el formulario tal
            // cual tras el GET (y como semilla del Alpine).
            'lineasSel' => collect($datos['lineas'] ?? [])
                ->map(fn (array $l) => [
                    // 0 = bulto a medida: el <select> del formulario usa ese valor
                    // para mostrar los campos de medidas en vez del catálogo.
                    'tipo' => (int) ($l['tipo'] ?? 0),
                    'cantidad' => (int) $l['cantidad'],
                    // 0/1 y no true/false: es el valor del <select> del formulario, y
                    // Alpine lo compara con las opciones tal cual viene.
                    'estiba' => isset(TipoBulto::ESTIBAS_ELEGIBLES[$l['estiba'] ?? '']) ? $l['estiba'] : 'auto',
                    // Las medidas escritas a mano vuelven al formulario tal cual, para
                    // que un recálculo no le borre al usuario lo que acaba de tipear.
                    'medida_nombre' => (string) ($l['medida_nombre'] ?? ''),
                    'medida_largo' => $l['medida_largo'] ?? '',
                    'medida_ancho' => $l['medida_ancho'] ?? '',
                    'medida_alto' => $l['medida_alto'] ?? '',
                    'medida_peso' => $l['medida_peso'] ?? '',
                ])
                ->values(),
            // La escena 3D se arma en el cliente con estos números: no hay ningún
            // modelo 3D — la silueta y los bultos son prismas derivados de las
            // medidas. SIEMPRE viaja como lista de bloques: el cupo máximo es el
            // caso particular de un solo bloque.
            'escena' => $this->escena($camion, $bulto, $resultado, $mixta, $estiba, $enPallet, $prueba['bultos'] ?? null),
        ]);
    }

    /**
     * Corre la carga mixta y arma el paquete para la vista: cada línea con su
     * modelo, lo pedido y lo cargado EN UNIDADES (el idioma del vendedor), y el
     * motivo de lo que quedó afuera.
     *
     * @param  array<int, array{tipo: int|string, cantidad: int|string}>  $lineasInput
     * @param  Collection<int, TipoBulto>  $bultos
     * @return array{resultado: array, lineas: list<array<string, mixed>>, cabeTodo: bool, peligrosas: list<TipoBulto>}
     */
    private function calcularMixta(CamionSimulacion $camion, array $lineasInput, $bultos, bool $enOrdenDeLista = false, bool $aprovechar = false): array
    {
        $modelos = [];
        $estibas = [];
        $lineas = [];
        foreach (array_values($lineasInput) as $i => $l) {
            $modelo = $this->modeloDeLinea($l, $bultos);
            if ($modelo === null) {
                continue;   // línea sin producto ni medidas: no es una línea
            }
            $modelos[$i] = $modelo;
            // La estiba se elige POR LÍNEA: en la misma carga puede ir un pack de
            // botellones acostado, otro de pie y una caja en automático. Un valor
            // inventado cae a `auto`, que es el comportamiento verificado.
            $estibas[$i] = isset(TipoBulto::ESTIBAS_ELEGIBLES[$l['estiba'] ?? '']) ? $l['estiba'] : 'auto';
            // El vendedor habla en UNIDADES (200 botellones); el motor en BULTOS
            // (bolsas de 5). Se redondea HACIA ARRIBA: 198 botellones son 40
            // bolsas — la bolsa viaja completa o no viaja.
            $lineas[$i] = [
                'bulto' => $modelo->paraCalculo($estibas[$i]),
                'cantidad' => (int) ceil(((int) $l['cantidad']) / max(1, $modelo->unidades)),
            ];
        }

        $resultado = $this->calculo->carga($camion->paraCalculo(), $lineas, $enOrdenDeLista, $aprovechar);

        $filas = [];
        foreach ($modelos as $i => $modelo) {
            $r = $resultado['lineas'][$i];
            $pedidasUnidades = (int) $lineasInput[array_keys($lineasInput)[$i]]['cantidad'];

            $filas[] = [
                'modelo' => $modelo,
                'estiba' => $estibas[$i],
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
     * El plan de carga como .xlsx (pedido del dueño 10-08-2026): que el resultado
     * deje de vivir solo en la pantalla y se pueda bajar para el andén, el
     * conductor o la cotización.
     *
     * SALE DEL MISMO CÁLCULO QUE LA PANTALLA, literalmente: se invoca `index()` y
     * se leen los datos que le pasó a la vista, sin renderizarla. Es la lección ya
     * documentada del Excel de la flota —«el listado y la descarga filtran por el
     * MISMO método»—, llevada al extremo que este caso permite: acá no hay «un
     * método compartido» que alguien pueda dejar de usar, hay UNA sola ruta de
     * cálculo. Si mañana cambia el motor, la planilla cambia con él o no cambia
     * ninguno de los dos.
     *
     * Se puede hacer porque `index()` es una CALCULADORA: valida, calcula y no
     * escribe nada (lo dice el comentario de su grupo de rutas). Invocarla no
     * tiene efectos.
     */
    public function excel(Request $request, PlanDeCargaExcel $excel): Response
    {
        return response($excel->generar($this->index($request)->getData()), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.PlanDeCargaExcel::nombreArchivo().'"',
            // Se arma con los números del momento: que no quede cacheado ni en el
            // navegador ni en un proxy.
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * La misma pregunta, respondida para TODOS los camiones.
     *
     * Cada modo compara lo suyo, porque son preguntas distintas y mezclarlas daría
     * una tabla que no significa nada:
     *   - cupo máximo  → cuántas unidades entran
     *   - carga mixta  → si cabe todo, y cuántas unidades entraron
     *   - sobre pallet → cuántas unidades en total, apilando pallets
     *
     * Devuelve `null` cuando no hay nada que comparar (sin producto, o una sola caja
     * de carga en el catálogo): una tabla de una fila no ayuda a elegir.
     *
     * @param  Collection<int, CamionSimulacion>  $camiones
     * @param  Collection<int, TipoBulto>  $bultos
     * @param  array<string, mixed>  $datos
     * @return ?list<array<string, mixed>>
     */
    private function compararCamiones(
        $camiones, ?CamionSimulacion $actual, ?TipoBulto $bulto, $bultos, array $datos,
        string $estiba, ?int $apilado, PalletSimulado $pallet, bool $sobrePallet, bool $enOrdenDeLista,
    ): ?array {
        $hayLineas = isset($datos['lineas']) && $datos['lineas'] !== [];
        if ($camiones->count() < 2 || (! $bulto && ! $hayLineas)) {
            return null;
        }

        $filas = [];
        foreach ($camiones as $c) {
            if ($hayLineas) {
                $m = $this->calcularMixta($c, $datos['lineas'], $bultos, $enOrdenDeLista, $aprovechar);
                $unidades = array_sum(array_column($m['lineas'], 'cargadas_unidades'));
                $cabe = $m['cabeTodo'];
            } elseif ($sobrePallet) {
                $p = $this->calcularEnPallet($c, $bulto, $pallet, $estiba, $apilado);
                $unidades = $p['unidadesTotales'];
                $cabe = $p['entraEnPallet'];
            } else {
                $unidades = $this->calculo->cupo($c->paraCalculo(), $bulto->paraCalculo($estiba, $apilado))['unidades'];
                $cabe = $unidades > 0;
            }

            $filas[] = [
                'camion' => $c,
                'unidades' => $unidades,
                'cabe' => $cabe,
                'actual' => $actual && $c->id === $actual->id,
            ];
        }

        // De mayor a menor: el que más lleva va primero, que es el orden en que se
        // toma la decisión. A igual número, el más chico primero — mandar el camión
        // grande a medio llenar es peor negocio aunque quepa lo mismo.
        usort($filas, fn (array $a, array $b) => $b['unidades'] <=> $a['unidades']
            ?: $a['camion']->largo_cm * $a['camion']->ancho_cm * $a['camion']->alto_cm
               <=> $b['camion']->largo_cm * $b['camion']->ancho_cm * $b['camion']->alto_cm);

        return $filas;
    }

    /**
     * El producto de una línea: del catálogo, o un BULTO A MEDIDA («cubicar»).
     *
     * El bulto a medida es un `TipoBulto` **sin guardar** — un modelo de Eloquent
     * recién construido funciona perfecto sin tocar la base. Esa es la idea que
     * evitó una segunda abstracción: como es la MISMA clase, todo lo que viene
     * después sigue andando sin enterarse (`paraCalculo()`, la forma del visor, el
     * color, la letra, la fila del detalle). Un «BultoAMedida» aparte habría
     * obligado a duplicar cada uno de esos caminos o a poner un `if` en todos.
     *
     * Nunca se llama a `save()`: es descartable por decisión del dueño (07-08) y
     * porque el catálogo alimenta los cupos que se le prometen a un cliente.
     * Candado: `test_el_bulto_a_medida_no_se_guarda_en_el_catalogo`.
     *
     * @param  array<string, mixed>  $l
     * @param  Collection<int, TipoBulto>  $bultos
     */
    private function modeloDeLinea(array $l, $bultos): ?TipoBulto
    {
        if (! empty($l['tipo'])) {
            return $bultos->firstWhere('id', (int) $l['tipo']);
        }

        // Las tres medidas son obligatorias juntas: con una sola no hay bulto que
        // calcular, y adivinar la que falta sería inventar un número.
        foreach (['medida_largo', 'medida_ancho', 'medida_alto'] as $campo) {
            if (empty($l[$campo])) {
                return null;
            }
        }

        return new TipoBulto([
            'nombre' => trim((string) ($l['medida_nombre'] ?? '')) ?: 'Bulto a medida',
            'categoria' => 'cajas',
            'largo_cm' => (int) $l['medida_largo'],
            'ancho_cm' => (int) $l['medida_ancho'],
            'alto_cm' => (int) $l['medida_alto'],
            'peso_kg' => (float) ($l['medida_peso'] ?? 0),
            // Se cubica el bulto tal cual se escribió: UNA unidad por bulto. Si
            // fueran packs, la cantidad ya se pide en unidades y el vendedor
            // escribe las que son.
            'unidades' => 1,
            // Sin dato de terreno sobre cuántos aguanta apilados, el tope lo pone
            // la altura del camión y no un número inventado. El control de apilado
            // de la simulación lo puede bajar.
            'apilable_max' => 30,
            'soporta_peso_encima' => true,
            // Rota libre: es una caja cualquiera, no un pack con orientación
            // dictada. Si el usuario quiere fijarla, el selector de estiba de la
            // línea ya lo hace (le saca la rotación al motor, §3.1).
            'orientacion_fija' => false,
            'peligrosa' => false,
        ]);
    }

    /**
     * SOBRE PALLET: se arma un pallet con un producto y se sube al camión.
     *
     * Son DOS llamadas al mismo `cupo()` verificado, sin una línea de cálculo nueva:
     *   1. cuántas unidades entran ENCIMA del pallet → el pallet como caja de carga;
     *   2. cuántos pallets entran en el CAMIÓN → el pallet armado como un bulto más.
     *
     * Por eso el resultado hereda todas las garantías del motor (rejilla exacta,
     * centímetros enteros, redondeo hacia abajo) en vez de estrenar una heurística.
     *
     * @return array{pallet: PalletSimulado, porPallet: array, enCamion: array, unidadesPorPallet: int, pesoArmadoKg: float, unidadesTotales: int, cabenPallets: int}
     */
    private function calcularEnPallet(CamionSimulacion $camion, TipoBulto $bulto, PalletSimulado $pallet, string $estiba, ?int $apilado = null): array
    {
        $porPallet = $this->calculo->cupo($pallet->comoCajaDeCarga(), $bulto->paraCalculo($estiba, $apilado));

        // Peso del pallet ARMADO: la madera más lo que se le puso encima. Entra al
        // cálculo del camión porque un camión se puede llenar por kilos antes que por
        // espacio (con botellones vacíos no pasa, con cajas de repuestos sí).
        $pesoArmado = self::PESO_MADERA_KG + $porPallet['peso_kg'];

        $enCamion = $this->calculo->cupo(
            $camion->paraCalculo(),
            $pallet->comoBulto($pesoArmado, max(1, $porPallet['unidades'])),
        );

        // Si NO entra ni un bulto encima, no hay pallet que subir: se reporta cero y no
        // «14 pallets». Pasa de verdad y no es un error del cálculo — la bolsa de
        // botellones mide 130 cm de largo y un pallet estándar tiene 120, así que
        // sobresale. Informar 14 pallets vacíos sería un número que no significa nada, y
        // el vendedor podría leerlo como que la carga entra.
        $entra = $porPallet['bultos'] > 0;

        return [
            'pallet' => $pallet,
            'porPallet' => $porPallet,
            'enCamion' => $enCamion,
            'entraEnPallet' => $entra,
            'unidadesPorPallet' => $porPallet['unidades'],
            'pesoArmadoKg' => round($pesoArmado, 1),
            'cabenPallets' => $entra ? $enCamion['bultos'] : 0,
            'unidadesTotales' => $entra ? $enCamion['bultos'] * $porPallet['unidades'] : 0,
        ];
    }

    /**
     * La escena del visor: vehículo + lista de bloques (posición, orientación,
     * rejilla, cantidad, color y nombre). En cupo máximo es UN bloque; en mixta,
     * los que el acomodo por zonas haya puesto — ordenados fondo → puerta para
     * que la animación cargue como se carga de verdad.
     */
    private function escena(?CamionSimulacion $camion, ?TipoBulto $bulto, ?array $resultado, ?array $mixta, string $estiba = 'auto', ?array $enPallet = null, ?int $topeBultos = null): ?array
    {
        if (! $camion || ($resultado === null && $mixta === null && $enPallet === null)) {
            return null;
        }

        $m = fn (int $cm) => $cm / 100;

        if ($enPallet !== null) {
            return $this->escenaEnPallet($camion, $bulto, $enPallet, $m);
        }

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
                    // El visor tiene que dibujar la MISMA estiba que se calculó: si no, el
                    // dibujo diría «de pie» mientras el cálculo dice «acostado» y el
                    // lienzo dejaría de ser la prueba de lo que el motor hizo, que es todo
                    // lo que aporta.
                    'estiba' => TipoBulto::estibaEfectiva($mixta['lineas'][$b['linea']]['estiba']),
                ])
                ->all();
        } else {
            // Con una CANTIDAD A PROBAR, el dibujo se capa a lo pedido: si el veredicto
            // dice «entran tus 50», el camión tiene que mostrar 50, no el máximo.
            $enEscena = min($resultado['bultos'], $topeBultos ?? PHP_INT_MAX);
            $bloques = $enEscena > 0 ? [[
                'x' => 0,
                'y' => 0,
                'orientacion' => array_map($m, $resultado['orientacion']),
                'rejilla' => $resultado['rejilla'],
                'cantidad' => $enEscena,
                'color' => self::COLORES_3D[0],
                'letra' => self::letra(0),
                'nombre' => $bulto->nombre,
                'forma' => $bulto->formaVisor(),
                'estiba' => TipoBulto::estibaEfectiva($estiba),
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
     * La escena del modo SOBRE PALLET: el camión, el pallet armado (que el visor dibuja
     * AL LADO, en el piso) y los pallets ya subidos.
     *
     * El pallet viaja con su `interior`: la rejilla del producto encima. Con eso el visor
     * dibuja el mismo pallet armado en las dos situaciones —al costado y arriba del
     * camión— sin recalcular nada.
     *
     * Si el motor lo giró 90° para que entrara, el interior viene **ya girado** desde acá:
     * el visor no tiene que deducirlo, y así el dibujo no puede contradecir al cálculo.
     */
    private function escenaEnPallet(CamionSimulacion $camion, TipoBulto $bulto, array $enPallet, callable $m): array
    {
        $p = $enPallet['pallet'];
        $enCamion = $enPallet['enCamion'];
        $porPallet = $enPallet['porPallet'];

        $girado = $enCamion['orientacion']['largo'] !== $p->largo_cm;
        $rejilla = $porPallet['rejilla'];
        $ori = $porPallet['orientacion'];
        if ($girado) {
            $rejilla = ['largo' => $rejilla['ancho'], 'ancho' => $rejilla['largo'], 'alto' => $rejilla['alto']];
            $ori = ['largo' => $ori['ancho'], 'ancho' => $ori['largo'], 'alto' => $ori['alto']];
        }

        $interior = [
            'rejilla' => $rejilla,
            'orientacion' => array_map($m, $ori),
            'cantidad' => $porPallet['bultos'],
            'forma' => $bulto->formaVisor(),
            'color' => self::COLORES_3D[0],
        ];

        $bloques = $enPallet['cabenPallets'] > 0 ? [[
            'x' => 0,
            'y' => 0,
            'orientacion' => array_map($m, $enCamion['orientacion']),
            'rejilla' => $enCamion['rejilla'],
            'cantidad' => $enPallet['cabenPallets'],
            'color' => self::COLORES_3D[0],
            'letra' => self::letra(0),
            'nombre' => $bulto->nombre,
            'forma' => 'pallet',
            'estiba' => 'pie',
            'base' => $m($p->base_cm),
            'interior' => $interior,
        ]] : [];

        return [
            'vehiculo' => [
                'nombre' => $camion->nombre,
                'largo' => $camion->largo_cm / 100,
                'ancho' => $camion->ancho_cm / 100,
                'alto' => $camion->alto_cm / 100,
                'silueta' => $this->silueta($camion),
                'ejes' => self::EJES_POR_SILUETA[$this->silueta($camion)],
            ],
            'bloques' => $bloques,
            'tope' => $enPallet['cabenPallets'],
            'libre_m' => self::pisoLibre($camion->largo_cm / 100, $bloques),
            // Lo que el visor dibuja EN EL PISO, al lado del camión, mientras el pallet
            // todavía no se subió (pedido del dueño 06-08).
            'pallet' => [
                'largo' => $m($p->largo_cm),
                'ancho' => $m($p->ancho_cm),
                'alto' => $m($p->alto_cm),
                'base' => $m($p->base_cm),
                'nombre' => $bulto->nombre,
                'unidades' => $enPallet['unidadesPorPallet'],
                // Sin girar: al lado del camión se muestra como se arma, a lo largo.
                'interior' => [
                    'rejilla' => $porPallet['rejilla'],
                    'orientacion' => array_map($m, $porPallet['orientacion']),
                    'cantidad' => $porPallet['bultos'],
                    'forma' => $bulto->formaVisor(),
                    'color' => self::COLORES_3D[0],
                ],
            ],
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
