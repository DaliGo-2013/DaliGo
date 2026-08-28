{{--
    LA CARGA, DENTRO DEL PANEL Y AGRUPADA POR PARADA. Requiere: $mixta, $ctrl, $camion.

    Pedido del dueño (21-08-2026), eligiendo la opción B de un canvas de propuestas:
    «en el panel izquierdo como EasyCargo… separado por colores y separado en grupo, con
    el detalle de cada carga, cantidad y espacio que ocupa», y con una condición encima:
    «que permanezca todo en la misma interfaz sin tener que deslizar hacia abajo con una
    barra sino trabajar todo en una misma pantalla».

    ── QUÉ REEMPLAZA ──
    Esto ocupa el lugar del bloque «Cubicaje» compacto del panel, que decía cargadas/
    pedidas y nada más. Y absorbe lo que hasta ahora eran DOS secciones separadas debajo
    del camión, que decían lo mismo mirado distinto: «La carga, producto por producto»
    (plana, con letra y color) y «El reparto, parada por parada» (agrupada, sin cantidad
    ni espacio). Eran la misma lista.

    ── EL ORDEN ES EL DE CARGA, no el de entrega ──
    De arriba hacia abajo se lee como se estiba: **la última parada primero**, porque lo
    que baja primero se carga último (contra la puerta). Es el orden del andén y el del
    Excel. La lista del CHOFER —parada 1 primero— sigue viviendo abajo, y esa asimetría
    es deliberada: son dos lectores distintos y ninguno tiene que leer al revés.

    ── EL COLOR ES DEL PRODUCTO, NO DEL GRUPO ──
    EasyCargo pinta cada grupo de un color; acá no. El color ya significa «qué producto
    es» —es el mismo del bloque en el dibujo, la única excepción sancionada a la paleta
    (D-013)— y un segundo sistema de color compitiendo le saca el significado a la letra.
    El grupo se separa con banda gris, número sólido y peso tipográfico.

    Y la LETRA es del producto, no de la posición en la lista: se conserva aunque el
    producto cambie de grupo, porque es lo que está escrito sobre sus cajas en el lienzo.
--}}
@php
    // Se agrupa por parada SIN reordenar las líneas dentro del grupo: el índice de la
    // línea es lo que ata la fila con su bloque en el dibujo, con su letra y con su
    // color (bitácora 2026-08-10: reindexar una lista que otra estructura referencia
    // por índice es lo que las desalinea en cuanto se descarta un elemento).
    $porParada = [];
    foreach ($mixta['lineas'] as $i => $fila) {
        $porParada[$fila['parada']][$i] = $fila;
    }
    // Orden de CARGA: la parada más alta primero (se carga al fondo). La 0 —sin parada
    // declarada— va al final: se carga contra la puerta, o sea que sale en la primera
    // entrega, que es lo que ya avisa el formulario.
    krsort($porParada);
    $volumenCamion = $camion ? $camion->volumenM3() : null;
    $hayParadas = ($mixta['paradas'] ?? null) !== null;
@endphp

