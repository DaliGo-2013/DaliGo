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
    // Clases compartidas por los botones del menú: se repiten en cinco grupos y
    // escribirlas a mano dejaba unos con `py-1` y otros con `py-1.5`.
    $btn = 'rounded-lg border border-neutral-300 bg-white px-2 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50';
    $titulo = 'px-1 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-neutral-400';
@endphp

<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm"
     x-data="{ menu: window.innerWidth >= 640 }">
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
            </details>

            {{-- Cuánto se ve cargado. «▶» reproduce la estiba de a poco (para mirar en
                 qué ORDEN va la carga) y los pasos permiten armarla a mano — para los
                 dos lados: −10/−5/−1 para sacar y +1/+5/+10 para poner (los de restar
                 los pidió el dueño 06-08). --}}
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
                    <div class="mt-1 grid grid-cols-3 gap-1">
                        @foreach ([
                            'carga3dQuita10' => '−10',
                            'carga3dQuita5' => '−5',
                            'carga3dQuita1' => '−1',
                            'carga3dSuma1' => '+1',
                            'carga3dSuma5' => '+5',
                            'carga3dSuma10' => '+10',
                            'carga3dTodo' => 'Todo',
                            'carga3dVaciar' => 'Vaciar',
                        ] as $id => $texto)
                            <button type="button" id="{{ $id }}" class="{{ $btn }}">{{ $texto }}</button>
                        @endforeach
                    </div>
                </div>
            </details>

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

            {{-- IMPORTAR DE EXCEL (pedido del dueño 06-08). Ver `_importar.blade.php`:
                 se PEGA lo copiado de la planilla, sin subir archivo. --}}
            <details class="group">
                <summary class="{{ $titulo }} flex cursor-pointer select-none list-none items-center justify-between rounded hover:text-neutral-600 [&::-webkit-details-marker]:hidden">
                    Traer carga <span class="transition group-open:rotate-180">▾</span>
                </summary>
                <button type="button" id="carga3dImportar" @click="$dispatch('abrir-importar')"
                        class="{{ $btn }} mt-1 w-full">Importar de Excel</button>
            </details>
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

            <div class="absolute left-3 top-3 flex items-center gap-2">
                {{-- El ☰ abre el menú cuando está cerrado (siempre, en celular al arrancar). --}}
                <button type="button" x-show="!menu" @click="menu = true"
                        class="rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50"
                        title="Abrir el menú de herramientas">☰ Menú</button>
                <span class="text-xs font-medium text-neutral-500">
                    {{ $escena['vehiculo']['nombre'] }} · arrastrá para girar<span class="hidden lg:inline">, rueda para acercar</span>
                    @if ($escena['libre_m'] > 0.05)
                        · <span class="text-neutral-400">quedan {{ number_format($escena['libre_m'], 2, ',', '.') }} m libres en la puerta</span>
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>
