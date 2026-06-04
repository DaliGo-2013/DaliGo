@props(['variant' => 'brand'])

@php
    $variants = [
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-100',
        'neutral' => 'bg-neutral-100 text-neutral-500 ring-neutral-200',
    ];
    $variant = $variants[$variant] ?? $variants['brand'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$variant]) }}>{{ $slot }}</span>
