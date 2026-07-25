@props(['href', 'titulo' => null])

{{-- ÚNICA forma de volver a la pantalla anterior en toda la app (doctrina del
     dueño, 2026-07-24). Antes convivían 13 familias: la MISMA flecha a la
     izquierda del título en 5 vistas y escrita a mano a la DERECHA en 26, una X
     rotulada "Cancelar" arriba, un "Cancelar" de texto al pie del formulario, y
     enlaces tenues escondidos DESPUÉS de la paginación. Reglas fijas, sin
     variantes ni props de estilo:
       - texto SIEMPRE el literal "Volver" (el destino va en el tooltip, nunca
         en el texto visible: así el control se ve idéntico en cada pantalla);
       - SIEMPRE arriba a la izquierda, pegado al título (a la derecha viven las
         acciones —Guardar/Editar/Crear— y salir no debe quedar junto a confirmar);
       - solo en pantallas que cuelgan de un listado. Un listado que ES ítem del
         menú NO lleva Volver: se llega por la sidebar, no tiene página padre.

     $href = la pantalla padre, y es el destino GARANTIZADO. El handler de
     app.js (data-dg-volver) solo lo reemplaza por history.back() cuando la
     página anterior es esa misma, para devolver el scroll y el mes abierto del
     listado; en cualquier otro caso se navega a este href.

     min-h-12 (48px) = objetivo táctil: en planta esto se toca con guantes. --}}
<a href="{{ $href }}" data-dg-volver title="{{ $titulo ?? 'Volver' }}"
   {{ $attributes->merge(['class' => 'inline-flex min-h-12 shrink-0 items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 text-sm font-medium text-neutral-600 shadow-sm transition duration-150 hover:bg-neutral-50 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500/40 active:scale-[0.98]']) }}>
    <x-icon.arrow-left class="h-5 w-5" />
    Volver
</a>
