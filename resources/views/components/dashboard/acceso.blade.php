@props(['item', 'paleta'])

{{-- Card de acceso del Inicio (M16): squircle de ícono + label, estilo Bsale
     sobrio. Vive dentro del x-data dgTiles del zócalo: en modo Personalizar el
     click abre el panel de swatches (en vez de navegar) y el color elegido se
     guarda por usuario (D-013). Sin JS, la card navega y pinta el color que
     dejó el servidor. --}}
<div class="relative" data-tile="{{ $item['key'] }}">
    {{-- En modo edición: preventDefault (no navegar) + stopPropagation (el
         mismo click burbujearía al @click.outside del panel y lo cerraría al
         abrirlo — gotcha primo del info-tip 2026-07-01). El estado `abierto`
         es único en dgTiles, así que abrir una card cierra la anterior sola. --}}
    <a href="{{ $item['href'] }}" title="{{ $item['desc'] }}"
        @click="if (editando) { $event.preventDefault(); $event.stopPropagation(); abrir('{{ $item['key'] }}') }"
        :class="editando && 'border-dashed border-brand-300'"
        class="flex flex-col items-center gap-2.5 rounded-2xl border border-neutral-200 bg-white p-4 text-center shadow-sm transition duration-150 hover:border-neutral-300 hover:shadow active:scale-[0.98]">
        <span data-squircle
            class="flex h-12 w-12 items-center justify-center rounded-xl transition duration-150 {{ $paleta[$item['color']]['tile'] }}">
            <x-dynamic-component :component="'icon.' . $item['icon']" class="h-6 w-6" />
        </span>
        <span class="text-sm font-medium text-neutral-800">{{ $item['label'] }}</span>
        <span class="sr-only">{{ $item['desc'] }}</span>
        {{-- Affordance del modo Personalizar: lápiz en la esquina de la card --}}
        <span x-show="editando" x-cloak
            class="absolute right-2 top-2 rounded-full bg-neutral-100 p-1 text-neutral-500">
            <x-icon.pencil class="h-3.5 w-3.5" />
        </span>
    </a>

    {{-- Panel de swatches: anclado bajo la card (z-10 sobre la fila siguiente);
         targets táctiles de 44px (h-11 w-11). --}}
    {{-- Sin x-transition a propósito: el modificador dejaba estilos inline
         pegados (opacity/transform) y el display no se aplicaba; el x-show
         puro togglea confiable y el motion del proyecto es sutil igual. --}}
    <div x-show="abierto === '{{ $item['key'] }}'" x-cloak
        @click.outside="abierto = null"
        class="absolute inset-x-0 top-full z-10 mt-1 flex flex-wrap justify-center gap-1 rounded-2xl border border-neutral-200 bg-white p-2 shadow-md"
        role="group" aria-label="Color para {{ $item['label'] }}">
        @foreach ($paleta as $colorKey => $c)
            <button type="button" @click="pintar('{{ $item['key'] }}', '{{ $colorKey }}')"
                :aria-pressed="String(colores['{{ $item['key'] }}'] === '{{ $colorKey }}')"
                aria-label="{{ $c['nombre'] }}" title="{{ $c['nombre'] }}"
                class="relative flex h-11 w-11 items-center justify-center rounded-full transition duration-150 hover:bg-neutral-50 active:scale-[0.98]">
                <span class="h-7 w-7 rounded-full ring-1 ring-inset {{ $c['dot'] }}"></span>
                <x-icon.check x-show="colores['{{ $item['key'] }}'] === '{{ $colorKey }}'" x-cloak
                    class="absolute h-4 w-4 text-neutral-700" />
            </button>
        @endforeach
    </div>
</div>
