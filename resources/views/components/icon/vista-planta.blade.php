{{-- Vista de PLANTA del camión (dibujo propio en formato Heroicons outline:
     la caja mirada desde arriba, con la cabina como franja). No existe en Heroicons. --}}
@props(['size' => 'h-5 w-5'])
@php
    $size = preg_match('/(?:^|\s)h-/', (string) $attributes->get('class')) ? '' : $size;
@endphp
<svg {{ $attributes->merge(['class' => $size]) }} fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h9v16.5h-9z" />
    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 17.25h9" />
</svg>
