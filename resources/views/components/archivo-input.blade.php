@props([
    'texto' => 'Elegir archivo',      // rótulo del botón, SIEMPRE completo
    'vacio' => 'Todavía no elegiste el archivo',
    'icono' => 'camera',              // camera | document
])

{{-- Selector de archivo con el texto LEGIBLE, pedido del dueño (2026-07-28).

     El problema: un `<input type="file">` nativo dibuja su propio rótulo y el
     navegador lo RECORTA sin piedad. En 375px se leía «Seleccionar archivo
     ningú…onado» — ni el botón ni el estado del archivo se entendían. Y no hay
     forma de arreglarlo con CSS: `::file-selector-button` estiliza el botón,
     pero el texto de «ningún archivo seleccionado» no es alcanzable.

     La salida: el input nativo queda TRANSPARENTE encima de nuestro botón
     (`opacity-0` + `absolute inset-0`), así que sigue siendo el que recibe el
     toque, el foco y la validación del navegador — nada de esconderlo con
     `display:none`, que es lo que rompe el envío en silencio cuando el campo es
     `required` (esa mina ya está en la bitácora). Encima se dibuja el botón con
     el rótulo entero, y DEBAJO el nombre del archivo elegido con `break-words`,
     que era justo lo que pidió el dueño: si no cabe, se envuelve; no se abrevia.

     `@change` y no `x-on:change` a propósito: son dos atributos HTML distintos,
     así que el listener del componente y el que pase el llamador conviven en vez
     de pisarse (en el lote, cada máquina anota su foto para el ✓ del acordeón). --}}
<div x-data="{ nombre: '' }">
    <div class="relative">
        <input type="file"
            {{ $attributes->merge(['class' => 'peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0']) }}
            @change="nombre = $event.target.files.length ? $event.target.files[0].name : ''">

        <div class="pointer-events-none flex min-h-11 items-center justify-center gap-2 rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-4 py-2.5 text-sm font-medium text-neutral-700 transition peer-hover:bg-neutral-100 peer-focus:border-brand-500 peer-focus:ring-2 peer-focus:ring-brand-500/30">
            <x-dynamic-component :component="'icon.'.$icono" class="h-5 w-5 shrink-0 text-neutral-400" />
            <span x-text="nombre ? 'Cambiar' : @js($texto)">{{ $texto }}</span>
        </div>
    </div>

    {{-- El nombre del archivo va ACÁ, debajo, y envuelve en varias líneas si hace
         falta. Es el pedido literal: que nada quede abreviado. --}}
    <p class="mt-1 break-words text-xs leading-snug"
       x-bind:class="nombre ? 'font-medium text-green-700' : 'text-neutral-500'"
       x-text="nombre || @js($vacio)">{{ $vacio }}</p>
</div>
