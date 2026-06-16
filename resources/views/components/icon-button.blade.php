@props(['href' => null, 'variant' => 'default', 'label' => null, 'size' => 'sm'])

@php
    $variants = [
        'default' => 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700',
        'danger' => 'text-neutral-400 hover:bg-red-50 hover:text-red-600',
        'primary' => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 active:scale-[0.98]',
        'secondary' => 'border border-neutral-300 bg-white text-neutral-500 shadow-sm hover:bg-neutral-50 hover:text-neutral-700 active:scale-[0.98]',
    ];
    // sm = acciones de fila (36px); lg = acciones principales, cómodas al tocar en móvil (48px).
    $sizes = ['sm' => 'p-2', 'md' => 'p-2.5', 'lg' => 'p-3.5'];
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
