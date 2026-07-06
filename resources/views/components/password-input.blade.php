@props(['disabled' => false])

{{-- Input de contraseña con boton para ver/ocultar lo tipeado (ojo). Mismo
     estilo que x-text-input + pr-11 para el boton. La clase del call-site
     (margenes tipo mt-1.5) va al wrapper; el resto de atributos, al input. --}}
<div x-data="{ ver: false }" {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <input @disabled($disabled) type="password" x-bind:type="ver ? 'text' : 'password'"
        {{ $attributes->except('class')->merge(['class' => 'block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 pr-11 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 disabled:cursor-not-allowed disabled:opacity-60']) }}>
    <button type="button" x-on:click="ver = !ver" title="Mostrar u ocultar contraseña"
        class="absolute inset-y-0 right-0 flex items-center rounded-lg px-3 text-neutral-400 transition duration-150 hover:text-neutral-700">
        <x-icon.eye x-show="!ver" class="h-5 w-5" />
        <x-icon.eye-slash x-show="ver" x-cloak class="h-5 w-5" />
        <span class="sr-only">Mostrar u ocultar contraseña</span>
    </button>
</div>
