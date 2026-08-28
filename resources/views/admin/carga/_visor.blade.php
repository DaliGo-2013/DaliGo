{{--
    El visor 3D y su MENÚ DE HERRAMIENTAS. Requiere: $escena.

    Estaba COPIADO en los dos modos de la pantalla (cupo máximo y carga mixta),
    idéntico. Al sumar los controles de zoom habrían quedado dos copias que
    driftean, así que se extrajo acá: un solo lugar donde agregar botones.

    ────────────────────────────────────────────────────────────────────────────
    UN MENÚ LATERAL, NO BOTONES POR TODA LA PANTALLA (pedido del dueño 06-08-2026)

    «Quiero que organices los botones en un menú, como las imágenes de EasyCargo,
    en un lateral… y no tenga tantos botones por toda la pantalla, siento que
    genera confusión.»

    Antes los controles vivían en las CUATRO esquinas del lienzo (vistas arriba a
    la izquierda, zoom arriba a la derecha, pasos de carga abajo a la izquierda,
    códigos y nombres abajo a la derecha) y encima el cubicaje flotando. Cada
    pedido nuevo sumaba una esquina.

    Es una BARRA FIJA que le come ancho al lienzo, no un panel flotante encima:
    flotando taparía el camión, y agrandar el camión fue justamente lo que pidió
    dos días antes. Como el visor mide el recuadro real para encuadrar, el camión
    se reacomoda solo al ancho que queda (y al abrir o cerrar el menú).

    En celular arranca CERRADO: 14 rem de un ancho de 342 px serían la mitad de la
    pantalla. El botón ☰ lo abre.

    EVOLUCIÓN «CABINA» (dueño, 21-08-2026, opción D de un canvas de propuestas):
    el acordeón de secciones se reemplazó por cabecera de iconos + cuerpo CARGAR +
    pie con hojas («Compartir» / «Herramientas»). El QUÉ vive abajo en «EL MENÚ»;
    el PORQUÉ y lo que NO cambió: docs/reglas/simulador-de-carga.md §4.1nonies-ter.
    ────────────────────────────────────────────────────────────────────────────

    ZOOM SOLO EN ESCRITORIO (pedido del dueño 05-08-2026: «no lo quiero para
    celular, no quiero que se quede pegada o se ponga lento»). Se cumple por
    construcción, no por un `if`: el zoom entra por la RUEDA del mouse (que un
    táctil no emite) y sus botones viven en un contenedor `hidden lg:flex`.
    NO se registran handlers de touch ni de pinza. En celular sigue andando el
    arrastre para girar, que ya estaba.
--}}
@php
    $ctrl = \App\Http\Controllers\Admin\SimuladorCargaController::class;
    // MODO PÚBLICO: el mismo visor servido por un link compartido, sin login.
    // Se apagan las secciones que NAVEGAN hacia adentro de la app (comparar
    // camiones, bajar el Excel, armar pallets, importar). Sin esto el link sería
    // una puerta a rutas que piden permiso: rebotarían igual, pero mostrarle al
    // cliente que existen no aporta nada. Lo que queda —vistas, cargar, rótulos—
    // es mirar el dibujo, que es para lo que se comparte.
    $publico = $publico ?? false;
    // Clases compartidas por los botones del menú: se repiten en cinco grupos y
    // escribirlas a mano dejaba unos con `py-1` y otros con `py-1.5`.
    $btn = 'rounded-lg border border-neutral-300 bg-white px-2 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50';
    // La variante SIN padding, para los botones de ícono de la cabecera. No es
    // `$btn` más un padding-cero concatenado: con dos paddings en el class gana el
    // que va después en el CSS compilado (y el de cero va antes → override inerte),
    // el mismo footgun de los tamaños de íconos de la bitácora [2026-07-24]. Ojo:
    // acá tampoco se ESCRIBE esa clase ni en comentarios — Tailwind escanea los
    // Blade como texto y la metería al bundle (bitácora [2026-07-30]).
    $btnIcono = 'flex items-center justify-center rounded-lg border border-neutral-300 bg-white font-medium text-neutral-700 transition hover:bg-neutral-50';
    $titulo = 'px-1 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-neutral-400';
    // El acomodo a mano: si viene uno aplicado, el tablero arranca ABIERTO. Llegar a un
    // camión acomodado y tener que buscar dónde se toca eso es la peor versión.
    $acomodo = $escena['acomodo'] ?? null;

    // EL VEREDICTO, para decirlo ARRIBA del dibujo (pedido del dueño 12-08-2026,
    // dibujado sobre la pantalla: «el mensaje NO CABE TODO arriba, que aparezca cuando
    // no entra todo»). Estaba en una tarjeta DEBAJO del visor: había que mirar el camión,
    // bajar y recién ahí enterarse de que no entraba.
    //
    // `null` = esta pantalla no responde sí/no y por eso no muestra cartel: el cupo
    // máximo sin cantidad a probar contesta «cuántos entran», que es otra pregunta, y el
    // armado del pallet todavía no es una carga.
    $cabe = null;
    $detalle = null;
    // Texto propio del cartel. `null` = el de siempre («Cabe todo ✓» / «No cabe todo»);
    // con una sola línea sin cantidad lo reemplaza el NÚMERO, que es la respuesta.
    $veredicto = null;
    $n = fn ($x) => number_format($x, 0, ',', '.');

    // LA RESPUESTA A «¿CUÁNTO ENTRA?» ES EL VEREDICTO (21-08, al fusionar las dos
    // preguntas). Con una sola línea SIN cantidad, «Cabe todo ✓» sería una respuesta
    // vacía: por definición cabe: no se pidió un número. Lo que se vino a preguntar es
    // CUÁNTOS, así que el cartel dice el número — que es exactamente lo que decía la
    // tarjeta «ENTRAN 550» de la pestaña que desapareció, pero arriba del dibujo, donde
    // la doctrina §4.3bis manda poner la respuesta.
    $abiertas = collect($mixta['lineas'] ?? [])->filter(fn ($l) => $l['abierta']);
    $soloUnaAbierta = ($mixta ?? null) !== null
        && count($mixta['lineas']) === 1
        && $abiertas->count() === 1;

    if ($soloUnaAbierta) {
        $fila = $mixta['lineas'][array_key_first($mixta['lineas'])];
        $cabe = true;
        $veredicto = 'Entran '.$n($fila['cargadas_unidades']);
        $partesDetalle = [$fila['modelo']->nombre.' en '.$escena['vehiculo']['nombre']];
        // Los BULTOS además de las unidades: el vendedor cotiza en botellones y el
        // cargador cuenta bolsas. Decir solo uno de los dos obliga a dividir a mano.
        if ($fila['modelo']->unidades > 1) {
            $partesDetalle[] = $n($fila['bultos_colocados']).' '
                .\Illuminate\Support\Str::plural('bulto', $fila['bultos_colocados'])
                .' de '.$fila['modelo']->unidades;
        }
        // Y SI EL PESO CORTÓ ANTES QUE EL ESPACIO se dice acá mismo (pedido del dueño
        // 11-08). Es la diferencia entre «entran 154» y «entran 154 de los 324 que
        // caben, porque te quedaste sin kilos» — con la segunda, mandar un camión más
        // grande no sirve y eso cambia la decisión.
        if ($fila['limita'] === 'peso' && ($fila['por_espacio'] ?? 0) > $fila['bultos_colocados']) {
            $partesDetalle[] = 'por espacio entrarían '.$n($fila['por_espacio'])
                .', pero se llena de kilos antes';
        }
        $detalle = implode(' · ', $partesDetalle).'.';
    } elseif (! $publico && ($mixta ?? null) !== null && count($mixta['lineas']) === 1 && $mixta['cabeTodo']
        && ($mixta['lineas'][array_key_first($mixta['lineas'])]['pedidas_unidades'] ?? null) !== null) {
        // UN SOLO PRODUCTO CON CANTIDAD Y ENTRA TODO: se le DEVUELVE EL NÚMERO que pidió.
        // «Cabe todo ✓» es correcto y no contesta: la pregunta fue «¿me entran 50?» y la
        // respuesta útil repite el 50. Es el gemelo del caso de abajo —el que dice «de tus
        // 500 entran 420»— y sin él la pantalla solo pone números cuando la noticia es
        // mala, que es la mitad más fácil de leer mal.
        $fila = $mixta['lineas'][array_key_first($mixta['lineas'])];
        $cabe = true;
        $veredicto = 'Tus '.$n($fila['pedidas_unidades']).' entran';
        $detalle = $fila['modelo']->nombre.' en '.$escena['vehiculo']['nombre'].'.';
    } elseif (! $publico && ($mixta ?? null) !== null && count($mixta['lineas']) === 1 && ! $mixta['cabeTodo']) {
        // UN SOLO PRODUCTO CON CANTIDAD Y NO ENTRA TODO: el cartel lleva los NÚMEROS y
        // no la frase pelada. La pregunta fue «¿me entran 500?», así que «no cabe todo»
        // obligaría a bajar la vista a buscar cuántos sí — y esta era la respuesta de la
        // pestaña «¿Cuánto entra?» con su cantidad a probar, que al fusionar había que
        // conservar (el caso que solo tenía UNA de las dos pantallas, 21-08).
        $fila = $mixta['lineas'][array_key_first($mixta['lineas'])];
        $cabe = false;
        $detalle = 'De tus '.$n($fila['pedidas_unidades']).' entran '.$n($fila['cargadas_unidades'])
            .'. Quedan '.$n($fila['pedidas_unidades'] - $fila['cargadas_unidades']).' afuera.';
    } elseif (($mixta ?? null) !== null) {
        $cabe = $mixta['cabeTodo'];
        // «Con eso se negocia» es lo que se le dice al VENDEDOR. El mismo cartel viaja
        // al link compartido, donde del otro lado hay un cliente o un conductor: ahí la
        // frase sobra y suena a que se está calculando cuánto apretarlo.
        // OJO CON EL «ABAJO». Decía «Abajo está qué queda afuera y por qué» cuando la lista
        // de la carga vivía debajo del camión; al mudarla al panel (21-08) esa frase quedó
        // señalando un lugar vacío. Ahora no nombra un lugar: la lista está en el panel en
        // escritorio y detrás del cajón en el celular, así que cualquier «abajo» o
        // «izquierda» es verdad solo en una de las dos. En el link PÚBLICO sí está abajo —
        // esa pantalla trae su propia tabla— y por eso su texto se conserva tal cual.
        $detalle = match (true) {
            $cabe => 'La carga completa entra en '.$escena['vehiculo']['nombre'].'.',
            $publico => 'Queda carga afuera. Abajo, producto por producto.',
            default => 'Cada producto dice cuánto queda afuera y por qué — con eso se negocia.',
        };
        // Con una línea «hasta llenar» entre varias, el premio de haber fusionado: se
        // dice cuánto MÁS entra en lo que sobra, que es lo que ninguna de las dos
        // pestañas viejas podía contestar.
        if ($cabe && $abiertas->isNotEmpty()) {
            $relleno = $abiertas->first();
            $detalle = 'Lo pedido entra, y en lo que sobra caben '
                .$n($relleno['cargadas_unidades']).' '.$relleno['modelo']->nombre.' más.';
        }
    }
    // Había una cuarta rama, para la «cantidad a probar» del cupo de un producto
    // (`$prueba`). Se fue con la fusión y no se perdió nada: esa cantidad ahora ES la de
    // la línea, así que sus dos textos —«Tus 50 entran» y «De tus 500 entran 420»— son
    // las dos ramas de arriba, calculados sobre lo que el motor colocó de verdad en vez
    // de sobre una comparación hecha aparte.
