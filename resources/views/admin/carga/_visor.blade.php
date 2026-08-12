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
    $titulo = 'px-1 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-neutral-400';
    // El acomodo a mano: si viene uno aplicado, el tablero arranca ABIERTO. Llegar a un
    // camión acomodado y tener que buscar dónde se toca eso es la peor versión.
    $acomodo = $escena['acomodo'] ?? null;
@endphp

<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm"
     x-data="{ menu: window.innerWidth >= 640, tablero: {{ ($acomodo['activo'] ?? false) ? 'true' : 'false' }} }">
    <div class="flex items-stretch">

        {{-- ═══ EL MENÚ ═══ --}}
        <aside x-show="menu" x-cloak
               class="w-56 shrink-0 space-y-1 overflow-y-auto border-r border-neutral-200 bg-neutral-50/70 p-2 text-xs"
               style="max-height: 80vh">

            <div class="flex items-center justify-between px-1">
                <span class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Herramientas</span>
                <button type="button" @click="menu = false" class="rounded p-0.5 text-neutral-400 transition hover:bg-neutral-200 hover:text-neutral-700"
                        title="Cerrar el menú" aria-label="Cerrar el menú">✕</button>
            </div>

            {{-- CADA SECCIÓN ES UN DESPLEGABLE (pedido del dueño 06-08: «que se puedan
                 desplegar como dropdown»). Son <details> nativos —el estado abierto o
                 cerrado no necesita JS ni Alpine— y arrancan abiertos Vista y Cargar,
                 que son los que se tocan todo el tiempo; el resto cerrado. --}}

            {{-- Vistas fijas: el panel «Views» de EasyCargo. Van en celular también —
                 sin zoom táctil, cambiar de vista es la única forma de mirar la carga
                 desde otro lado sin pelear con el arrastre. --}}
            <details open class="group">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Vista <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <div class="grid grid-cols-2 gap-1 pt-1">
                    @foreach ([
                        'carga3dVista3d' => '3D',
                        'carga3dVistacostado' => 'Costado',
                        'carga3dVistaplanta' => 'Planta',
                        'carga3dVistapuerta' => 'Puerta',
                    ] as $id => $texto)
                        <button type="button" id="{{ $id }}" aria-pressed="false" class="{{ $btn }}">{{ $texto }}</button>
                    @endforeach
                </div>
            </details>

            {{-- Zoom: escritorio únicamente (ver el encabezado). --}}
            <details class="group hidden lg:block">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Acercar <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <div class="flex items-center gap-1 pt-1">
                    <button type="button" id="carga3dMenos" aria-label="Alejar" class="{{ $btn }} w-8 text-center">−</button>
                    <button type="button" id="carga3dMas" aria-label="Acercar" class="{{ $btn }} w-8 text-center">+</button>
                    <button type="button" id="carga3dReset" class="{{ $btn }} flex-1">Reiniciar</button>
                </div>
                <p class="px-1 pt-1 text-[11px] leading-snug text-neutral-500">
                    Con el <span class="font-medium text-neutral-700">botón derecho</span> apretado se
                    recorre la carga. «Reiniciar» vuelve al encuadre de siempre.
                </p>
            </details>

            {{-- Cuánto se ve cargado. «▶» reproduce la estiba de a poco (para mirar en
                 qué ORDEN va la carga) y los pasos permiten armarla a mano.

                 DOS botones y no seis (pedido del dueño 07-08: «es mucho número»).
                 Reemplazan a −10/−5/−1/+1/+5/+10, que existían desde el 06-08 porque
                 con un paso de a uno bajar de a mucho era repetir el botón veinte
                 veces. Ese problema NO volvió: ahora + y − se pueden MANTENER
                 APRETADOS y aceleran solos, así que se cubre el mismo recorrido con
                 un tercio de los controles. Sin la repetición, esto sería un
                 retroceso — no quitarla. --}}
            <details open class="group">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Cargar <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <div class="pt-1">
                    <div class="px-1 pb-1 tabular-nums font-medium text-neutral-600">
                        <span id="carga3dN">0</span> de {{ $escena['tope'] }}
                    </div>
                    @if (! empty($escena['pallet']))
                        {{-- Armar el pallet en el piso y SUBIRLO: el visor arranca con el
                             pallet al costado y el camión vacío. --}}
                        <button type="button" id="carga3dSubir"
                                class="mb-1 w-full rounded-lg bg-neutral-800 px-2 py-1.5 font-semibold text-white transition hover:bg-neutral-900">↑ Subir al camión</button>
                    @endif
                    <button type="button" id="carga3dPlay"
                            class="w-full rounded-lg bg-brand-600 px-2 py-1.5 font-semibold text-white transition hover:bg-brand-700">▶ Cargar de a poco</button>
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
                    </div>
                    {{-- Barra deslizante para la cantidad (pedido del dueño 07-08 mirando
                         el pallet cargado de EasyCargo). Es un TERCER control del MISMO
                         número, no un modo aparte: arrastrar da el barrido rápido y la
                         sensación de «llenar», el campo da el número exacto y los pasos
                         ajustan de a uno. Se deshabilita con tope 0 — pasa de verdad
                         cuando el producto no entra en el pallet (la bolsa mide 130 y el
                         pallet 120), y una barra que no puede moverse confunde. --}}
                    <input type="range" id="carga3dBarra" min="0" max="{{ max(1, $escena['tope']) }}" step="1" value="0"
                           @disabled(($escena['tope'] ?? 0) < 1)
                           aria-label="Cantidad cargada"
                           class="mt-2 h-1.5 w-full cursor-pointer appearance-none rounded-full bg-neutral-200 accent-brand-600 disabled:cursor-not-allowed disabled:opacity-40">
                    <div class="mt-2 grid grid-cols-2 gap-1">
                        <button type="button" id="carga3dTodo" class="{{ $btn }}">Todo</button>
                        <button type="button" id="carga3dVaciar" class="{{ $btn }}">Vaciar</button>
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
            </details>

            {{-- Todo lo que sigue navega hacia adentro de la app, así que no va en el
                 link compartido. Ver la nota de `$publico` arriba. --}}
            @if (! $publico)

            {{-- DESCARGAR: el plan de carga como .xlsx. El enlace arrastra la query
                 actual entera, así que baja EXACTAMENTE lo que se está mirando —
                 camión, producto, estiba, apilado y las líneas de la carga mixta. Si
                 armara su propia URL, la planilla empezaría a diferir de la pantalla,
                 que es el defecto clásico de este tipo de botón. --}}
            <details class="group">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Descargar <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <div class="pt-1">
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
            </details>

            {{-- ¿EN CUÁL CONVIENE? La misma pregunta que se está haciendo, resuelta
                 para toda la flota. Va en el menú y no suelta en la pantalla, como el
                 resto de los controles (doctrina del 06-08). Cada fila es un enlace
                 que cambia de camión conservando todo lo demás: producto, estiba,
                 apilado y las líneas de la carga mixta. --}}
            @if (! empty($comparativa))
                <details class="group">
                    <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                        Camiones <span class="transition group-open:rotate-180">▾</span>
                    </summary>
                    <div class="space-y-1 pt-1">
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
                </details>
            @endif

            @endif {{-- fin de lo que no va en el link compartido (Descargar · Camiones) --}}

            {{-- Nombres de los productos sobre su bloque, y la LETRA de cada producto
                 escrita sobre sus cajas. Los dos se pueden apagar: con un solo producto
                 no distinguen nada y tapan carga. --}}
            <details class="group">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Rótulos <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <div class="grid grid-cols-2 gap-1 pt-1">
                    <button type="button" id="carga3dCodigos" aria-pressed="true" class="{{ $btn }}">Códigos</button>
                    <button type="button" id="carga3dNombres" aria-pressed="true" class="{{ $btn }}">Nombres</button>
                </div>
            </details>

            @if (! $publico)

            {{-- PALLET, también desde el menú (pedido del dueño 06-08: «la opción de
                 agregar un pallet esté ahí también, con el estándar y el otro»). Los dos
                 tipos son enlaces al modo Sobre pallet conservando camión y producto. --}}
            <details class="group">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Pallet <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <div class="grid gap-1 pt-1">
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
            </details>

            {{-- EL CUBICAJE: el formato del panel izquierdo de EasyCargo. Por producto,
                 su letra sobre su color, cuántas van de cuántas y un punto verde o rojo.
                 Repite el detalle de más abajo A PROPÓSITO: el valor es no levantar la
                 vista del dibujo para saber qué es cada bloque. --}}
            @if ($mixta !== null)
                <details open class="group">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Cubicaje <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <div class="mt-1 rounded-lg border border-neutral-200 bg-white p-1">
                    @foreach ($mixta['lineas'] as $i => $fila)
                        @php $rgbPanel = $ctrl::COLORES_3D[$i % count($ctrl::COLORES_3D)]; @endphp
                        <div class="flex items-center gap-1.5 px-0.5 py-0.5">
                            <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded text-[10px] font-bold text-white"
                                  style="background: rgb({{ implode(',', $rgbPanel) }})">{{ $ctrl::letra($i) }}</span>
                            <span class="flex-1 truncate text-neutral-600" title="{{ $fila['modelo']->nombre }}">{{ $fila['modelo']->nombre }}</span>
                            <span class="shrink-0 font-medium tabular-nums text-neutral-900">{{ number_format($fila['cargadas_unidades'], 0, ',', '.') }}/{{ number_format($fila['pedidas_unidades'], 0, ',', '.') }}</span>
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $fila['motivo'] === null ? 'bg-brand-600' : 'bg-red-500' }}"
                                  title="{{ $fila['motivo'] === null ? 'Entra completo' : 'Queda carga afuera' }}"></span>
                        </div>
                    @endforeach
                </div>
                </details>
            @endif

            {{-- ACOMODAR A MANO: mover y girar los bloques en una vista de planta. Ver
                 `_acomodo.blade.php` para el porqué y para lo que deliberadamente no
                 hace. Va en el menú como todo lo demás (doctrina del 06-08) y no en el
                 link compartido: quien mira el plan no lo reacomoda. --}}
            @if (! empty($acomodo['piezas']))
                <details open class="group">
                    <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                        Acomodar <span class="transition group-open:rotate-180">▾</span>
                    </summary>
                    <button type="button" @click="tablero = ! tablero"
                            :aria-pressed="tablero ? 'true' : 'false'"
                            class="{{ $btn }} mt-1 w-full"
                            x-text="tablero ? 'Cerrar el tablero' : 'Mover y girar bloques'"></button>
                    <p class="px-1 pt-1 text-[11px] leading-snug text-neutral-500">
                        Vista de planta. El cálculo no verifica lo que se acomoda a mano.
                    </p>
                </details>
            @endif

            {{-- IMPORTAR DE EXCEL (pedido del dueño 06-08). Ver `_importar.blade.php`:
                 se PEGA lo copiado de la planilla, sin subir archivo. --}}
            <details class="group">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Traer carga <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <button type="button" id="carga3dImportar" @click="$dispatch('abrir-importar')"
                        class="{{ $btn }} mt-1 w-full">Importar de Excel</button>
            </details>

            @endif {{-- fin de lo que no va en el link compartido (Pallet · Traer carga) --}}
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

    @if (! $publico)
        @include('admin.carga._acomodo', ['escena' => $escena])
    @endif

    {{-- ═══ EL CAMIÓN EN NÚMEROS ═══
         Pedido del dueño (10-08, dibujado sobre la pantalla): «la descripción del
         camión la quiero adentro del cuadrado donde está el camión, para poder usar
         mejor el espacio». Era una tarjeta aparte debajo del visor: su propio borde,
         su propia sombra y el hueco entre las dos. Adentro se ahorran los tres, y los
         datos quedan pegados al dibujo que describen.

         Va como FRANJA AL PIE y no flotando sobre el lienzo: un panel encima taparía
         el camión, que es la doctrina del 06-08 anotada arriba en este mismo archivo.
         Con `bg-neutral-50/70` —el mismo fondo del menú lateral— se lee como parte del
         visor y no como una tarjeta de contenido metida adentro. --}}
    @if ($camion ?? null)
        <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1 border-t border-neutral-200 bg-neutral-50/70 px-4 py-2.5 text-sm">
            <span class="font-semibold text-neutral-900">{{ $camion->nombre }}</span>
            <span class="text-neutral-500">Medidas útiles
                <span class="font-medium tabular-nums text-neutral-900">{{ number_format($camion->largo_cm / 100, 2, ',', '.') }} × {{ number_format($camion->ancho_cm / 100, 2, ',', '.') }} × {{ number_format($camion->alto_cm / 100, 2, ',', '.') }} m</span>
                <span class="cursor-help text-neutral-300"
                      title="Medidas por DENTRO de la caja, no la ficha del fabricante: entre exterior e interior hay 10 a 20% de volumen, que es la diferencia entre que la carga entre o quede en el andén.">ⓘ</span>
            </span>
            <span class="text-neutral-500">Volumen
                <span class="font-medium tabular-nums text-neutral-900">{{ number_format($camion->volumenM3(), 1, ',', '.') }} m³</span>
            </span>
            <span class="text-neutral-500">Carga máxima
                @if ($camion->peso_max_kg)
                    <span class="font-medium tabular-nums text-neutral-900">{{ number_format($camion->peso_max_kg, 0, ',', '.') }} kg</span>
                @else
                    <span class="text-neutral-400">sin dato</span>
                @endif
            </span>
            @if ($camion->pasillo_cm > 0)
                <span class="text-neutral-500">Pasillo reservado
                    <span class="font-medium tabular-nums text-neutral-900">{{ $camion->pasillo_cm }} cm</span>
                </span>
            @endif
            {{-- El «Free meters» de EasyCargo: más accionable que el % de ocupación
                 para «¿le sumo algo más a este viaje?». --}}
            <span class="text-neutral-500">Piso libre en la puerta
                <span class="font-medium tabular-nums text-neutral-900">{{ number_format($escena['libre_m'], 2, ',', '.') }} m</span>
            </span>
        </div>
    @endif
</div>
