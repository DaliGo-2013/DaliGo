{{-- "Más info" bajo demanda para cualquier tarjeta: un ícono ⓘ que revela un
     globo con el slot como explicación. Cross-device: se abre al TOCAR (móvil y
     desktop) y también con HOVER en desktop (detectado con matchMedia para no
     romper en touch). Cierra con click-afuera o Esc. El globo es viewport-safe.
     Props: align = right (globo hacia la izquierda, para esquinas) | left (hacia
     la derecha, para headers pegados al borde izquierdo). --}}
@props(['align' => 'right'])

<span x-data="{ open: false, hover: false, canHover: window.matchMedia('(hover: hover)').matches }"
      class="relative inline-flex"
      @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button"
            @click.stop="open = ! open"
            @mouseenter="hover = true" @mouseleave="hover = false"
            :aria-expanded="(open || (canHover && hover)) ? 'true' : 'false'"
            class="inline-flex h-5 w-5 items-center justify-center rounded-full text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-600 focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:scale-[0.95]">
        <x-icon.information-circle class="h-4 w-4" />
        <span class="sr-only">Más información</span>
    </button>

    <div x-show="open || (canHover && hover)" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         @click.stop
         class="absolute top-full z-30 mt-2 w-64 max-w-[calc(100vw-2rem)] rounded-xl border border-neutral-200 bg-white p-3 text-left text-xs font-normal leading-relaxed text-neutral-600 shadow-lg {{ $align === 'left' ? 'left-0' : 'right-0' }}">
        {{ $slot }}
    </div>
</span>
