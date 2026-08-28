{{-- `min-h-11 sm:min-h-0`: mínimo táctil de 44px en móvil. El porqué completo está en primary-button.blade.php (medición del 28-08). --}}
<a {{ $attributes->merge(['class' => 'inline-flex min-h-11 sm:min-h-0 shrink-0 items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-2 focus:ring-offset-white active:scale-[0.98]']) }}>
    {{ $slot }}
</a>
