{{-- `max-lg:min-h-11`: mínimo táctil de 44px MIENTRAS es drawer (hasta lg:, no sm:
     como el resto — el menú lateral es táctil hasta 1024px). Medía 40px. Ver
     primary-button.blade.php para el criterio.

     Subítem de un módulo de la sidebar V4. `aria-current="page"` contiguo al
     href a propósito: marcador estable para tests (doctrina anti
     verde-engañoso, bitácora 2026-07-20). --}}
@props(['item', 'activo' => false, 'badge' => 0])

<a href="{{ route($item['route']) }}"@if ($activo) aria-current="page"@endif
   {{ $attributes->merge(['class' => $activo
       ? 'flex items-center gap-1.5 rounded-e-lg border-s-4 border-brand-600 bg-brand-50 px-2 py-2.5 text-sm font-semibold text-brand-700 max-lg:min-h-11 lg:py-1.5'
       : 'flex items-center gap-1.5 rounded-lg px-2 py-2.5 text-sm text-neutral-600 max-lg:min-h-11 transition duration-150 hover:bg-neutral-50 hover:text-neutral-900 lg:py-1.5']) }}>
    {{ $item['label'] }}
    @if ($badge > 0)
        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white"
              title="{{ str_replace(':n', $badge, $item['badge_title'] ?? ':n') }}">{{ $badge }}</span>
    @endif
</a>
