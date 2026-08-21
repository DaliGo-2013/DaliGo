{{-- Vista de PUERTA del camión (dibujo propio en formato Heroicons outline:
     las dos hojas traseras con sus manillas). No existe en Heroicons. --}}
@props(['size' => 'h-5 w-5'])
@php
    $size = preg_match('/(?:^|\s)h-/', (string) $attributes->get('class')) ? '' : $size;
@endphp
<svg {{ $attributes->merge(['class' => $size]) }} fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 4.5h13.5v15H5.25zM12 4.5v15" />
    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 12h.01M14.25 12h.01" />
</svg>
