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
<div class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm lg:col-span-2">
    <canvas id="carga3d" width="1240" height="720" class="block w-full cursor-grab"></canvas>

    <div class="absolute left-4 top-3 text-xs font-medium text-neutral-500">
        {{ $escena['vehiculo']['nombre'] }} · arrastrá para girar<span class="hidden lg:inline">, rueda para acercar</span>
    </div>

    <div class="absolute bottom-3 left-4 flex items-center gap-3 text-xs">
        <button type="button" id="carga3dPlay"
                class="rounded-lg bg-brand-600 px-2.5 py-1 font-semibold text-white transition hover:bg-brand-700">▶ Cargar</button>
        <span class="text-neutral-500"><span id="carga3dN">0</span> de {{ $escena['tope'] }}</span>
    </div>

    {{-- Nombres de los productos sobre su bloque. Se puede apagar: con un solo
         producto la etiqueta no aporta y tapa carga. --}}
    <div class="absolute bottom-3 right-4 flex items-center gap-1.5 text-xs">
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
