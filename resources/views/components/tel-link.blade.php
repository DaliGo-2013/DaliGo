@props(['telefono', 'etiqueta' => null])

{{-- Teléfono TOCABLE: en el celular abre el marcador (tel:). El técnico en
     terreno tenía el número como texto plano y debía copiarlo a mano.

     El href se limpia de espacios, puntos y paréntesis porque el marcador de
     iOS los tolera mal; lo que se MUESTRA queda tal como lo escribió quien
     agendó. min-h-11 = 44px, el mínimo táctil de Apple. --}}
@php
    $numero = preg_replace('/[^0-9+]/', '', (string) $telefono);
@endphp
@if ($numero !== '')
    <a href="tel:{{ $numero }}"
       {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700 active:scale-[0.98] sm:min-h-0']) }}>
        <x-icon.phone class="h-4 w-4 shrink-0" />
        <span>{{ $etiqueta ?? $telefono }}</span>
    </a>
@endif
