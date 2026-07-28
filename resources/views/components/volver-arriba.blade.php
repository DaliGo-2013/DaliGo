{{-- Botón flotante "volver arriba", SOLO en móvil.

     El problema que resuelve: los listados paginan de 25 en 25 y en el celular
     cada fila ocupa ~150px, así que la página del listado de Servicio Técnico
     mide ~4.700px = unas 6 pantallas. Para volver al buscador —que está arriba—
     había que recorrer todo de vuelta, y con la ruedita del mouse en el
     simulador (o con el pulgar en un celular real) eso es lento y molesto.

     Aparece recién pasados 600px de scroll: antes de eso el buscador está a la
     vista y el botón solo estorbaría.

     `sm:hidden` porque en escritorio la barra de scroll y la ruedita ya
     resuelven; esto es una muleta táctil, no un adorno.

     POSICIÓN — el mapa de apilamiento del shell V4 manda:
       z-20 = este botón y el banner "llegaron ingresos nuevos" · z-30 = scrim
       del drawer · z-40 = drawer y sidebar · z-50 = modales e info-tip.
     Queda en z-20 para que el drawer y los modales lo tapen (es lo correcto:
     con el menú abierto no debe flotar encima). Y va a `bottom-20`, no a
     `bottom-4`, por dos razones concretas: el banner de ingresos nuevos vive
     centrado en `bottom-4` y en 375px es casi tan ancho como la pantalla, y la
     barra flotante de Safari en iOS también come esa franja. --}}
<button type="button"
        x-data="{ visible: false }"
        x-on:scroll.window.throttle.150ms="visible = window.scrollY > 600"
        x-show="visible"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-end="opacity-0 translate-y-1"
        x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        title="Volver arriba"
        class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-20 inline-flex h-12 w-12 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-700 shadow-lg transition duration-150 active:scale-[0.95] sm:hidden">
    {{-- No hay icono chevron-up en el set: es el chevron-down rotado. --}}
    <x-icon.chevron-down class="h-5 w-5 rotate-180" />
    <span class="sr-only">Volver arriba</span>
</button>
