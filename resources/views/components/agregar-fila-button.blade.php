{{-- Botón "Agregar máquina / Agregar repuesto" de los formularios con filas
     repetibles (taller público, lote del conductor, cotización, parte del
     técnico). Estaba copiado a mano en los cuatro con `px-2.5 py-1.5`, o sea
     ~32px de alto: bajo el mínimo táctil de Apple (44px) y siendo el control
     que gobierna todo el flujo. `min-h-11` en móvil y la densidad original
     desde sm:, donde se apunta con el mouse. --}}
<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'inline-flex min-h-11 items-center gap-1 rounded-lg border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition duration-150 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500/40 active:scale-[0.98] sm:min-h-0 sm:px-2.5 sm:py-1.5',
]) }}>
    <x-icon.plus class="h-4 w-4" />
    {{ $slot }}
</button>
