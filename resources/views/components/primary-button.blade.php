{{-- `min-h-11 sm:min-h-0` = 44px de alto en MÓVIL, el mínimo táctil que este
     proyecto ya declaraba en `icon-button` («el mínimo táctil de Apple»). Medido a
     375px, este botón daba 40px: 4px de menos en cada acción de cada pantalla, y
     los vendedores trabajan en terreno con el celular en una mano.

     Va como `min-h` y no como más `py-`: un mínimo solo crece lo que está corto y
     no toca lo que ya llega (un botón con `h-12` de las pantallas de operario
     queda igual), así que no aparecen espacios en blanco de más. Desde `sm:` se
     libera y vuelve la densidad de escritorio, donde se apunta con el mouse. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 sm:min-h-0 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-2 focus:ring-offset-white active:scale-[0.98] disabled:opacity-50']) }}>
    {{ $slot }}
</button>
