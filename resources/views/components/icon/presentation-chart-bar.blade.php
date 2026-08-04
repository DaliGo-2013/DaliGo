{{-- El tamaño va por el prop `size` o por `class`. Si el llamador ya pone un alto
     en `class` (idioma `h-4 w-4` dentro de botones), NO agregamos el default: el
     merge CONCATENA y con dos tamaños gana el posterior en el CSS, no el del
     llamador — el override hacia abajo quedaba inerte. Ver bitácora 2026-07-24. --}}
@props(['size' => 'h-5 w-5'])
@php
    $size = preg_match('/(?:^|\s)h-/', (string) $attributes->get('class')) ? '' : $size;
@endphp
<svg {{ $attributes->merge(['class' => $size]) }} fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.25m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
</svg>
