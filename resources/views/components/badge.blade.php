@props(['variant' => 'brand'])

@php
    // Paleta de 4 colores: marca (naranjo), neutro y danger (rojo, solo destructivo/negativo).
    $variants = [
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-100',
        'neutral' => 'bg-neutral-100 text-neutral-500 ring-neutral-200',
        'danger' => 'bg-red-50 text-red-700 ring-red-100',
    ];
    $variant = $variants[$variant] ?? $variants['brand'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$variant]) }}>{{ $slot }}</span>
