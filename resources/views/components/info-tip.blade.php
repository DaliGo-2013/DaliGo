{{-- "Más info" bajo demanda para cualquier tarjeta: un ícono ⓘ que revela la
     explicación (slot). Cross-device:
       · Móvil  → HOJA INFERIOR (bottom sheet) anclada al viewport (fixed inset-x-4
         bottom-4) con backdrop; nunca se recorta. Se abre al TOCAR.
       · Desktop → globo chico junto al ícono (sm:absolute), se abre al HOVER
         (detectado con matchMedia para no romper en touch) y también al tocar.
     Un solo globo abierto a la vez: al abrir uno se despacha `close-info-tips`
     (evento de ventana) que cierra a los demás — NO dependemos de la propagación
     del click (el botón usa @click.stop para no navegar dentro de tarjetas-enlace,
     lo que impedía que el @click.outside de los otros tips se enterara). Toda ruta
     de cierre resetea open y hover (evita el hover "pegado" en dispositivos que
     reportan hover:hover por error). Cierra con tocar fuera / backdrop / Esc.
     Props: align = right (globo hacia la izquierda, para esquinas) | left (hacia
     la derecha, para headers pegados al borde izquierdo) — solo aplica en desktop. --}}
@props(['align' => 'right'])

<span x-data="{ open: false, hover: false, canHover: window.matchMedia('(hover: hover)').matches }"
      class="relative inline-flex"
      @click.outside="open = false; hover = false"
      @keydown.escape.window="open = false; hover = false"
      @close-info-tips.window="open = false; hover = false">
    <button type="button"
            @click.stop="(open || (canHover && hover)) ? (open = false, hover = false) : ($dispatch('close-info-tips'), open = true)"
            @mouseenter="if (canHover) { $dispatch('close-info-tips'); hover = true }"
            @mouseleave="hover = false"
            :aria-expanded="(open || (canHover && hover)) ? 'true' : 'false'"
            class="inline-flex h-5 w-5 items-center justify-center rounded-full text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-600 focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:scale-[0.95]">
        <x-icon.information-circle class="h-4 w-4" />
        <span class="sr-only">Más información</span>
    </button>

    {{-- Backdrop: solo móvil (sm:hidden). Atenúa el fondo y cierra al tocar fuera. --}}
    <div x-show="open" x-cloak
         @click="open = false; hover = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-neutral-900/20 sm:hidden"></div>

    {{-- Globo: móvil = hoja inferior a ancho completo; desktop = popover junto al ícono. --}}
    <div x-show="open || (canHover && hover)" x-cloak
         @click.stop
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="fixed inset-x-4 bottom-4 z-50 rounded-xl border border-neutral-200 bg-white p-4 text-left text-sm font-normal leading-relaxed text-neutral-600 shadow-xl sm:absolute sm:inset-x-auto sm:bottom-auto sm:top-full sm:mt-2 sm:w-64 sm:max-w-[calc(100vw-2rem)] sm:p-3 sm:text-xs sm:shadow-lg {{ $align === 'left' ? 'sm:left-0' : 'sm:right-0' }}">
        {{ $slot }}
    </div>
</span>
