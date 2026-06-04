@props(['href' => null, 'variant' => 'default', 'label' => null])

@php
    $variants = [
        'default' => 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700',
        'danger' => 'text-neutral-400 hover:bg-red-50 hover:text-red-600',
    ];
    $classes = 'inline-flex items-center justify-center rounded-lg p-2 transition duration-150 focus:outline-none focus:ring-2 focus:ring-brand-500/40 '.($variants[$variant] ?? $variants['default']);
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
