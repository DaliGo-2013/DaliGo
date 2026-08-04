{{-- El tamaño va por el prop `size` o por `class`. Si el llamador ya pone un alto
     en `class`, NO agregamos el default: el merge CONCATENA y con dos tamaños gana
     el posterior en el CSS, no el del llamador. Ver bitácora 2026-07-24. --}}
@props(['size' => 'h-5 w-5'])
@php
    $size = preg_match('/(?:^|\s)h-/', (string) $attributes->get('class')) ? '' : $size;
@endphp
<svg {{ $attributes->merge(['class' => $size]) }} fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
</svg>
