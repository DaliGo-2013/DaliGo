@props(['label', 'active' => false])

@php
$classes = $active
            ? 'inline-flex h-full items-center gap-1 px-1 pt-1 border-b-2 border-brand-600 text-sm font-medium leading-5 text-neutral-900 focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex h-full items-center gap-1 px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-neutral-500 hover:text-neutral-800 hover:border-neutral-300 focus:outline-none focus:text-neutral-800 focus:border-neutral-300 transition duration-150 ease-in-out';
@endphp

{{-- Dropdown de navegación: trigger con el mismo estilo/subrayado que x-nav-link.
     Mismo patrón Alpine y transiciones que x-dropdown (no se reutiliza directo
     porque sus divs intermedios impiden estirar el trigger a la altura del nav). --}}
<div class="relative flex" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <button type="button" @click="open = ! open" class="{{ $classes }}">
        {{ $label }}
        {{-- Badge opcional (ej. contador de Servicio Técnico); vacío para los demás menús. --}}
        {{ $badge ?? '' }}
        <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute start-0 top-full z-50 mt-1 w-48 origin-top-left rounded-md shadow-lg"
            style="display: none;"
            @click="open = false">
        <div class="rounded-md bg-white py-1 ring-1 ring-neutral-200">
            {{ $slot }}
        </div>
    </div>
</div>
