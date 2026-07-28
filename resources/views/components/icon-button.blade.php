@props(['href' => null, 'variant' => 'default', 'label' => null, 'size' => 'sm'])

@php
    $variants = [
        'default' => 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700',
        'danger' => 'text-neutral-400 hover:bg-red-50 hover:text-red-600',
        'primary' => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 active:scale-[0.98]',
        // brand: icono de marca sobre un fondo tintado (cerrar el <x-aviso>).
        // Va como VARIANTE y no por class del llamador porque el merge de
        // atributos CONCATENA: un text-brand-600 pasado por fuera pelearia con
        // el text-neutral-400 del default y ganaria el que ordene el CSS
        // (mismo mecanismo del gotcha de tamano de los iconos, 2026-07-24).
        'brand' => 'text-brand-600 hover:bg-brand-100 hover:text-brand-700',
        'secondary' => 'border border-neutral-300 bg-white text-neutral-500 shadow-sm hover:bg-neutral-50 hover:text-neutral-700 active:scale-[0.98]',
    ];
    // sm = acciones de fila (36px en desktop); lg = acciones principales (48px).
    // En móvil el `sm` sube a 44px (mínimo táctil de Apple): con 36px los iconos
    // de Editar/Eliminar quedan pegados y se toca el equivocado. El `sm:size-auto`
    // devuelve la densidad de fila en desktop, donde se apunta con el mouse.
    $sizes = ['sm' => 'min-h-11 min-w-11 sm:min-h-0 sm:min-w-0 p-2', 'md' => 'p-2.5', 'lg' => 'p-3.5'];
    $classes = 'inline-flex items-center justify-center rounded-lg transition duration-150 focus:outline-none focus:ring-2 focus:ring-brand-500/40 '
        .($sizes[$size] ?? $sizes['sm']).' '
        .($variants[$variant] ?? $variants['default']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        @isset($label)<span class="sr-only">{{ $label }}</span>@endisset
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        {{ $slot }}
        @isset($label)<span class="sr-only">{{ $label }}</span>@endisset
    </button>
@endif
