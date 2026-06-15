@props([
    'name',                 // nombre del <input hidden> que viaja en el submit (ej. 'cliente_id')
    'label',                // etiqueta del campo
    'endpoint',             // URL del autocompletado JSON
    'chip' => 'Seleccionado', // texto corto del badge cuando hay seleccion
    'inicialId' => 0,       // id del modelo actual (al editar)
    'inicialLabel' => '',   // etiqueta del modelo actual (al editar)
    'placeholder' => 'Escribe para buscar…',
    'hint' => null,
])

@php $inputId = $name.'_buscar'; @endphp

{{-- Autocompletado reutilizable (cliente, producto, …). El id elegido viaja en
     el <input hidden name="{{ $name }}">; "{{ $name }}_label" solo repuebla el
     texto si la validacion falla (no se persiste). Logica en buscadorRemoto (app.js). --}}
<div {{ $attributes }}
     x-data="buscadorRemoto({
        endpoint: '{{ $endpoint }}',
        inicialId: {{ (int) old($name, $inicialId) }},
        inicialLabel: @js(old($name.'_label', $inicialLabel))
     })">
    <x-input-label :for="$inputId" :value="$label" />

    <input type="hidden" name="{{ $name }}" :value="seleccionId">
    <input type="hidden" name="{{ $name }}_label" :value="elegidoLabel">

    <div class="relative mt-1.5">
        <x-text-input :id="$inputId" type="text" class="w-full" autocomplete="off"
            x-ref="input" :placeholder="$placeholder"
            x-model="term"
            @input.debounce.300ms="buscar()"
            @focus="if (resultados.length) abierto = true"
            @keydown.escape="abierto = false"
            @click.outside="abierto = false" />

        <div x-show="abierto" x-cloak
             class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
            <template x-if="cargando">
                <div class="px-3.5 py-2.5 text-sm text-neutral-400">Buscando…</div>
            </template>
            <template x-if="!cargando && resultados.length === 0 && term.length >= 2">
                <div class="px-3.5 py-2.5 text-sm text-neutral-400">Sin resultados para “<span x-text="term"></span>”.</div>
            </template>
            <ul class="max-h-60 divide-y divide-neutral-100 overflow-auto">
                <template x-for="r in resultados" :key="r.id">
                    <li>
                        <button type="button" @click="elegir(r)"
                            class="block w-full px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50">
                            <span x-text="r.label"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    <div x-show="seleccionId" x-cloak class="mt-2 flex flex-wrap items-center gap-2 text-sm">
        <x-badge>{{ $chip }}</x-badge>
        <span class="font-medium text-neutral-800" x-text="elegidoLabel"></span>
        <button type="button" @click="limpiar()" class="text-xs text-neutral-400 underline hover:text-neutral-600">cambiar</button>
    </div>

    @if ($hint)
        <x-input-hint x-show="!seleccionId" x-cloak>{{ $hint }}</x-input-hint>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
