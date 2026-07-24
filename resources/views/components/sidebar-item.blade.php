{{-- Subítem de un módulo de la sidebar V4. `aria-current="page"` contiguo al
     href a propósito: marcador estable para tests (doctrina anti
     verde-engañoso, bitácora 2026-07-20). --}}
@props(['item', 'activo' => false])

<a href="{{ route($item['route']) }}" @if ($activo) aria-current="page" @endif
   {{ $attributes->merge(['class' => $activo
       ? 'block rounded-lg bg-brand-50 px-2 py-2.5 text-sm font-medium text-brand-700 lg:py-1.5'
       : 'block rounded-lg px-2 py-2.5 text-sm text-neutral-600 transition duration-150 hover:bg-neutral-50 hover:text-neutral-900 lg:py-1.5']) }}>
    {{ $item['label'] }}
</a>
