{{-- Módulo de la sidebar V4 como acordeón nativo (details/summary, cero JS):
     chip de ícono + label (+badge) + chevron a la derecha que rota 90° al
     abrir. `open` lo decide el SERVIDOR por request (el módulo de la ruta
     activa llega abierto); en una MPA ese es el único estado que importa.
     El bloque global de prefers-reduced-motion (app.css) recorta la rotación. --}}
@props(['modulo', 'clave', 'abierto' => false, 'badge' => 0])

{{-- `open data-modulo="x"` queda contiguo a propósito: ancla el acordeón
     abierto A SU módulo en los tests (un '<details open' suelto pasaría
     con cualquier acordeón abierto). --}}
<details {{ $abierto ? 'open' : '' }} data-modulo="{{ $clave }}" class="group">
    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-neutral-900 transition duration-150 hover:bg-neutral-50 lg:py-2.5 [&::-webkit-details-marker]:hidden">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
            <x-dynamic-component :component="'icon.' . $modulo['icon']" class="h-5 w-5" />
        </span>
        <span class="flex flex-1 items-center gap-1.5">
            {{ $modulo['label'] }}
            @if ($badge > 0)
                <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white"
                      title="{{ str_replace(':n', $badge, $modulo['badge_title'] ?? ':n') }}">{{ $badge }}</span>
            @endif
        </span>
        {{-- Sin h-4 w-4: el merge del ícono concatena con su default h-5 w-5
             y en la cascada gana h-5 (override inerte — bitácora 2026-07-24). --}}
        <x-icon.chevron-right class="shrink-0 text-neutral-400 transition-transform duration-150 group-open:rotate-90" />
    </summary>
    <div class="ms-7 mt-1 space-y-0.5 border-l border-neutral-200 pb-1 pe-2 ps-3">
        {{ $slot }}
    </div>
</details>
