{{--
    El visor 3D y sus controles. Requiere: $escena.

    Estaba COPIADO en los dos modos de la pantalla (cupo máximo y carga mixta),
    idéntico. Al sumar los controles de zoom habrían quedado dos copias que
    driftean, así que se extrajo acá: un solo lugar donde agregar botones.

    ZOOM SOLO EN ESCRITORIO (pedido del dueño 05-08-2026: «no lo quiero para
    celular, no quiero que se quede pegada o se ponga lento»). Se cumple por
    construcción, no por un `if`: el zoom entra por la RUEDA del mouse (que un
    táctil no emite) y los botones + / − viven en un contenedor `hidden lg:flex`.
    NO se registran handlers de touch ni de pinza. En celular sigue andando el
    arrastre para girar, que ya estaba.
--}}
{{-- Sin `lg:col-span-2`: el visor ya no vive en una grilla, va a todo el ancho y arriba
     de todo (pedido del dueño 05-08). Dentro de la grilla el recuadro se ESTIRABA a la
     altura de la columna de datos y el lienzo, con su proporción fija, quedaba pegado
     arriba dejando un hueco blanco abajo.

     El lienzo es más BAJO en proporción (1240×560 en vez de 1240×720) justamente porque
     ahora es el doble de ancho: con 720 de alto ocuparía media pantalla de alto y habría
     que scrollear para ver los datos. --}}
