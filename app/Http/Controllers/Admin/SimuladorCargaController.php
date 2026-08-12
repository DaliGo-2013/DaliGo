<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CamionSimulacion;
use App\Models\CargaReal;
use App\Models\TipoBulto;
use App\Services\Carga\AcomodoManual;
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
        'camion_nqr' => 2,
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
            // Tope de apilado POR LÍNEA, igual que la estiba. Es lo que dejaba el hueco
            // arriba de los bidones: el catálogo dice 6 y una bolsa acostada mide 26 cm,
            // así que seis son 156 de los 266 del HINO — media caja de aire. Vacío = el
            // del catálogo, que es el comportamiento verificado.
            'lineas.*.apilado' => ['nullable', 'integer', 'min:1', 'max:30'],
            // UN PALLET ES UNA LÍNEA MÁS DE LA CARGA (pedido del dueño 10-08: «si cargo
            // botellones y tapas también tengo que poder cargar pallets, porque en la vida
            // real cargamos a veces pallets y de paso bidones o dispensadores»).
            //
            // Vacío = la línea va SUELTA, como siempre. Con un estándar elegido, `tipo`
            // pasa a ser lo que va ENCIMA del pallet y `cantidad` cuenta PALLETS.
            'lineas.*.pallet' => ['nullable', 'in:'.implode(',', array_keys(PalletSimulado::TIPOS))],
            'lineas.*.pallet_alto' => ['nullable', 'integer', 'min:'.PalletSimulado::ALTO_MIN, 'max:'.PalletSimulado::ALTO_MAX],
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
            // EL ACOMODO A MANO (pedido del dueño 11-08: «que te dé la opción de dar
            // vuelta la caja y acomodar como uno quiero»). Una posición por bloque,
            // `x,y` o `x,y,g` en centímetros, indexada por el ordinal del bloque; y
            // `acomodo_de`, para cuántos bloques se armó — si el resultado cambió de
            // tamaño, el acomodo se descarta entero en vez de aplicarse torcido.
            //
            // Viaja en la URL como todo lo demás: el link ES el escenario, así que un
            // plan acomodado a mano se comparte y se baja a Excel sin tabla nueva. Ver
            // `AcomodoManual` para lo que el acomodo puede y no puede cambiar.
            'acomodo' => ['nullable', 'array', 'max:200'],
            'acomodo.*' => ['string', 'regex:'.AcomodoManual::FORMATO],
            'acomodo_de' => ['nullable', 'integer', 'min:0', 'max:200'],
        ]);

        // EL ORDEN DE LAS LÍNEAS SE RESTAURA POR ÍNDICE.
        //
        // `validate()` no devuelve las líneas en el orden en que se enviaron: arma el
        // resultado recorriendo REGLA por regla, así que la primera línea que aparece es
        // la que tiene la primera clave con la que se topó. Una línea a la que le falta
        // una clave opcional queda DETRÁS de las completas — con dos líneas y la primera
        // sin `tipo`, salen en el orden 1, 0.
        //
        // No es cosmético: con «Como armé la lista» el primero de la lista es el que va
        // al FONDO del camión, y de esa misma posición salen la letra y el color con que
        // el producto aparece en el lienzo. Las claves son los índices del formulario, así
        // que ordenar por clave devuelve el orden que armó el usuario.
        if (isset($datos['lineas'])) {
            ksort($datos['lineas']);
        }

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
            $aprovechar,
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
            // LO QUE SE MIDIÓ EN TERRENO para esta misma combinación, si existe. Cierra
            // el lazo del lote 4: la pantalla que promete un techo muestra al lado lo que
            // realmente entró las veces que se contó.
            //
            // No CORRIGE el número: lo acompaña. Aplicar el factor al cupo es una decisión
            // del dueño y necesita antes suficientes cargas para que el promedio signifique
            // algo — mientras tanto, mostrar los dos es más honesto que reemplazar uno.
            'medido' => $this->medidoEnTerreno($camion, $bulto, $estiba),
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
                    // Vacío y no el número del catálogo: así «no lo toqué» se distingue
                    // de «pedí justo 6», y cambiar de producto no arrastra un tope que
                    // era del anterior.
                    'apilado' => $l['apilado'] ?? '',
                    // Vacío = la línea va suelta. Con un estándar, va sobre pallet.
                    'pallet' => isset(PalletSimulado::TIPOS[$l['pallet'] ?? '']) ? $l['pallet'] : '',
                    'pallet_alto' => $l['pallet_alto'] ?? '',
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
            'escena' => $this->escena(
                $camion, $bulto, $resultado, $mixta, $estiba, $enPallet, $prueba['bultos'] ?? null,
                new AcomodoManual(
                    $datos['acomodo'] ?? [],
                    isset($datos['acomodo_de']) ? (int) $datos['acomodo_de'] : null,
                ),
            ),
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
        $apilados = [];
        $palletsDeLinea = [];
        $lineas = [];
        foreach (array_values($lineasInput) as $i => $l) {
            // La estiba se elige POR LÍNEA: en la misma carga puede ir un pack de
            // botellones acostado, otro de pie y una caja en automático. Un valor
            // inventado cae a `auto`, que es el comportamiento verificado.
            $estiba = isset(TipoBulto::ESTIBAS_ELEGIBLES[$l['estiba'] ?? '']) ? $l['estiba'] : 'auto';
            // Y el tope de apilado TAMBIÉN por línea, por el mismo motivo: una bolsa
            // acostada y una caja de tapas no aguantan lo mismo encima. Vacío = el del
            // catálogo. En una línea EN PALLET se refiere a las cajas de arriba del
            // pallet, que es donde queda el hueco ahí.
            $apilado = ($l['apilado'] ?? null) !== null && $l['apilado'] !== ''
                ? max(1, (int) $l['apilado'])
                : null;

            // ¿La línea va SOBRE PALLET? Entonces el bulto que viaja al motor es el
            // pallet ARMADO y la cantidad cuenta pallets, no unidades sueltas.
            $pal = $this->palletDeLinea($l, $bultos, $estiba, $apilado);
            if ($pal !== null) {
                $modelos[$i] = $pal['modelo'];
                $estibas[$i] = $estiba;
                $apilados[$i] = $apilado;
                $palletsDeLinea[$i] = $pal;
                // Con CERO cajas encima no se sube ni un pallet: se le pasan 0 al motor
                // para que no aparezcan tarimas vacías en el camión, y la fila lo explica.
                $lineas[$i] = [
                    'bulto' => $pal['bulto'],
                    'cantidad' => $pal['porPallet']['bultos'] > 0 ? max(0, (int) $l['cantidad']) : 0,
                ];

                continue;
            }

            $modelo = $this->modeloDeLinea($l, $bultos);
            if ($modelo === null) {
                continue;   // línea sin producto ni medidas: no es una línea
            }
            $modelos[$i] = $modelo;
            $estibas[$i] = $estiba;
            $apilados[$i] = $apilado;
            // El vendedor habla en UNIDADES (200 botellones); el motor en BULTOS
            // (bolsas de 5). Se redondea HACIA ARRIBA: 198 botellones son 40
            // bolsas — la bolsa viaja completa o no viaja.
            $lineas[$i] = [
                'bulto' => $modelo->paraCalculo($estibas[$i], $apilados[$i]),
                'cantidad' => (int) ceil(((int) $l['cantidad']) / max(1, $modelo->unidades)),
            ];
        }

        $resultado = $this->calculo->carga($camion->paraCalculo(), $lineas, $enOrdenDeLista, $aprovechar);

        $filas = [];
        foreach ($modelos as $i => $modelo) {
            $r = $resultado['lineas'][$i];
            $pedidasUnidades = (int) $lineasInput[array_keys($lineasInput)[$i]]['cantidad'];

            // POR QUÉ QUEDÓ AIRE ARRIBA DE ESTE PRODUCTO (pedido del dueño 10-08:
            // «necesito que los bidones también lleguen hasta el techo»).
            //
            // El hueco no se explicaba solo: dos productos apilados los MISMOS 6 del
            // catálogo llegan a alturas distintas —seis cajas de 42 cm tocan el techo y
            // seis bolsas acostadas de 26 se quedan a media caja—, así que en pantalla
            // parecía un error del dibujo. Ahora la fila dice cuántas van de alto y
            // cuántas daría la altura, y el botón de al lado sube el tope de un toque.
            //
            // Los dos números salen del bloque que el motor YA colocó (su rejilla y la
            // orientación con que lo puso), no de un cálculo paralelo: si divergieran, la
            // pantalla estaría explicando una carga que no es la que dibujó.
            $bloque = null;
            foreach ($resultado['bloques'] as $b) {
                if ($b['linea'] === $i) {
                    $bloque = $b;
                    break;
                }
            }

            // SE INDEXA POR EL ÍNDICE DE LA LÍNEA, no por la posición en esta lista.
            //
            // Una línea sin producto ni medidas no llega hasta acá, así que las de abajo
            // corrían un lugar y esta lista dejaba de coincidir con los bloques, que
            // viajan con el índice ORIGINAL (`$b['linea']`). Las consecuencias no eran
            // cosméticas: `escena()` resuelve el nombre de cada bloque con
            // `$mixta['lineas'][$b['linea']]` y reventaba con «Undefined array key», y la
            // letra y el color —que salen de esta clave en la lista, en el panel de
            // cubicaje y en el Excel— señalaban al producto de al lado.
            //
            // Con la clave puesta, los cuatro lugares hablan del mismo producto por
            // construcción, y el botón «Apilar N» le escribe a la tarjeta correcta.
            $pal = $palletsDeLinea[$i] ?? null;

            // EN UNA LÍNEA EN PALLET el hueco que importa NO es el de arriba del pallet
            // —un pallet no se apila sobre otro (§3.3.4), así que ahí siempre va 1— sino
            // el que queda entre la última caja y el tope del pallet. Es el mismo control
            // «Apilar hasta» apuntando al lugar donde de verdad sirve, y por eso los dos
            // números salen del cupo INTERIOR y no del bloque del camión.
            $apiladas = $pal !== null
                ? ($pal['porPallet']['rejilla']['alto'] ?: null)
                : ($bloque['rejilla']['alto'] ?? null);
            $porAlto = $pal !== null
                ? ($pal['porPallet']['orientacion']['alto'] > 0
                    ? intdiv($pal['pallet']->altoUtilCm(), $pal['porPallet']['orientacion']['alto'])
                    : null)
                : ($bloque !== null && $bloque['orientacion']['alto'] > 0
                    ? intdiv($camion->alto_cm, $bloque['orientacion']['alto'])
                    : null);

            // Un pallet en el que no entra NI UNA caja no es un pallet: se reporta como
            // que no cabe y se dice por qué, en vez de subir tarimas vacías al camión
            // (§3.3.5 — pasa de verdad: la bolsa de botellones mide 130 y el pallet 120).
            $palletVacio = $pal !== null && $pal['porPallet']['bultos'] === 0;

            $filas[$i] = [
                'modelo' => $modelo,
                'estiba' => $estibas[$i],
                'apilado' => $apilados[$i],
                'apiladas' => $apiladas,
                'apilables_por_alto' => $porAlto,
                'pedidas_unidades' => $pedidasUnidades,
                // Cargadas se reporta capado a lo pedido: si pidió 198 y la
                // última bolsa completa las 200, decir «cargadas 200» confunde.
                'cargadas_unidades' => $palletVacio ? 0 : min($r['unidades_colocadas'], $pedidasUnidades),
                'bultos_colocados' => $palletVacio ? 0 : $r['colocados'],
                'bultos_pedidos' => $r['pedidos'],
                'motivo' => $palletVacio ? 'pallet_vacio' : $r['motivo'],
                // Lo que hace falta para contar la línea EN PALLET: qué lleva encima,
                // cuántas por pallet, y la rejilla con que el visor lo dibuja.
                'pallet' => $pal === null ? null : [
                    'nombre' => PalletSimulado::TIPOS[$pal['clave']]['nombre'],
                    'clave' => $pal['clave'],
                    'producto' => $pal['producto'],
                    'alto_cm' => $pal['pallet']->alto_cm,
                    'base_cm' => $pal['pallet']->base_cm,
                    'largo_cm' => $pal['pallet']->largo_cm,
                    'por_pallet' => $pal['porPallet']['unidades'],
                    'bultos_por_pallet' => $pal['porPallet']['bultos'],
                    'rejilla' => $pal['porPallet']['rejilla'],
                    'orientacion' => $pal['porPallet']['orientacion'],
                    'peso_armado_kg' => round(self::PESO_MADERA_KG + $pal['porPallet']['peso_kg'], 1),
                ],
            ];
        }

        // EL PESO, PARA PODER AVISAR (pedido del dueño 11-08: «que cuando se pase el
        // límite de carga aparezca un cartel de advertencia, aunque el camión no esté
        // lleno completamente»).
        //
        // El motor YA recorta por kilos —nunca devuelve una carga que se pase—, y por eso
        // hasta ahora el aviso no existía: mirando el resultado, el peso cargado siempre
        // entra. Lo que faltaba es el número con el que se avisa: **cuánto pesa lo
        // PEDIDO**, que es lo único que dice de cuánto te pasaste. Se calcula sobre las
        // cantidades pedidas, no sobre las colocadas.
        $topePeso = $camion->peso_max_kg;
        $pedidoKg = 0.0;
        foreach ($lineas as $l) {
            $pedidoKg += ((float) ($l['bulto']['peso'] ?? 0)) * $l['cantidad'];
        }

        return [
            'resultado' => $resultado,
            'lineas' => $filas,
            'peso' => [
                'tope_kg' => $topePeso,
                'cargado_kg' => $resultado['peso_kg'],
                'pedido_kg' => round($pedidoKg, 1),
                // Se pasa: lo pedido no entra por kilos. Es el caso del cartel.
                'se_pasa' => $topePeso !== null && $pedidoKg > $topePeso,
                // Y el motivo con el que el motor recortó, que es lo que distingue
                // «te falta espacio» de «te pasás de kilos».
                'recorto' => array_filter($filas, fn (array $f) => $f['motivo'] === 'peso') !== [],
            ],
            // El veredicto sale de las FILAS y no de `cabe_todo` del motor, porque un
            // pallet vacío es un «no cabe» que el motor no puede ver: para él la línea
            // pidió cero pallets y los colocó todos.
            'cabeTodo' => array_filter($filas, fn (array $f) => $f['motivo'] !== null) === [],
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
        bool $aprovechar = false,
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
     * Las cargas REALES anotadas para esta combinación (camión + producto + estiba).
     *
     * Es el lazo de vuelta del historial (lote 4): la pantalla que promete un techo
     * muestra al lado lo que entró de verdad las veces que se contó. Sin esto el
     * historial sería un cuaderno — el valor está en que el número aparezca justo donde
     * se toma la decisión.
     *
     * Por COMBINACIÓN y no por camión a secas: la misma bolsa da 420 de pie y 360
     * acostada, así que promediar entre estibas daría un factor que no describe ninguna.
     *
     * Devuelve null cuando no hay nada anotado, que es el caso normal al principio: la
     * pantalla no muestra una fila vacía prometiendo precisión que no tiene.
     *
     * @return ?array{veces:int, factor:float, promedio:int, ultima:?string}
     */
    private function medidoEnTerreno(?CamionSimulacion $camion, ?TipoBulto $bulto, string $estiba): ?array
    {
        if ($camion === null || $bulto === null || ! $bulto->exists) {
            return null;   // sin producto, o un bulto a medida que no está en el catálogo
        }

        $cargas = CargaReal::where('camion_simulacion_id', $camion->id)
            ->where('tipo_bulto_id', $bulto->id)
            ->where('estiba', $estiba)
            ->where('simulado', '>', 0)
            ->get();

        if ($cargas->isEmpty()) {
            return null;
        }

        return [
            'veces' => $cargas->count(),
            'factor' => round($cargas->avg(fn (CargaReal $c) => $c->factor()), 4),
            'promedio' => (int) round($cargas->avg('real')),
            'ultima' => $cargas->max('fecha')?->format('d/m/Y'),
        ];
    }

    /**
     * UNA LÍNEA DE LA CARGA QUE VA SOBRE PALLET (pedido del dueño 10-08-2026: *«si cargo
     * botellones y tapas, también tengo que tener la opción de poder cargar pallets,
     * porque en la vida real cargamos a veces pallets y de paso bidones o dispensadores…
     * dame la chance de cargar cosas mixtas sin sacarme de la interfaz»*).
     *
     * Hasta ahora «Sobre pallet» era un MODO que se comía el camión entero: para ver tres
     * pallets de tapas y cien botellones sueltos había que elegir uno de los dos. Y no es
     * un caso raro — es cómo se carga.
     *
     * NO HAY MOTOR NUEVO, y ese es el punto. Vuelve a aplicarse la idea de §3.3: **un
     * pallet es una caja de carga**. Se le pregunta al mismo `cupo()` cuántas cajas entran
     * encima, y el pallet ARMADO entra a la carga mixta como **un bulto más**
     * (`PalletSimulado::comoBulto()`, con rotación solo horizontal y sin apilar uno sobre
     * otro). El acomodo por zonas lo reparte con los bultos sueltos sin enterarse de que
     * es un pallet.
     *
     * El modelo que se devuelve es un `TipoBulto` **sin guardar** — el mismo truco del
     * bulto a medida: como es la misma clase, la fila del detalle, la letra, el color y el
     * Excel siguen andando sin un solo `if`.
     *
     * @param  array<string, mixed>  $l
     * @param  Collection<int, TipoBulto>  $bultos
     * @return ?array{pallet: PalletSimulado, producto: TipoBulto, porPallet: array, modelo: TipoBulto, bulto: array, clave: string}
     */
    private function palletDeLinea(array $l, $bultos, string $estiba, ?int $apilado): ?array
    {
        $clave = $l['pallet'] ?? null;
        if (! isset(PalletSimulado::TIPOS[$clave])) {
            return null;   // línea suelta, como siempre
        }

        // Qué va ENCIMA. Sin producto no hay pallet que armar: se cae a línea suelta y el
        // validador de siempre la descarta, en vez de dibujar una tarima vacía.
        $producto = ! empty($l['tipo']) ? $bultos->firstWhere('id', (int) $l['tipo']) : null;
        if ($producto === null) {
            return null;
        }

        $medidas = PalletSimulado::TIPOS[$clave];
        $pallet = new PalletSimulado(
            largo_cm: $medidas['largo'],
            ancho_cm: $medidas['ancho'],
            alto_cm: max(PalletSimulado::ALTO_MIN, min(
                PalletSimulado::ALTO_MAX,
                (int) ($l['pallet_alto'] ?? 0) ?: PalletSimulado::ALTO_DEFECTO,
            )),
        );

        $porPallet = $this->calculo->cupo($pallet->comoCajaDeCarga(), $producto->paraCalculo($estiba, $apilado));
        $pesoArmado = self::PESO_MADERA_KG + $porPallet['peso_kg'];

        // `unidadesEncima: 1` A PROPÓSITO, y no las cajas que lleva: en esta lista la línea
        // habla en PALLETS —«3 pallets de tapas»— igual que la de botellones habla en
        // botellones. Las cajas de arriba se informan aparte («18 por pallet»). Si el
        // bulto viajara con 18 unidades, «cargadas 54 de 3» sería un número sin sentido.
        return [
            'clave' => $clave,
            'pallet' => $pallet,
            'producto' => $producto,
            'porPallet' => $porPallet,
            'bulto' => $pallet->comoBulto($pesoArmado, 1),
            'modelo' => new TipoBulto([
                'nombre' => 'Pallet '.$medidas['nombre'].' · '.$producto->nombre,
                'categoria' => 'cajas',
                'largo_cm' => $pallet->largo_cm,
                'ancho_cm' => $pallet->ancho_cm,
                'alto_cm' => $pallet->alto_cm,
                'peso_kg' => $pesoArmado,
                'unidades' => 1,
                'apilable_max' => 1,
                'soporta_peso_encima' => false,
                'orientacion_fija' => false,
                // La mercancía peligrosa no deja de serlo por subirse a un pallet: el
                // aviso de rotulado y segregación tiene que seguir saliendo.
                'peligrosa' => $producto->peligrosa,
                'peligrosa_codigo' => $producto->peligrosa_codigo,
            ]),
        ];
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
    private function escena(?CamionSimulacion $camion, ?TipoBulto $bulto, ?array $resultado, ?array $mixta, string $estiba = 'auto', ?array $enPallet = null, ?int $topeBultos = null, ?AcomodoManual $acomodoManual = null): ?array
    {
        if (! $camion || ($resultado === null && $mixta === null && $enPallet === null)) {
            return null;
        }

        $m = fn (int $cm) => $cm / 100;

        $acomodoManual ??= new AcomodoManual;

        if ($enPallet !== null) {
            return $this->escenaEnPallet($camion, $bulto, $enPallet, $m, $acomodoManual);
        }

        // EL ACOMODO A MANO SE APLICA EN CENTÍMETROS, antes de pasar a metros.
        //
        // Dos razones. Una: el credo del motor es rejilla entera en cm; comparar huellas
        // en metros haría que 0,44 × 3 = 1,3199999999999998 se «pise» con un vecino en
        // 1,32 por dos diezmilésimas de milímetro, y la pantalla marcaría en rojo una
        // carga perfecta. Dos: la carga de arriba de un pallet se rota comparando el
        // largo con que quedó colocado contra el suyo (`interiorDelPallet`), así que si
        // el giro llega ANTES de ese mapeo, el pallet acomodado a mano gira su carga por
        // el mismo camino que ya usaba el giro del motor — sin código nuevo.
        $acomodo = null;

        if ($mixta !== null) {
            // El orden fondo → puerta se fija ACÁ y no se vuelve a tocar: es el ordinal
            // con el que el acomodo guarda cada posición. Reordenar después de mover
            // dejaría las posiciones apuntando a otros bloques en el próximo recálculo.
            $acomodo = $acomodoManual->aplicar(
                collect($mixta['resultado']['bloques'])->sortBy([['x', 'asc'], ['y', 'asc']])->values()->all(),
                $camion->largo_cm,
                $camion->ancho_cm,
            );

            $bloques = collect($acomodo['bloques'])
                ->map(function (array $b) use ($mixta, $m) {
                    $fila = $mixta['lineas'][$b['linea']];

                    $bloque = [
                        'x' => $m($b['x']),
                        'y' => $m($b['y']),
                        // A qué ALTURA apoya (segundo piso, 11-08). Sin pasarlo, el motor
                        // contaría las bolsas de arriba y el lienzo las dibujaría en el
                        // piso, atravesando el muro: el dibujo dejaría de ser la prueba de
                        // lo que el motor hizo, que es todo lo que aporta.
                        'apoyo' => $m($b['apoyo'] ?? 0),
                        'orientacion' => array_map($m, $b['orientacion']),
                        'rejilla' => $b['rejilla'],
                        'cantidad' => $b['cantidad'],
                        'color' => self::COLORES_3D[$b['linea'] % count(self::COLORES_3D)],
                        'letra' => self::letra($b['linea']),
                        'nombre' => $fila['modelo']->nombre,
                        'forma' => $fila['modelo']->formaVisor(),
                        // El visor tiene que dibujar la MISMA estiba que se calculó: si no, el
                        // dibujo diría «de pie» mientras el cálculo dice «acostado» y el
                        // lienzo dejaría de ser la prueba de lo que el motor hizo, que es todo
                        // lo que aporta.
                        'estiba' => TipoBulto::estibaEfectiva($fila['estiba']),
                    ];

                    // Una línea EN PALLET se dibuja como pallet: tarima de madera con su
                    // carga encima, con el MISMO `forma: 'pallet'` + `interior` que ya usa
                    // el modo Sobre pallet. Cero JS nuevo — el visor no distingue de qué
                    // pantalla vino el bloque.
                    // `array_merge` y NO el operador `+`: `+` conserva la clave de la
                    // izquierda, así que `forma` habría seguido siendo 'caja' y el pallet
                    // se habría dibujado como un cajón liso, en silencio.
                    return $fila['pallet'] === null ? $bloque : array_merge($bloque, [
                        'forma' => 'pallet',
                        'base' => $m($fila['pallet']['base_cm']),
                        'interior' => $this->interiorDelPallet(
                            $fila['pallet'], $b['orientacion']['largo'], $m, $bloque['color'],
                        ),
                    ]);
                })
                ->all();
        } else {
            // Con una CANTIDAD A PROBAR, el dibujo se capa a lo pedido: si el veredicto
            // dice «entran tus 50», el camión tiene que mostrar 50, no el máximo.
            $enEscena = min($resultado['bultos'], $topeBultos ?? PHP_INT_MAX);

            // El cupo máximo también se puede acomodar: es el caso de UN bloque, y ahí
            // vive la carga «de a un bulto» que se pidió (una línea de 1 es un bloque de
            // 1, o sea la caja suelta que se arrastra y se gira).
            $acomodo = $acomodoManual->aplicar($enEscena > 0 ? [[
                'x' => 0,
                'y' => 0,
                'orientacion' => $resultado['orientacion'],
                'rejilla' => $resultado['rejilla'],
                'cantidad' => $enEscena,
            ]] : [], $camion->largo_cm, $camion->ancho_cm);

            $bloques = collect($acomodo['bloques'])->map(fn (array $b) => [
                'x' => $m($b['x']),
                'y' => $m($b['y']),
                'orientacion' => array_map($m, $b['orientacion']),
                'rejilla' => $b['rejilla'],
                'cantidad' => $b['cantidad'],
                'color' => self::COLORES_3D[0],
                'letra' => self::letra(0),
                'nombre' => $bulto->nombre,
                'forma' => $bulto->formaVisor(),
                'estiba' => TipoBulto::estibaEfectiva($estiba),
            ])->all();
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
            // EL TABLERO: la misma carga vista desde arriba, en centímetros enteros, para
            // arrastrarla y girarla. Viaja con la escena y no aparte porque tiene que
            // llegar también al link compartido: ahí no se puede tocar, pero el aviso de
            // «acomodo a mano» sí se tiene que ver.
            'acomodo' => $this->tablero($acomodo, $bloques, $camion),
        ];
    }

    /**
     * El tablero del acomodo manual: piso, huellas y lo que está mal.
     *
     * Las medidas salen de los bloques EN CENTÍMETROS que devolvió el acomodo y no de los
     * de la escena: los de la escena ya pasaron por metros, y volver a multiplicar por 100
     * para dibujar un tablero que después escribe centímetros en la URL es dar dos vueltas
     * para llegar al mismo entero, con una posibilidad de perderlo en el camino.
     *
     * @param  array<string, mixed>  $acomodo  lo que devolvió `AcomodoManual::aplicar`
     * @param  list<array<string, mixed>>  $escena  los mismos bloques ya en metros, por color y nombre
     * @return array<string, mixed>
     */
    private function tablero(array $acomodo, array $escena, CamionSimulacion $camion): array
    {
        $piezas = [];

        foreach ($acomodo['bloques'] as $i => $b) {
            $piezas[] = [
                'x' => $b['x'],
                'y' => $b['y'],
                'largo' => $b['rejilla']['largo'] * $b['orientacion']['largo'],
                'ancho' => $b['rejilla']['ancho'] * $b['orientacion']['ancho'],
                'girado' => (bool) ($b['girado'] ?? false),
                'cantidad' => $b['cantidad'],
                // El color y la letra son los MISMOS del lienzo (por eso salen de la
                // escena y no se recalculan): el tablero y el dibujo 3D tienen que
                // nombrar cada bloque igual, o hay que adivinar cuál se está moviendo.
                'color' => sprintf('#%02x%02x%02x', ...$escena[$i]['color']),
                'letra' => $escena[$i]['letra'],
                'nombre' => $escena[$i]['nombre'],
            ];
        }

        return [
            'piso' => ['largo' => $camion->largo_cm, 'ancho' => $camion->ancho_cm],
            'piezas' => $piezas,
            'activo' => $acomodo['activo'],
            'choques' => $acomodo['choques'],
            'fuera' => $acomodo['fuera'],
            'descartado' => $acomodo['descartado'],
        ];
    }

    /**
     * La carga que va ENCIMA de un pallet, lista para el visor.
     *
     * Si el motor giró el pallet 90° para que entrara, el interior viene **ya girado**
     * desde acá: el visor no tiene que deducirlo y así el dibujo no puede contradecir al
     * cálculo. Es la misma regla que ya seguía el modo Sobre pallet, extraída para que las
     * dos pantallas la compartan en vez de tener cada una su copia — que es exactamente
     * como se desincronizan.
     *
     * @param  array<string, mixed>  $pallet  la clave `pallet` de la fila
     * @param  int  $largoColocadoCm  el largo con que el motor lo puso en el camión
     * @param  list<int>  $color  el del BLOQUE: la carga de arriba es del mismo producto
     */
    private function interiorDelPallet(array $pallet, int $largoColocadoCm, callable $m, array $color): array
    {
        $rejilla = $pallet['rejilla'];
        $ori = $pallet['orientacion'];

        if ($largoColocadoCm !== $pallet['largo_cm']) {
            $rejilla = ['largo' => $rejilla['ancho'], 'ancho' => $rejilla['largo'], 'alto' => $rejilla['alto']];
            $ori = ['largo' => $ori['ancho'], 'ancho' => $ori['largo'], 'alto' => $ori['alto']];
        }

        return [
            'rejilla' => $rejilla,
            'orientacion' => array_map($m, $ori),
            'cantidad' => $pallet['bultos_por_pallet'],
            'forma' => $pallet['producto']->formaVisor(),
            'color' => $color,
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
    private function escenaEnPallet(CamionSimulacion $camion, TipoBulto $bulto, array $enPallet, callable $m, AcomodoManual $acomodoManual): array
    {
        $p = $enPallet['pallet'];
        $enCamion = $enPallet['enCamion'];
        $porPallet = $enPallet['porPallet'];

        $comun = [
            'rejilla' => $porPallet['rejilla'],
            'orientacion' => $porPallet['orientacion'],
            'bultos_por_pallet' => $porPallet['bultos'],
            'producto' => $bulto,
            'largo_cm' => $p->largo_cm,
        ];

        // Este modo también se acomoda a mano: es el caso de UN bloque de pallets, y
        // dejarlo afuera sería un agujero arbitrario —el usuario arma el pallet, lo sube
        // y de golpe no puede moverlo—. Como en los otros dos modos, el acomodo se aplica
        // en centímetros y ANTES de resolver el interior, así la carga de arriba gira con
        // la tarima por el camino de siempre.
        $acomodo = $acomodoManual->aplicar($enPallet['cabenPallets'] > 0 ? [[
            'x' => 0,
            'y' => 0,
            'orientacion' => $enCamion['orientacion'],
            'rejilla' => $enCamion['rejilla'],
            'cantidad' => $enPallet['cabenPallets'],
        ]] : [], $camion->largo_cm, $camion->ancho_cm);

        $bloques = collect($acomodo['bloques'])->map(fn (array $b) => [
            'x' => $m($b['x']),
            'y' => $m($b['y']),
            'orientacion' => array_map($m, $b['orientacion']),
            'rejilla' => $b['rejilla'],
            'cantidad' => $b['cantidad'],
            'color' => self::COLORES_3D[0],
            'letra' => self::letra(0),
            'nombre' => $bulto->nombre,
            'forma' => 'pallet',
            'estiba' => 'pie',
            'base' => $m($p->base_cm),
            'interior' => $this->interiorDelPallet($comun, $b['orientacion']['largo'], $m, self::COLORES_3D[0]),
        ])->all();

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
            'acomodo' => $this->tablero($acomodo, $bloques, $camion),
            // Lo que el visor dibuja EN EL PISO, al lado del camión, mientras el pallet
            // todavía no se subió (pedido del dueño 06-08).
            'pallet' => [
                'largo' => $m($p->largo_cm),
                'ancho' => $m($p->ancho_cm),
                'alto' => $m($p->alto_cm),
                'base' => $m($p->base_cm),
                'nombre' => $bulto->nombre,
                'unidades' => $enPallet['unidadesPorPallet'],
                // Sin girar: al lado del camión se muestra como se arma, a lo largo. Se
                // pide con su propio largo, así que el helper no lo rota.
                'interior' => $this->interiorDelPallet($comun, $p->largo_cm, $m, self::COLORES_3D[0]),
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
