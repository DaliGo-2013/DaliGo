{{-- Sección colapsable para pantallas de operario: cabecera tocable (label +
     resumen opcional + chevron) que muestra/oculta su contenido. El estado lo
     controla una propiedad booleana del x-data del CONTENEDOR (prop `model`,
     mismo patrón que stepper-input), p. ej. model="paneles.maquina". Así se puede
     auto-colapsar desde afuera (al elegir) y abrir ante errores de validación.
     Slots: por defecto = cuerpo; `summary` = texto mostrado solo al estar colapsada. --}}
@props(['label', 'model'])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-neutral-200']) }}>
    <button type="button" x-on:click="{{ $model }} = ! {{ $model }}"
            :aria-expanded="{{ $model }} ? 'true' : 'false'"
            class="flex w-full items-center justify-between gap-3 px-3.5 py-3 text-left transition duration-150 hover:bg-neutral-50">
        <span class="min-w-0">
            <span class="block text-sm font-medium text-neutral-900">{{ $label }}</span>
            @isset($summary)
                <span class="mt-0.5 block truncate text-xs text-neutral-500" x-show="! ({{ $model }})">{{ $summary }}</span>
            @endisset
        </span>
        <span class="shrink-0 text-neutral-400 transition-transform duration-150" :class="{{ $model }} && 'rotate-180'">
            <x-icon.chevron-down class="h-5 w-5" />
        </span>
    </button>

    <div x-show="{{ $model }}" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="border-t border-neutral-100 px-3.5 py-3">
        {{ $slot }}
    </div>
</div>