<div class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
    {{-- El ALTO lo fija el CSS con `aspect-ratio`, no el atributo del lienzo: el visor
         ajusta el mapa de bits al recuadro real (para que no salga borroso en un monitor
         ancho) y si el alto dependiera del atributo, tocarlo movería el recuadro y el
         recuadro volvería a mover el mapa de bits.

         La proporción es 1240/660 = 1,88, medida sobre el dibujo: a 3/4 el camión ocupa
         una silueta de ~1,85 de proporción, así que con el 2,21 anterior sobraba ancho y
         faltaba alto — el camión se veía chico en un recuadro apaisado. Los atributos
         quedan como respaldo si el JS no corre. --}}
    {{-- `max-h`: el visor le da al recuadro la forma del camión, y un camión corto pide
         un recuadro casi cuadrado. En un monitor ancho eso serían mil pixeles de alto.
         El tope está en 80vh y no más abajo porque el pedido del dueño fue justamente
         que el camión se viera MÁS GRANDE (05-08); con 68vh el recuadro volvía a quedar
         apaisado en una pantalla de notebook y el camión no llenaba el ancho. --}}
    {{-- `min-h`: en un celular angosto la proporción del camión deja un recuadro de 230
         px y el camión queda diminuto justo donde no hay zoom para compensar. --}}
    <canvas id="carga3d" width="1240" height="660" style="aspect-ratio: 1240 / 660"
            class="block max-h-[80vh] min-h-[18rem] w-full cursor-grab"></canvas>

    <div class="absolute left-4 top-3 text-xs font-medium text-neutral-500">
        {{ $escena['vehiculo']['nombre'] }} · arrastrá para girar<span class="hidden lg:inline">, rueda para acercar</span>
        @if ($escena['libre_m'] > 0.05)
            · <span class="text-neutral-400">quedan {{ number_format($escena['libre_m'], 2, ',', '.') }} m libres en la puerta</span>
        @endif
    </div>

    {{-- VISTAS FIJAS. Es el panel «Views» de EasyCargo, que el dueño señaló como lo que
         más le sirve de esa app (05-08-2026). Van en celular también: sin zoom táctil,
         cambiar de vista es la única forma de mirar la carga desde otro lado sin pelear
         con el arrastre. --}}
    <div class="absolute left-4 top-9 flex flex-wrap items-center gap-1.5 text-xs">
        @foreach ([
            'carga3dVista3d' => '3D',
            'carga3dVistacostado' => 'Costado',
            'carga3dVistaplanta' => 'Planta',
            'carga3dVistapuerta' => 'Puerta',
        ] as $id => $texto)
            <button type="button" id="{{ $id }}" aria-pressed="false"
                    class="rounded-lg border border-neutral-300 bg-white px-2 py-1 font-medium text-neutral-700 transition hover:bg-neutral-50">{{ $texto }}</button>
        @endforeach
    </div>

    {{-- EL CUBICAJE, en la esquina: el formato del panel izquierdo de EasyCargo, que el
         dueño pidió expresamente (06-08-2026). Por producto: su letra sobre su color,
         cuántas van de cuántas y un punto verde o rojo.

         SÍ, repite el detalle que está más abajo, y es a propósito: el valor está en
         tenerlo AL LADO del camión, sin levantar la vista del dibujo para saber qué es
         cada bloque. Abajo sigue el detalle completo, con el motivo de lo que no entró.

         Desde `sm`: en un celular estos 13 rem se comerían media pantalla del camión, y
         ahí el detalle de abajo queda a un scroll. --}}
    @if ($mixta !== null)
        <div class="absolute left-4 top-[4.4rem] hidden w-52 rounded-xl border border-neutral-200 bg-white/90 p-1.5 text-xs shadow-sm backdrop-blur sm:block">
            <p class="px-1 pb-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-400">La carga</p>
            @foreach ($mixta['lineas'] as $i => $fila)
                @php $rgbPanel = \App\Http\Controllers\Admin\SimuladorCargaController::COLORES_3D[$i % count(\App\Http\Controllers\Admin\SimuladorCargaController::COLORES_3D)]; @endphp
                <div class="flex items-center gap-1.5 px-1 py-0.5">
                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded text-[10px] font-bold text-white"
                          style="background: rgb({{ implode(',', $rgbPanel) }})">{{ \App\Http\Controllers\Admin\SimuladorCargaController::letra($i) }}</span>
                    <span class="flex-1 truncate text-neutral-600" title="{{ $fila['modelo']->nombre }}">{{ $fila['modelo']->nombre }}</span>
                    <span class="shrink-0 font-medium tabular-nums text-neutral-900">{{ number_format($fila['cargadas_unidades'], 0, ',', '.') }}/{{ number_format($fila['pedidas_unidades'], 0, ',', '.') }}</span>
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $fila['motivo'] === null ? 'bg-brand-600' : 'bg-red-500' }}"
                          title="{{ $fila['motivo'] === null ? 'Entra completo' : 'Queda carga afuera' }}"></span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Cuánto se ve cargado. «▶» reproduce la estiba de a poco (para mirar en qué
         ORDEN va la carga) y los pasos permiten armarla a mano: de a uno para el
         detalle, de a 5 o de a 10 para avanzar, «Todo» y «Vaciar» para los extremos
         (pedido del dueño 05-08). Envuelve en pantalla angosta. --}}
    <div class="absolute bottom-3 left-4 flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" id="carga3dPlay"
                class="rounded-lg bg-brand-600 px-2.5 py-1 font-semibold text-white transition hover:bg-brand-700">▶ Cargar</button>
        <span class="mx-1 tabular-nums font-medium text-neutral-600"><span id="carga3dN">0</span> de {{ $escena['tope'] }}</span>
        @foreach ([
            'carga3dVaciar' => 'Vaciar',
            'carga3dQuita1' => '−1',
            'carga3dSuma1' => '+1',
            'carga3dSuma5' => '+5',
            'carga3dSuma10' => '+10',
            'carga3dTodo' => 'Todo',
        ] as $id => $texto)
            <button type="button" id="{{ $id }}"
                    class="rounded-lg border border-neutral-300 bg-white px-2 py-1 font-medium text-neutral-700 transition hover:bg-neutral-50">{{ $texto }}</button>
        @endforeach
    </div>

    {{-- Nombres de los productos sobre su bloque, y la LETRA de cada producto escrita
         sobre sus cajas (el «cajas escritas con códigos» de EasyCargo). Los dos se
         pueden apagar: con un solo producto no distinguen nada y tapan carga. --}}
    <div class="absolute bottom-3 right-4 flex items-center gap-1.5 text-xs">
        <button type="button" id="carga3dCodigos" aria-pressed="true"
                class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1 font-medium text-neutral-700 transition hover:bg-neutral-50">Códigos</button>
        <button type="button" id="carga3dNombres" aria-pressed="true"
                class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1 font-medium text-neutral-700 transition hover:bg-neutral-50">Nombres</button>
    </div>

    {{-- Zoom: escritorio únicamente (ver el comentario de arriba). --}}
    <div class="absolute right-4 top-3 hidden items-center gap-1.5 text-xs lg:flex">
        <button type="button" id="carga3dMenos" aria-label="Alejar"
                class="h-7 w-7 rounded-lg border border-neutral-300 bg-white font-semibold text-neutral-700 transition hover:bg-neutral-50">−</button>
        <button type="button" id="carga3dMas" aria-label="Acercar"
                class="h-7 w-7 rounded-lg border border-neutral-300 bg-white font-semibold text-neutral-700 transition hover:bg-neutral-50">+</button>
        <button type="button" id="carga3dReset"
                class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1 font-medium text-neutral-700 transition hover:bg-neutral-50">Reiniciar</button>
    </div>
</div>
