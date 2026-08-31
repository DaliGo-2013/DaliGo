@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

<div
    x-data="{
        show: @js($show),
        focusables() {
            // All focusable element types...
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                // All non-disabled elements...
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="$watch('show', value => {
        if (value) {
            document.body.classList.add('overflow-y-hidden');
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.classList.remove('overflow-y-hidden');
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    {{-- pb con safe-area: los botones al pie del modal caían debajo de la barra
         de gestos del iPhone y no se podían tocar. --}}
    class="fixed inset-0 overflow-y-auto px-4 py-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] sm:px-0 z-50"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div
        x-show="show"
        class="fixed inset-0 transform transition-all"
        x-on:click="show = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-neutral-900 opacity-40"></div>
    </div>

    {{-- `relative z-10` NO es decoración: sin posicionamiento este panel queda ESTÁTICO, y un
         hermano `fixed` —el backdrop de arriba— se pinta SIEMPRE encima de un hermano estático,
         sin importar el orden del DOM. El resultado que reportó el dueño (28-08-2026) son tres
         síntomas de una sola causa: la ventana se ve atenuada (el `bg-neutral-900 opacity-40`
         está por delante), no se puede deslizar el contenido, y un clic en cualquier parte la
         cierra (el `x-on:click="show = false"` del backdrop recibe TODOS los clics).

         POR QUÉ FUNCIONABA ANTES Y DEJÓ DE FUNCIONAR: el panel trae `transform`, que en Tailwind
         v3 emitía un `transform` real y por lo tanto creaba contexto de apilamiento — eso lo
         elevaba por accidente. En v4 esa clase compila a
         `transform: var(--tw-rotate-x,) var(--tw-rotate-y,) …` con TODAS las variables en
         `initial`, así que la declaración resuelve a vacío y el navegador la descarta: `transform`
         quedó siendo un no-op y el panel perdió la elevación que nunca debió depender de ella.
         Misma familia que la bitácora [2026-07-26] (v4 usa `scale`/`translate` independientes).

         El z-index va explícito para que esto no vuelva a depender de un efecto colateral. --}}
    <div
        x-show="show"
        class="relative z-10 mb-6 bg-white border border-neutral-200 rounded-xl overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{ $slot }}
    </div>
</div>
