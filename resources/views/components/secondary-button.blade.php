{{-- `min-h-11 sm:min-h-0`: mínimo táctil de 44px en móvil. El porqué completo está en primary-button.blade.php (medición del 28-08). --}}
<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-11 sm:min-h-0 items-center rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm transition duration-150 ease-in-out hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-2 focus:ring-offset-white active:scale-[0.98] disabled:opacity-50']) }}>
    {{ $slot }}
</button>
