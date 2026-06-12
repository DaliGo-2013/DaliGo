{{-- Campo de cantidad para operarios: sumar tocando botones grandes, sin tipear.
     Requiere un contenedor Alpine (x-data); `model` es la propiedad a la que se enlaza
     (por defecto, igual a `name`). `steps` define los botones de suma (ej. [1, 10]). --}}
@props(['label', 'hint' => null, 'name', 'model' => null, 'value' => 0, 'steps' => [1, 10]])

@php
    $model = $model ?? $name;
    $btn = 'flex h-12 shrink-0 select-none items-center justify-center rounded-lg border text-base font-semibold shadow-sm transition duration-150 focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:scale-[0.98]';
@endphp

<div {{ $attributes }}>
    <div class="flex flex-wrap items-baseline gap-x-2">
        <x-input-label :for="$name" :value="$label" />
        @if ($hint)
            <span class="text-xs text-neutral-500">{{ $hint }}</span>
        @endif
    </div>

    <div class="mt-1.5 flex items-stretch gap-2">
        <button type="button"
                class="{{ $btn }} w-12 border-neutral-300 bg-white text-neutral-500 hover:bg-neutral-50 hover:text-neutral-700"
                x-on:click="{{ $model }} = Math.max(0, (Number({{ $model }}) || 0) - 1)">
            <span aria-hidden="true">&minus;</span>
            <span class="sr-only">Quitar 1 de {{ $label }}</span>
        </button>

        <input id="{{ $name }}" name="{{ $name }}" type="number" min="0" inputmode="numeric" required
               x-model.number="{{ $model }}" value="{{ $value }}"
               class="block h-12 w-full min-w-0 rounded-lg border border-neutral-300 bg-white text-center text-lg font-semibold text-neutral-900 shadow-sm transition duration-150 [appearance:textfield] focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">

        @foreach ($steps as $step)
            <button type="button"
                    class="{{ $btn }} {{ $loop->last ? 'px-4' : 'w-12' }} border-brand-100 bg-brand-50 text-brand-700 hover:bg-brand-100"
                    x-on:click="{{ $model }} = (Number({{ $model }}) || 0) + {{ (int) $step }}">
                <span aria-hidden="true">+{{ $step }}</span>
                <span class="sr-only">Agregar {{ $step }} a {{ $label }}</span>
            </button>
        @endforeach
    </div>

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