<div class="min-h-0 flex-1 overflow-y-auto">
    @foreach ($porParada as $parada => $filas)
        @if ($hayParadas)
            @php
                // El subtotal de la parada: cuánto volumen baja acá. Es el dato que no
                // existía en ninguna pantalla — ni la lista plana ni la del reparto lo
                // decían — y es el que contesta «¿cuánto le dejo a este cliente?».
                $volGrupo = array_sum(array_column($filas, 'volumen_m3'));
            @endphp
            <div class="flex items-center gap-2 border-t border-neutral-200 bg-neutral-100 px-2 py-1">
                <span @class([
                    'flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[9px] font-bold',
                    'bg-neutral-800 text-white' => $parada > 0,
                    'bg-neutral-300 text-neutral-700' => $parada === 0,
                ])>{{ $parada > 0 ? $parada : '—' }}</span>
                <span class="text-[11px] font-semibold text-neutral-900">
                    {{ $parada > 0 ? 'Parada '.$parada : 'Sin parada' }}
                </span>
                <span class="ml-auto shrink-0 text-[11px] tabular-nums text-neutral-500">
                    {{ number_format($volGrupo, 1, ',', '.') }} m³
                </span>
            </div>
        @endif

        @foreach ($filas as $i => $fila)
            @php
                $rgb = $ctrl::COLORES_3D[$i % count($ctrl::COLORES_3D)];
                $pct = $volumenCamion > 0 ? round($fila['volumen_m3'] / $volumenCamion * 100) : 0;
                $pal = $fila['pallet'];
                // Qué se cuenta en la línea 2: pallets si va paletizada, unidades si no.
                $sustantivo = $pal
                    ? \Illuminate\Support\Str::plural('pallet', $fila['cargadas_unidades'])
                    : ($fila['modelo']->unidades > 1 ? 'unidades' : 'bultos');
            @endphp
            <div class="flex gap-2 border-t border-neutral-100 px-2 py-1.5 {{ $fila['abierta'] ? 'bg-neutral-50' : '' }}">
                <span class="mt-0.5 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded text-[10px] font-bold text-white"
                      style="background: rgb({{ implode(',', $rgb) }})"
                      title="Esta letra va escrita sobre sus cajas en el visor">{{ $ctrl::letra($i) }}</span>

                <div class="min-w-0 flex-1 leading-tight">
                    <div class="flex items-baseline gap-1.5">
                        <p class="min-w-0 flex-1 truncate text-[12px] font-medium text-neutral-900"
                           title="{{ $fila['modelo']->nombre }}">{{ $fila['modelo']->nombre }}</p>
                        <span class="shrink-0 text-[12px] font-semibold tabular-nums text-neutral-900">
                            {{ number_format($fila['volumen_m3'], 1, ',', '.') }} m³
                        </span>
                    </div>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        {{-- CUÁNTO. En una línea ABIERTA no se dice «de cuántas»: no se pidió
                             un número, se pidió «lo que quepa» — decir «84 de 84» inventaría
                             un pedido que nadie hizo. --}}
                        <span class="shrink-0 text-[11px] tabular-nums text-neutral-600">
                            <span class="font-semibold text-neutral-900">{{ number_format($fila['cargadas_unidades'], 0, ',', '.') }}</span>
                            @if (! $fila['abierta'] && $fila['cargadas_unidades'] !== $fila['pedidas_unidades'])
                                de {{ number_format($fila['pedidas_unidades'], 0, ',', '.') }}
                            @endif
                            {{ $sustantivo }}
                        </span>
                        @if ($fila['abierta'])
                            <span class="shrink-0 rounded-full bg-neutral-200 px-1.5 text-[10px] font-medium text-neutral-600"
                                  title="Se pidió «lo que quepa»: el motor lo llenó hasta que no entró uno más.">lo que quepa</span>
                        @endif
                        <span class="ml-auto shrink-0 text-[11px] tabular-nums text-neutral-500">{{ $pct }}%</span>
                        {{-- La barra va SIN posicionamiento absoluto a propósito: el
                             candado `test_los_controles_viven_en_un_solo_menu…` prohíbe
                             `absolute` dentro del menú, porque es lo que distingue una
                             barra que come ancho de un panel flotando sobre el camión
                             (doctrina 06-08). Un relleno con `width:%` dentro de un riel
                             con `overflow-hidden` hace lo mismo sin pedir la excepción. --}}
                        <span class="h-1 w-8 shrink-0 overflow-hidden rounded-full bg-neutral-200">
                            <span class="block h-full rounded-full bg-brand-600"
                                  style="width: {{ min(100, $pct * 4) }}%"></span>
                        </span>
                    </div>
                    {{-- POR QUÉ QUEDÓ AFUERA. Va en la fila y no en un aviso aparte: es el
                         dato con el que se negocia, y buscarlo en otra parte de la pantalla
                         es lo que el dueño pidió recortar. --}}
                    @if ($fila['motivo'] !== null)
                        <p class="mt-0.5 text-[11px] font-medium leading-tight text-red-600">
                            {{-- El «Quedan N» solo si se pidió un N. En una línea ABIERTA
                                 `pedidas_unidades` es null y la resta daba «Quedan 0 afuera»,
                                 que se contradice con el motivo de al lado. Pasa de verdad
                                 con un pallet vacío: es un «no cabe» sin nada pedido. --}}
                            @if ($fila['pedidas_unidades'] !== null)
                                Quedan {{ number_format($fila['pedidas_unidades'] - $fila['cargadas_unidades'], 0, ',', '.') }} afuera:
                            @endif
                            {{ $ctrl::MOTIVOS_CORTOS[$fila['motivo']] ?? 'no entra' }}
                        </p>
                    @endif

                    {{-- ═══ EL AIRE QUE QUEDA ARRIBA DE ESTE PRODUCTO ═══
                         Pedido del dueño (10-08): «necesito que los bidones también lleguen
                         hasta el techo». El hueco no era del dibujo ni del acomodo — era el
                         tope de apilado del catálogo. Y no se explica solo: dos productos
                         apilados los MISMOS 6 llegan a alturas distintas según cuánto mida
                         cada uno, así que en pantalla parecía un error del dibujo.

                         Vivía en la lista de abajo del camión, que se fue al cerrar el «todo
                         en una pantalla» (21-08). Es la sexta función que la fusión se
                         llevaba en silencio: los dos números seguían viajando en la fila
                         (`apiladas`, `apilables_por_alto`) y ninguna vista los mostraba —
                         un dato calculado que nadie lee es peor que uno que no existe,
                         porque parece cubierto.

                         Va acá y no en el armador de abajo porque los dos números los tiene
                         el SERVIDOR: la tarjeta del armador se dibuja desde el array de
                         Alpine, que no los conoce. El botón sí escribe en ese array —el
                         mismo `apilarHasta` de siempre— y recalcula. --}}
                    @if ($fila['apiladas'] && $fila['apilables_por_alto'] > $fila['apiladas'])
                        {{-- EL BOTÓN DICE QUÉ PASA AL TOCARLO (dueño 25-08). Decía «Apilar 8»
                             y eso es el número, no la acción: no se sabía si iba a apilar 8
                             más, dejar 8 en total, o solo cambiar el tope sin recalcular.
                             Ahora la línea completa se lee como una frase —«Van 6 de 8 que
                             caben · Subir el tope a 8 y recalcular»— y la ⓘ explica lo único
                             que la frase no puede: que cuántas aguanta la de abajo es dato de
                             terreno y la decisión es de quien carga. --}}
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] leading-tight text-neutral-500">
                            <span>
                                Van <span class="font-medium tabular-nums text-neutral-700">{{ $fila['apiladas'] }}</span>
                                de <span class="font-medium tabular-nums text-neutral-700">{{ $fila['apilables_por_alto'] }}</span> que caben
                            </span>
                            {{-- `min-h-8` y no menos: el panel también se abre como cajón en
                                 el celular, así que este botón se toca con el dedo. 32px es
                                 lo que tenía en la lista de abajo; en 224px de ancho no cabe
                                 un objetivo de 48 sin partir la fila en dos. --}}
                            <button type="button"
                                    @click="apilarHasta({{ $i }}, {{ $fila['apilables_por_alto'] }})"
                                    class="min-h-8 rounded bg-brand-50 px-1.5 font-medium text-brand-700 ring-1 ring-inset ring-brand-200 transition hover:bg-brand-100"
                                    title="Sube el tope de apilado de {{ $fila['modelo']->nombre }} a {{ $fila['apilables_por_alto'] }} y vuelve a calcular la carga. Cuántas aguanta la de abajo es dato de terreno: la decisión es tuya.">
                                Subir el tope a {{ $fila['apilables_por_alto'] }} y recalcular
                            </button>
                        </p>
                    @endif
                </div>

                {{-- Quitar, en la fila: la lista de la carga es donde se saca un producto
                     (dueño 21-08). Recalcula, salvo que fuera la última — un formulario sin
                     líneas devolvería la pantalla al estado vacío. --}}
                <button type="button"
                        @click="quitar({{ $i }}); lineas.length && $nextTick(() => $refs.formMixta?.requestSubmit())"
                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded text-neutral-400 transition hover:bg-red-50 hover:text-red-600"
                        title="Quitar {{ $fila['modelo']->nombre }} de la carga y recalcular">
                    <x-icon.trash class="h-3.5 w-3.5" />
                    <span class="sr-only">Quitar {{ $fila['modelo']->nombre }} de la carga</span>
                </button>
            </div>
        @endforeach
    @endforeach
</div>
