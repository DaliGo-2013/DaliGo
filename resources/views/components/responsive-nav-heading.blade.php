{{-- Encabezado de sección del menú móvil (agrupa responsive-nav-links). --}}
<div {{ $attributes->merge(['class' => 'px-4 pb-1 pt-4 text-xs font-medium uppercase tracking-wide text-neutral-500']) }}>
    {{ $slot }}
</div>
