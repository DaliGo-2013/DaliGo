{{-- La versión ENLACE de <x-secondary-button>: mismo aspecto, pero navega (o
     descarga) en vez de enviar un formulario. Existe porque el catálogo tenía el
     enlace con estilo primario (<x-button-link>) y el botón secundario solo como
     <button>, así que una acción secundaria que navega —descargar, abrir otra
     pantalla al lado de la acción principal— no tenía componente y se escribía a
     mano. Mismas clases que secondary-button + el gap/justify de button-link. --}}
<a {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700 shadow-sm transition duration-150 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-2 focus:ring-offset-white active:scale-[0.98]']) }}>{{ $slot }}</a>
