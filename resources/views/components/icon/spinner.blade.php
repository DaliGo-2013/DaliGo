{{--
    Spinner de carga. Mismo patron de tamano que el resto de la familia icon/*: si
    quien lo usa ya trae una clase h-*, no se aplica el tamano por defecto (si no,
    `$attributes->merge` concatena y gana el h- posterior en el CSS — gotcha del
    2026-07-24, que dejaba todos los overrides de tamano inertes).

    El giro lo pone quien lo usa con `animate-spin`, no el componente: asi tambien
    sirve como icono estatico si algun dia hace falta.
--}}
@props(['size' => 'h-5 w-5'])
@php
    $size = preg_match('/(?:^|\s)h-/', (string) $attributes->get('class')) ? '' : $size;
@endphp
<svg {{ $attributes->merge(['class' => $size]) }} fill="none" viewBox="0 0 24 24" aria-hidden="true">
    {{-- Aro tenue de fondo + arco opaco: el contraste es lo que hace visible el giro. --}}
    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" opacity="0.25" />
    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
</svg>