@endphp

<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm"
     {{-- Ya no hay estado `cubicar` acá: el cubicaje es una PESTAÑA de la página y su
          estado es el `modo` del contenedor de arriba. Lo que sí se conserva es que la
          página vuelva ABIERTA en él tras agregar un bulto (`cubicar=1` en la query) — el
          reclamo textual del dueño el 12-08: «le doy clic y se sale todo y me deja la
          interfaz sin nada». Eso lo decide `index.blade.php` al sembrar `modo`. --}}
     x-data="{ menu: window.innerWidth >= 640, tablero: {{ ($acomodo['activo'] ?? false) ? 'true' : 'false' }}, hoja: null, camiones: false }"
     {{-- El tablero de acomodo se abre también desde la pestaña «Cubicar», que es un
          HERMANO de este recuadro (el cubicaje dejó de vivir adentro el 21-08). Un
          `$dispatch` sube por el árbol y nunca llega a un hermano, así que el evento va
          por `window`. Es la misma forma que usa el menú para abrir «Importar», al
          revés. --}}
     @abrir-tablero.window="tablero = true; menu = true">

    {{-- ═══ EL CAMIÓN EN NÚMEROS ═══
         Pedido del dueño (10-08): «la descripción del camión la quiero adentro del
         cuadrado donde está el camión, para poder usar mejor el espacio». Era una
         tarjeta aparte debajo del visor, con su borde, su sombra y el hueco entre las
         dos; adentro se ahorran los tres.

         ARRIBA y ya no al pie (12-08, dibujado sobre la pantalla): es la ficha de lo
         que se está mirando, así que se lee ANTES del dibujo. Al pie quedaba después
         del tablero de acomodo, o sea a dos pantallazos del camión que describe.

         Va como FRANJA y no flotando sobre el lienzo: un panel encima taparía el camión,
         que es la doctrina del 06-08 anotada arriba en este mismo archivo. Con
         `bg-neutral-50/70` —el mismo fondo del menú lateral— se lee como parte del visor
         y no como una tarjeta de contenido metida adentro. --}}
    @if ($camion ?? null)
        {{-- LA FICHA VIVE EN LA ⓘ (pedido del dueño 21-08, señalando el listado de
             Inventario: «el detalle de la especificación del Contenedor 40 —medidas
             útiles, volumen, carga máxima y piso libre en la puerta— dejalo como el
             icono de notificación»). Es su propia doctrina del 17-08 aplicada acá: la
             explicación larga se esconde, el estado se muestra.

             Lo que QUEDA a la vista es el nombre del camión —lo que se está mirando— y
             el piso libre, que no es una especificación del camión sino el resultado de
             ESTA carga: es el «Free meters» de EasyCargo, la respuesta a «¿le sumo algo
             más a este viaje?», y cambia con cada cálculo. Esconderlo junto a las
             medidas fijas confundiría un dato vivo con la ficha técnica. --}}
        <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1 border-b border-neutral-200 bg-neutral-50/70 px-4 py-2.5 text-sm">
            <span class="inline-flex items-center gap-1.5">
                <span class="font-semibold text-neutral-900">{{ $camion->nombre }}</span>
                <x-info-tip>
                    <span class="block font-semibold text-neutral-900">{{ $camion->nombre }}</span>
                    <span class="mt-1 block">
                        Medidas útiles
                        <span class="font-medium tabular-nums text-neutral-700">{{ number_format($camion->largo_cm / 100, 2, ',', '.') }} × {{ number_format($camion->ancho_cm / 100, 2, ',', '.') }} × {{ number_format($camion->alto_cm / 100, 2, ',', '.') }} m</span>
                        · Volumen
                        <span class="font-medium tabular-nums text-neutral-700">{{ number_format($camion->volumenM3(), 1, ',', '.') }} m³</span>
                        · Carga máxima
                        @if ($camion->peso_max_kg)
                            <span class="font-medium tabular-nums text-neutral-700">{{ number_format($camion->peso_max_kg, 0, ',', '.') }} kg</span>
                        @else
                            <span class="text-neutral-400">sin dato</span>
                        @endif
                        @if ($camion->pasillo_cm > 0)
                            · Pasillo reservado
                            <span class="font-medium tabular-nums text-neutral-700">{{ $camion->pasillo_cm }} cm</span>
                        @endif
                    </span>
                    <span class="mt-1.5 block">
                        Son medidas por DENTRO de la caja, no la ficha del fabricante: entre
                        exterior e interior hay 10 a 20% de volumen, que es la diferencia
                        entre que la carga entre o quede en el andén.
                    </span>
                </x-info-tip>
            </span>
            {{-- El «Free meters» de EasyCargo: más accionable que el % de ocupación
                 para «¿le sumo algo más a este viaje?». Se queda a la vista porque es
                 RESULTADO de esta carga, no especificación del camión. --}}
            <span class="text-neutral-500">Piso libre en la puerta
                <span class="font-medium tabular-nums text-neutral-900">{{ number_format($escena['libre_m'], 2, ',', '.') }} m</span>
            </span>
            {{-- LO QUE YA LLEVABA (lote 5). Va en la MISMA franja que las medidas y no
                 en un aviso aparte: es lo que explica por qué el cupo salió más chico
                 que otras veces con el mismo camión. Sin decirlo, el número recortado
                 se lee como un error del cálculo. --}}
            @if ($mixta['ocupado']['hay'] ?? false)
                @php
                    // Se arma en PHP y no con `@if` inline: un `@endif` pegado al texto
                    // («… m@endif») no lo reconoce el compilador de Blade y revienta la
                    // vista entera con «unexpected end of file». Ya pasó antes en esta
                    // misma pantalla.
                    $partes = [];
                    if ($mixta['ocupado']['cm'] > 0) {
                        $partes[] = number_format($mixta['ocupado']['cm'] / 100, 2, ',', '.').' m';
                    }
                    if ($mixta['ocupado']['kg'] > 0) {
                        $partes[] = number_format($mixta['ocupado']['kg'], 0, ',', '.').' kg';
                    }
                @endphp
                <span class="font-medium text-amber-700">Ya lleva
                    <span class="tabular-nums">{{ implode(' · ', $partes) }}</span>
                    <span class="font-normal text-amber-600">— descontado</span>
                </span>
            @endif
        </div>
    @endif

    {{-- ═══ EL VEREDICTO ═══
         Pegado al borde de arriba del lienzo, que es donde el dueño lo dibujó: la
         respuesta y la prueba de la respuesta se leen de un vistazo.

         El «no cabe» va en ROJO y a todo el ancho, no como un chip discreto: es la
         única línea de esta pantalla que cambia una decisión comercial. El «cabe» va
         sobrio a propósito — una franja verde gigante para la respuesta esperada
         entrena a ignorar la franja, y entonces la roja tampoco se ve. --}}
    @if ($cabe !== null)
        <div @class([
            'flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b px-4 py-2.5',
            'border-red-200 bg-red-50' => ! $cabe,
            'border-neutral-200 bg-white' => $cabe,
        ])>
            <p @class([
                'text-lg font-semibold leading-tight',
                'text-red-700' => ! $cabe,
                'text-brand-600' => $cabe,
            ])>{{ $veredicto ?? ($cabe ? 'Cabe todo ✓' : 'No cabe todo') }}</p>
            <p @class(['text-sm', 'text-red-700' => ! $cabe, 'text-neutral-500' => $cabe])>{{ $detalle }}</p>

            {{-- ═══ LO QUE ENTRÓ DE VERDAD, AL LADO DE SU TECHO ═══
                 El lazo de vuelta del historial (lote 4). Vivía en la tarjeta «ENTRAN N»
                 de la pestaña «¿Cuánto entra?»; al fusionar las dos preguntas (21-08) esa
                 tarjeta desaparece, y el número calculado se mudó a esta franja — así que
                 la calibración se muda con él. Es el quinto caso que solo tenía UNA de las
                 dos pantallas: dejarlo caer habría apagado un candado sin que nadie se
                 enterara, que es la lección de la bitácora [2026-08-20].

                 NO corrige el cupo, lo acompaña: reemplazarlo sería cambiar un número
                 verificable por el promedio de dos anécdotas. Mostrar los dos deja ver el
                 hueco, que es la información.

                 Va en la MISMA franja y no en una tarjeta nueva: la pantalla tiene que
                 caber sin barra de scroll (pedido del dueño 21-08), y el `flex-wrap` de
                 acá arriba lo baja solo cuando no entra al lado. Fuera del link público:
                 es calibración interna, y del otro lado hay un cliente o un conductor.
                 A qué combinación corresponde lo decide el controlador
                 (`medidoDeLaPantalla`), no esta vista. --}}
            @if (! $publico && ! empty($medido ?? null))
                <p class="text-sm text-neutral-500">
                    En terreno entraron
                    <span class="font-medium tabular-nums text-neutral-700">{{ $n($medido['promedio']) }}</span>
                    ({{ round($medido['factor'] * 100) }}% de lo calculado) ·
                    <a href="{{ route('admin.cargas-reales.index') }}"
                       class="font-medium text-brand-700 hover:text-brand-600">{{ $medido['veces'] === 1 ? 'una carga anotada' : $medido['veces'].' cargas anotadas' }}</a>
                </p>
            @endif
        </div>
    @endif
    <div class="flex items-stretch">

        {{-- ═══ EL MENÚ · «CABINA» ═══
             Decisión del dueño 21-08-2026, elegida sobre un canvas de 4 propuestas
             (§4.1nonies-ter de docs/reglas/simulador-de-carga.md): el acordeón de
             secciones se reemplaza por TRES zonas — CABECERA fija con lo visual como
             iconos, CUERPO que es solo CARGAR (más el Cubicaje en mixta), y PIE con
             dos lanzadores («Compartir», «Herramientas») que abren HOJAS dentro del
             propio panel. Las hojas se muestran INTERCAMBIANDO el cuerpo con x-show,
             sin posicionamiento especial: nada del menú flota sobre el lienzo
             (doctrina 06-08) y el candado de «nada absoluto dentro del menú» sigue
             contando tal cual.

             El `aria-label` nombra el panel para lectores de pantalla Y es el ANCLA
             de los candados: los tests rebanan el HTML desde el texto «Herramientas»
             hasta `</aside>`, así que tiene que aparecer ANTES del primer control —
             por eso va antes que `class` en este tag. --}}
        <aside x-show="menu" x-cloak aria-label="Herramientas"
               class="flex w-56 shrink-0 flex-col border-r border-neutral-200 bg-neutral-50/70 text-xs"
               style="max-height: 80vh">

            {{-- CABECERA: vistas, zoom y rótulos como botonera compacta, siempre a un
                 toque (la lección de la propuesta C: guardar las vistas detrás de una
                 pestaña obligaba a un ping-pong de clics mientras se carga). --}}
            <div class="shrink-0 space-y-1.5 border-b border-neutral-200 bg-white p-2">
                {{-- Vistas fijas: el panel «Views» de EasyCargo, ahora como iconos.
                     Van en celular también — sin zoom táctil, cambiar de vista es la
                     única forma de mirar la carga desde otro lado sin pelear con el
                     arrastre. El JS les alterna las clases de activo
                     (bg-brand-600 / text-white), por eso conservan la receta
                     bg-white / text-neutral-700 del resto de los botones. --}}
                <div class="flex items-stretch gap-1">
                    <button type="button" id="carga3dVista3d" aria-pressed="false" title="3D"
                            class="{{ $btnIcono }} h-9 flex-1">
                        <x-icon.cube class="h-4 w-4" /><span class="sr-only">3D</span>
                    </button>
                    <button type="button" id="carga3dVistacostado" aria-pressed="false" title="Costado"
                            class="{{ $btnIcono }} h-9 flex-1">
                        <x-icon.vista-costado class="h-4 w-4" /><span class="sr-only">Costado</span>
                    </button>
                    <button type="button" id="carga3dVistaplanta" aria-pressed="false" title="Planta"
                            class="{{ $btnIcono }} h-9 flex-1">
                        <x-icon.vista-planta class="h-4 w-4" /><span class="sr-only">Planta</span>
                    </button>
                    <button type="button" id="carga3dVistapuerta" aria-pressed="false" title="Puerta"
                            class="{{ $btnIcono }} h-9 flex-1">
                        <x-icon.vista-puerta class="h-4 w-4" /><span class="sr-only">Puerta</span>
                    </button>
                    <button type="button" @click="menu = false"
                            class="flex h-9 w-7 shrink-0 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700"
                            title="Cerrar el menú">
                        <x-icon.x-mark class="h-4 w-4" /><span class="sr-only">Cerrar el menú</span>
                    </button>
                </div>
                {{-- Zoom: escritorio únicamente (ver el encabezado del archivo). El
                     envoltorio `hidden lg:block` es el mismo de siempre — lo vigila
                     test_el_zoom_se_ofrece_solo_en_escritorio. La ayuda del botón
                     derecho no se repite acá: ya la dice el letrero del lienzo. --}}
                <div class="hidden lg:block">
                    <div class="flex items-stretch gap-1">
                        <button type="button" id="carga3dMenos" aria-label="Alejar" title="Alejar"
                                class="{{ $btnIcono }} h-8 flex-1">−</button>
                        <button type="button" id="carga3dMas" aria-label="Acercar" title="Acercar"
                                class="{{ $btnIcono }} h-8 flex-1">+</button>
                        <button type="button" id="carga3dReset" title="Reiniciar · vuelve al encuadre de siempre"
                                class="{{ $btnIcono }} h-8 flex-1">
                            <x-icon.arrow-path class="h-4 w-4" /><span class="sr-only">Reiniciar</span>
                        </button>
                    </div>
                </div>
                {{-- Rótulos del dibujo, CON SU PALABRA (QA del dueño 21-08: probó «#» y
                     «ABC» y preguntó qué hacían — «les doy clic y no pasa nada», porque
                     con el camión vacío no hay bloques que rotular y el glifo no se
                     explica solo). Vuelven los nombres de siempre; apagados, el JS les
                     agrega bg-neutral-100 / text-neutral-400. --}}
                <div class="grid grid-cols-2 gap-1">
                    <button type="button" id="carga3dCodigos" aria-pressed="true"
                            title="Mostrar u ocultar la letra de cada producto sobre sus cajas"
                            class="{{ $btn }}">Códigos</button>
                    <button type="button" id="carga3dNombres" aria-pressed="true"
                            title="Mostrar u ocultar el nombre de cada producto sobre su bloque"
                            class="{{ $btn }}">Nombres</button>
                </div>
            </div>

            {{-- CUERPO = CARGAR. El panel entero es la palanca de carga; lo visual ya
                 quedó arriba y lo que se usa de a poco espera en las hojas del pie. --}}
            {{-- El cuerpo se parte en DOS: arriba el bloque de cargar, que es fijo, y
                 abajo LA CARGA, que se lleva el alto que sobra y es lo único que
                 scrollea. Es la mecánica del «todo en una pantalla» que pidió el dueño
                 el 21-08 («sin tener que deslizar hacia abajo con una barra sino
                 trabajar todo en una misma pantalla»): con `flex-1` + `min-h-0` la
                 lista se estira o se encoge con la ventana y nada más se mueve. --}}
            <div x-show="hoja === null" class="flex min-h-0 flex-1 flex-col">
            <div class="shrink-0 overflow-y-auto p-2">

                {{-- ¿EN CUÁL CONVIENE? La comparativa asciende a un CHIP pegado al
                     número (antes era la sección «Camiones»): el mejor camión a la
                     vista, y la lista completa al tocarlo. Cada fila sigue siendo un
                     enlace que cambia de camión conservando producto, estiba, apilado
                     y las líneas de la carga mixta. --}}
                @if (! $publico && ! empty($comparativa))
                    @php $mejor = $comparativa[0]; @endphp
                    <button type="button" @click="camiones = ! camiones"
                            :aria-expanded="camiones ? 'true' : 'false'"
                            class="mb-1 flex w-full items-center justify-between gap-2 rounded-lg border border-brand-200 bg-brand-50 px-2 py-1.5 text-brand-700 transition hover:bg-brand-100"
                            title="Comparar los {{ count($comparativa) }} camiones de la flota">
                        <span class="min-w-0 truncate">
                            @if ($mejor['cabe'])
                                Hasta <span class="font-semibold tabular-nums">{{ number_format($mejor['unidades'], 0, ',', '.') }}</span> en {{ $mejor['camion']->nombre }}
                            @else
                                Comparar camiones
                            @endif
                        </span>
                        {{-- El binding va en un <span> envoltorio y no en el componente
                             del ícono: una directiva Blade dentro del atributo de un
                             componente no se compila (bitácora 2026-08-14). --}}
                        <span class="shrink-0 transition" :class="camiones ? 'rotate-90' : ''">
                            <x-icon.chevron-right class="h-3.5 w-3.5" />
                        </span>
                    </button>
                    <div x-show="camiones" x-cloak class="mb-1 space-y-1">
                        @foreach ($comparativa as $fila)
                            <a href="{{ request()->fullUrlWithQuery(['camion_id' => $fila['camion']->id]) }}"
                               @class([
                                   'flex items-center justify-between gap-2 rounded-lg border px-2 py-1.5 transition',
                                   'border-brand-300 bg-brand-50' => $fila['actual'],
                                   'border-neutral-200 bg-white hover:bg-neutral-50' => ! $fila['actual'],
                               ])
                               @if ($fila['actual']) aria-current="true" @endif>
                                <span class="min-w-0 truncate {{ $fila['actual'] ? 'font-semibold text-brand-700' : 'text-neutral-700' }}">
                                    {{ $fila['camion']->nombre }}
                                </span>
                                <span class="shrink-0 tabular-nums {{ $fila['cabe'] ? 'font-semibold text-neutral-900' : 'text-neutral-400' }}">
                                    {{ $fila['cabe'] ? number_format($fila['unidades'], 0, ',', '.') : '—' }}
                                </span>
                            </a>
                        @endforeach
                        <p class="px-1 pt-0.5 text-[11px] leading-snug text-neutral-500">
                            Unidades que entran en cada uno, de mayor a menor. Tocá uno para cambiar.
                        </p>
                    </div>
                @endif

                @if (! empty($escena['pallet']))
                    {{-- Armar el pallet en el piso y SUBIRLO: el visor arranca con el
                         pallet al costado y el camión vacío. El JS le alterna el texto
                         (↑ Subir / ↓ Bajar), por eso queda como botón de texto plano. --}}
                    <button type="button" id="carga3dSubir"
                            class="mb-1 w-full rounded-lg bg-neutral-800 px-2 py-1.5 font-semibold text-white transition hover:bg-neutral-900">↑ Subir al camión</button>
                @endif
                {{-- − [caja] + : los pasos van GRANDES (h-11, objetivo táctil) y en
                     el medio se puede ESCRIBIR la cantidad exacta (pedido del dueño
                     07-08: «dame la opción de agregar números para hacer más exacta
                     la carga»). Con solo + y − llegar a 137 eran 137 toques; ahora se
                     tipea. Los tres controles mueven el MISMO número, así que la caja
                     también refleja lo que hacen los botones y la animación. --}}
                <div class="mt-1 flex items-stretch gap-1">
                    <button type="button" id="carga3dQuita1" aria-label="Sacar (mantené apretado para sacar de a muchos)"
                            title="Sacar · mantené apretado"
                            class="{{ $btn }} h-11 w-11 shrink-0 text-lg font-semibold">−</button>
                    <input type="number" id="carga3dCantidad" min="0" max="{{ $escena['tope'] }}" step="1"
                           inputmode="numeric" aria-label="Cantidad cargada"
                           class="h-11 w-full min-w-0 rounded-lg border-neutral-300 text-center text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    <button type="button" id="carga3dSuma1" aria-label="Agregar (mantené apretado para agregar de a muchos)"
                            title="Agregar · mantené apretado"
                            class="{{ $btn }} h-11 w-11 shrink-0 text-lg font-semibold">+</button>
                    {{-- El contador va PEGADO al stepper y no en una línea propia: son el
                         mismo número (los tres controles mueven `cant`), y separarlos
                         costaba 24px de alto que ahora se lleva la lista de la carga —
                         el «todo en una pantalla» del 21-08 se paga con estos recortes.

                         Con eso desapareció el `<span id="carga3dN">`, que mostraba el
                         mismo número en grande. `carga3d.js` lo sigue buscando con un
                         `if (n)` alrededor, así que ese update quedó como no-op A
                         PROPÓSITO: el número vive en el campo del stepper, que el JS
                         sincroniza igual (candado `test_los_tres_controles_de_cantidad…`).
                         No es código muerto olvidado; es la guarda haciendo su trabajo. --}}
                    <span class="flex shrink-0 flex-col justify-center pl-0.5 leading-none text-neutral-500">
                        <span class="text-[11px]">de {{ $escena['tope'] }}</span>
                        <span class="text-[10px] text-neutral-400">bultos</span>
                    </span>
                </div>
                {{-- Barra deslizante para la cantidad (pedido del dueño 07-08 mirando
                     el pallet cargado de EasyCargo). Es un TERCER control del MISMO
                     número, no un modo aparte: arrastrar da el barrido rápido y la
                     sensación de «llenar», el campo da el número exacto y los pasos
                     ajustan de a uno. Se deshabilita con tope 0 — pasa de verdad
                     cuando el producto no entra en el pallet (la bolsa mide 130 y el
                     pallet 120), y una barra que no puede moverse confunde. --}}
                {{-- ▶, barra, Todo y Vaciar en UNA fila. El ▶ pasa a ícono: a diferencia
                     de los rótulos (donde el dueño preguntó «¿qué hacen?» porque con el
                     camión vacío no se ve nada), su efecto es inmediato y evidente — el
                     camión se llena solo. Los cuatro siguen siendo controles del MISMO
                     número, que es la doctrina §4.1nonies-bis. --}}
                <div class="mt-2 flex items-center gap-1.5">
                    <button type="button" id="carga3dPlay" title="Cargar de a poco, para ver en qué orden va la carga"
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white transition hover:bg-brand-700">
                        <x-icon.play class="h-3 w-3" /><span class="sr-only">Cargar de a poco</span>
                    </button>
                    <input type="range" id="carga3dBarra" min="0" max="{{ max(1, $escena['tope']) }}" step="1" value="0"
                           @disabled(($escena['tope'] ?? 0) < 1)
                           aria-label="Cantidad cargada"
                           class="h-1.5 min-w-0 flex-1 cursor-pointer appearance-none rounded-full bg-neutral-200 accent-brand-600 disabled:cursor-not-allowed disabled:opacity-40">
                    <button type="button" id="carga3dTodo" class="{{ $btn }} shrink-0 py-1 text-[11px]">Todo</button>
                    <button type="button" id="carga3dVaciar" class="{{ $btn }} shrink-0 py-1 text-[11px]">Vaciar</button>
                </div>

                {{-- APROVECHAR EL ESPACIO QUE SOBRA. Recalcula en el servidor (no es
                     un control del lienzo): deja que el motor GIRE el bulto en las
                     regiones sobrantes, que es lo que se hace a mano — el grueso
                     acostado y, en la franja de la puerta, las bolsas paradas y
                     cruzadas. Solo aplica a la carga mixta, que es donde el motor
                     reparte el piso en regiones. --}}
                @if (! $publico && ($mixta ?? null) !== null)
                    <a href="{{ request()->fullUrlWithQuery(['aprovechar' => ($aprovechar ?? false) ? null : 1]) }}"
                       @class([
                           'mt-2 flex items-center justify-between gap-2 rounded-lg border px-2 py-1.5 transition',
                           'border-brand-300 bg-brand-50 font-semibold text-brand-700' => $aprovechar ?? false,
                           'border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50' => ! ($aprovechar ?? false),
                       ])
                       aria-pressed="{{ ($aprovechar ?? false) ? 'true' : 'false' }}">
                        <span>Usar todo el espacio</span>
                        <span class="shrink-0 text-[11px]">{{ ($aprovechar ?? false) ? 'ON' : 'OFF' }}</span>
                    </a>
                    <p class="px-1 pt-1 text-[11px] leading-snug text-neutral-500">
                        Gira los bultos en lo que sobra: la franja de la puerta y el costado.
                    </p>
                @endif

            </div>

            {{-- ═══ LA CARGA, AGRUPADA POR PARADA ═══
                 Reemplaza al bloque «Cubicaje» compacto, que decía cargadas/pedidas y
                 nada más. Pedido del dueño 21-08 sobre la foto de EasyCargo: «separado
                 por colores y separado en grupo, con el detalle de cada carga, cantidad
                 y espacio que ocupa». El porqué de cada decisión —el orden de carga, por
                 qué el grupo NO lleva color propio— vive en el partial. --}}
            @if (! $publico && ($mixta ?? null) !== null)
                <div class="flex min-h-0 flex-1 flex-col border-t border-neutral-200">
                    <p class="{{ $titulo }} shrink-0 px-2">
                        La carga
                        <span class="font-normal normal-case tracking-normal text-neutral-400">
                            · {{ count($mixta['lineas']) }} {{ \Illuminate\Support\Str::plural('producto', count($mixta['lineas'])) }}
                        </span>
                    </p>
                    @include('admin.carga._lista-panel')
                </div>
            @endif
            </div>

            {{-- Las hojas y el pie navegan hacia adentro de la app, así que no van en
                 el link compartido (ver la nota de `$publico` arriba). --}}
            @if (! $publico)

            {{-- HOJA «Compartir»: reemplaza al cuerpo mientras está abierta. Acá las
                 ayudas van completas y a la vista — la hoja es transitoria y tiene
                 espacio de sobra. --}}
            <div x-show="hoja === 'compartir'" x-cloak class="min-h-0 flex-1 overflow-y-auto p-2">
                <button type="button" @click="hoja = null"
                        class="mb-1 flex w-full items-center gap-1.5 rounded-lg px-1.5 py-1.5 text-left font-semibold text-neutral-700 transition hover:bg-neutral-100"
                        title="Volver a la carga">
                    <span class="inline-flex rotate-180"><x-icon.chevron-right class="h-3.5 w-3.5 text-neutral-400" /></span>
                    Compartir
                </button>
                {{-- DESCARGAR: el plan de carga como .xlsx. El enlace arrastra la query
                     actual entera, así que baja EXACTAMENTE lo que se está mirando —
                     camión, producto, estiba, apilado y las líneas de la carga mixta. Si
                     armara su propia URL, la planilla empezaría a diferir de la pantalla,
                     que es el defecto clásico de este tipo de botón. --}}
                <a href="{{ route('admin.carga.excel', request()->query()) }}"
                   class="{{ $btn }} flex w-full items-center justify-center gap-1.5">
                    Plan de carga (Excel)
                </a>
                <p class="px-1 pt-1 text-[11px] leading-snug text-neutral-500">
                    Incluye el orden de carga, del fondo hacia la puerta.
                </p>

                {{-- LINK COMPARTIBLE: la URL firmada se genera en el servidor y se
                     copia al portapapeles. No se muestra escrita porque es larga
                     (lleva el escenario entero) y nadie la va a tipear. --}}
                @php
                    $linkCompartir = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                        'publico.plan-carga',
                        now()->addDays(\App\Http\Controllers\Publico\PlanCargaPublicoController::DIAS_VIGENCIA),
                        request()->query(),
                    );
                @endphp
                <button type="button" class="{{ $btn }} mt-2 w-full"
                        x-data="{ copiado: false }"
                        @click="navigator.clipboard.writeText(@js($linkCompartir)).then(() => {
                                    copiado = true; setTimeout(() => copiado = false, 2500);
                                })">
                    <span x-show="! copiado">Copiar link para compartir</span>
                    <span x-show="copiado" x-cloak class="font-semibold text-brand-700">¡Copiado!</span>
                </button>
                <p class="px-1 pt-1 text-[11px] leading-snug text-neutral-500">
                    Abre el 3D sin cuenta. Vence en
                    {{ \App\Http\Controllers\Publico\PlanCargaPublicoController::DIAS_VIGENCIA }} días.
                </p>
            </div>

            {{-- HOJA «Herramientas»: lo que se usa de a poco (acomodar, cubicar,
                 pallet, importar), cada cosa con su ayuda completa a la vista. Al
                 activar una herramienta la hoja se cierra sola: el resultado aparece
                 abajo del lienzo y la mirada se va para allá. --}}
            <div x-show="hoja === 'herramientas'" x-cloak class="min-h-0 flex-1 space-y-2 overflow-y-auto p-2">
                <button type="button" @click="hoja = null"
                        class="flex w-full items-center gap-1.5 rounded-lg px-1.5 py-1.5 text-left font-semibold text-neutral-700 transition hover:bg-neutral-100"
                        title="Volver a la carga">
                    <span class="inline-flex rotate-180"><x-icon.chevron-right class="h-3.5 w-3.5 text-neutral-400" /></span>
                    Herramientas
                </button>

                {{-- ACOMODAR A MANO: mover y girar los bloques en una vista de planta.
                     Ver `_acomodo.blade.php` para el porqué y para lo que
                     deliberadamente no hace. --}}
                @if (! empty($acomodo['piezas']))
                    <div>
                        <p class="{{ $titulo }}">Acomodar</p>
                        <button type="button" @click="tablero = ! tablero; hoja = null"
                                :aria-pressed="tablero ? 'true' : 'false'"
                                class="{{ $btn }} w-full"
                                x-text="tablero ? 'Cerrar el tablero' : 'Mover y girar bloques'"></button>
                        <p class="px-1 pt-1 text-[11px] leading-snug text-neutral-500">
                            Vista de planta. El cálculo no verifica lo que se acomoda a mano.
                        </p>
                    </div>
                @endif

                {{-- CUBICAR SE FUE DE ACÁ: es una PESTAÑA (dueño 21-08, «quiero dejar como
                     una de las opciones principales cubicar»). Estaba a dos clics dentro de
                     esta hoja, o sea que había que saber que existía para encontrarlo. No se
                     deja además el botón: dos entradas al mismo panel serían dos estados
                     independientes —el `cubicar` de este menú y la pestaña— y el que se abre
                     de un lado no se cierra del otro. Ver `_cubicar.blade.php`. --}}

                {{-- PALLET (pedido del dueño 06-08: «la opción de agregar un pallet esté
                     ahí también, con el estándar y el otro»). Los dos tipos son enlaces
                     al modo Sobre pallet conservando camión y producto. --}}
                <div>
                    <p class="{{ $titulo }}">Pallet</p>
                    <div class="grid gap-1">
                        @foreach (\App\Services\Carga\PalletSimulado::TIPOS as $clavePallet => $tipoPallet)
                            <a href="{{ route('admin.carga.index', array_filter([
                                    'sobre_pallet' => 1,
                                    'pallet_tipo' => $clavePallet,
                                    'camion_id' => $camion?->id,
                                    'tipo_bulto_id' => $bulto?->id,
                                ])) }}"
                               class="{{ $btn }} block text-center">{{ $tipoPallet['nombre'] }}</a>
                        @endforeach
                    </div>
                </div>

                {{-- IMPORTAR DE EXCEL (pedido del dueño 06-08). Ver `_importar.blade.php`:
                     se PEGA lo copiado de la planilla, sin subir archivo. --}}
                <div>
                    <p class="{{ $titulo }}">Traer carga</p>
                    <button type="button" id="carga3dImportar" @click="$dispatch('abrir-importar'); hoja = null"
                            class="{{ $btn }} w-full">Importar de Excel</button>
                </div>
            </div>

            {{-- PIE: los dos lanzadores, quietos y siempre en el mismo lugar. Tocar el
                 de la hoja abierta la cierra (toggle). --}}
            <div class="shrink-0 border-t border-neutral-200 bg-white p-1">
                <button type="button" @click="hoja = hoja === 'compartir' ? null : 'compartir'"
                        :class="hoja === 'compartir' ? 'bg-neutral-100' : ''"
                        class="flex w-full items-center justify-between rounded-lg px-2 py-2 font-medium text-neutral-700 transition hover:bg-neutral-100">
                    Compartir <x-icon.chevron-right class="h-3.5 w-3.5 text-neutral-400" />
                </button>
                <button type="button" @click="hoja = hoja === 'herramientas' ? null : 'herramientas'"
                        :class="hoja === 'herramientas' ? 'bg-neutral-100' : ''"
                        class="flex w-full items-center justify-between rounded-lg px-2 py-2 font-medium text-neutral-700 transition hover:bg-neutral-100">
                    Herramientas <x-icon.chevron-right class="h-3.5 w-3.5 text-neutral-400" />
                </button>
            </div>

            @endif {{-- fin de lo que no va en el link compartido (hojas y pie) --}}
        </aside>

        {{-- ═══ EL LIENZO ═══ --}}
        <div class="relative min-w-0 flex-1">
            {{-- El ALTO lo fija el CSS con `aspect-ratio`, no el atributo del lienzo: el
                 visor ajusta el mapa de bits al recuadro real (para que no salga borroso
                 en un monitor ancho) y si el alto dependiera del atributo, tocarlo movería
                 el recuadro y el recuadro volvería a mover el mapa de bits.

                 `max-h`: el visor le da al recuadro la forma del camión, y un camión corto
                 pide un recuadro casi cuadrado; en un monitor ancho serían mil píxeles de
                 alto. `min-h`: en un celular angosto la proporción del camión dejaría un
                 recuadro de 230 px y el camión quedaría diminuto justo donde no hay zoom
                 para compensar. --}}
            <canvas id="carga3d" width="1240" height="660" style="aspect-ratio: 1240 / 660"
                    class="block max-h-[80vh] min-h-[18rem] w-full cursor-grab"></canvas>

            {{-- Solo la AYUDA de manejo. El nombre del camión y el piso libre estaban
                 también acá y volvían a aparecer en la franja de datos: ahora que la
                 franja vive dentro de este mismo recuadro (abajo), repetirlos era
                 justo el exceso de texto que el dueño pidió recortar (10-08). --}}
            <div class="absolute left-3 top-3 flex items-center gap-2">
                {{-- El ☰ abre el menú cuando está cerrado (siempre, en celular al arrancar). --}}
                <button type="button" x-show="!menu" @click="menu = true"
                        class="rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50"
                        title="Abrir el menú de herramientas">☰ Menú</button>
                {{-- Los tres controles del mouse, dichos donde se usan (pedido del dueño
                     12-08-2026, con los controles de EasyCargo al lado). El desplazamiento
                     con el botón derecho es el único que no se descubre solo: girar se
                     prueba arrastrando y la rueda es un reflejo, pero a nadie se le ocurre
                     apretar el derecho sobre un dibujo si nada se lo dice. --}}
                <span class="text-xs font-medium text-neutral-500">
                    Arrastrá para girar<span class="hidden lg:inline">, botón derecho para mover, rueda para acercar</span>
                </span>
            </div>
        </div>
    </div>

    {{-- ═══ EL AVISO DE ACOMODO A MANO ═══
         Va SIEMPRE que haya un acomodo aplicado, también en el link compartido y en el
         Excel. Es la contraparte de haber permitido mover bultos: quien recibe el plan
         tiene que saber que esas posiciones las puso una persona y no el cálculo, o el
         dibujo se lee como una promesa verificada que nadie verificó. Las cantidades sí
         siguen siendo las del motor — acomodar no descubre lugar nuevo. --}}
    @if ($acomodo['activo'] ?? false)
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 border-t border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            <span class="font-semibold">Acomodo a mano</span>
            <span class="text-amber-800">El cálculo no verificó estas posiciones. Las cantidades no cambian.</span>
            @if ($acomodo['choques'] !== [])
                <span class="font-semibold text-red-700">
                    {{ count($acomodo['choques']) }} par(es) de bloques se pisan.
                </span>
            @endif
            @if ($acomodo['fuera'] !== [])
                <span class="font-semibold text-red-700">
                    {{ count($acomodo['fuera']) }} bloque(s) sobresalen de la caja.
                </span>
            @endif
            {{-- Lo que se acomodó a mano y NO se pudo conservar: el bloque que hoy ocupa
                 ese lugar es de otro producto, así que volvió a donde lo puso el cálculo.
                 Sin decirlo, un bloque que aparece movido de vuelta se lee como que el
                 acomodo no se guardó — y lo que pasó es lo contrario: se guardó, y se
                 respetó de quién era cada lugar (ver `AcomodoManual`). --}}
            @if (($acomodo['ignorados'] ?? 0) > 0)
                <span class="text-amber-800">
                    {{ $acomodo['ignorados'] }} bloque(s) volvieron al lugar del cálculo: cambió el producto que iba ahí.
                </span>
            @endif
        </div>
    @endif

    {{-- Un acomodo armado para OTRO resultado no se aplica torcido: se descarta entero y
         se dice. Aplicar las primeras posiciones sobre productos que ahora son distintos
         sería mover carga ajena en silencio (ver `AcomodoManual`). --}}
    @if ($acomodo['descartado'] ?? false)
        <div class="border-t border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            <span class="font-semibold">Se descartó el acomodo a mano:</span>
            la carga cambió y las posiciones guardadas ya no corresponden a estos bloques.
        </div>
    @endif

    {{-- LOS NÚMEROS DE LA CARGA, dentro del recuadro y al pie del dibujo (pedido del dueño
         12-08: «acá lo quiero abajo, dentro de donde está el camión, para ahorrar espacio»).
         Era una tarjeta suelta debajo del visor.

         Va DESPUÉS del lienzo y antes de los tableros: se lee el camión, después sus
         números, y al final las herramientas que lo modifican. --}}
    @if (($mixta ?? null) !== null)
        <div class="border-t border-neutral-200 bg-neutral-50/70 px-4 py-3">
            @include('admin.carga._numeros', ['p' => $mixta['peso']])
        </div>
    @endif

    @if (! $publico)
        @include('admin.carga._acomodo', ['escena' => $escena])
        {{-- El cubicaje SALIÓ de acá: subió a pestaña (21-08) y vive al pie de la página,
             donde van los formularios de las pestañas. Estaba adentro del recuadro porque
             era una herramienta del menú, y dejar el panel acá con la pestaña arriba
             pondría un formulario dentro del dibujo y otro debajo. El del acomodo sí se
             queda: se opera MIRANDO el camión, así que tiene que estar pegado a él. --}}
    @endif

    {{-- La franja del camión y el veredicto viven ARRIBA, antes del lienzo (12-08).
         Acá abajo no queda nada: lo último del recuadro es el tablero de acomodo. --}}
</div>
