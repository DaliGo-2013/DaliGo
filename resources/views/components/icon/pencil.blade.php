{{-- El tamaño va por el prop `size` o por `class`. Si el llamador ya pone un alto
     en `class` (idioma `h-4 w-4` dentro de botones), NO agregamos el default: el
     merge CONCATENA y con dos tamaños gana el posterior en el CSS, no el del
     llamador — el override hacia abajo quedaba inerte. Ver bitácora 2026-07-24. --}}
@props(['size' => 'h-5 w-5'])
@php
    $size = preg_match('/(?:^|\s)h-/', (string) $attributes->get('class')) ? '' : $size;
@endphp
<svg {{ $attributes->merge(['class' => $size]) }} fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
</svg>
