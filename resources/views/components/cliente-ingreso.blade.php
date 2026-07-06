@props([
    'endpoint',                 // URL del autocompletado de clientes (JSON)
    'inicialRut' => '',
    'inicialNombre' => '',
    'inicialTelefono' => '',
    'inicialClienteId' => 0,
])

{{-- Cliente del ingreso: nombre y RUT son obligatorios y se guardan en la orden.
     El RUT funciona como buscador (si existe en el catalogo, autocompleta nombre
     y enlaza cliente_id); si no, se escriben a mano. Logica en clienteIngreso (app.js). --}}
<div {{ $attributes }}
     x-data="clienteIngreso({
        endpoint: '{{ $endpoint }}',
        rut: @js(old('cliente_rut', $inicialRut)),
        nombre: @js(old('cliente_nombre', $inicialNombre)),
        telefono: @js(old('cliente_telefono', $inicialTelefono)),
        clienteId: {{ (int) old('cliente_id', $inicialClienteId) }}
     })">
    <input type="hidden" name="cliente_id" :value="clienteId">

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        {{-- RUT (buscador) --}}
        <div>
            <x-input-label for="cliente_rut">RUT <span class="text-red-500">*</span></x-input-label>
            <div class="relative mt-1.5">
                <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="w-full" autocomplete="off" required
                    x-ref="input" placeholder="Ej. 12.345.678-9"
                    x-model="rut"
                    @input.debounce.300ms="buscar()"
                    @focus="if (resultados.length) abierto = true"
                    @keydown.escape="abierto = false"
                    @click.outside="abierto = false" />

                <div x-show="abierto" x-cloak
                     class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                    <template x-if="cargando">
                        <div class="px-3.5 py-2.5 text-sm text-neutral-400">Buscando…</div>
                    </template>
                    <template x-if="!cargando && resultados.length === 0 && rut.length >= 2">
                        <div class="px-3.5 py-2.5 text-sm text-neutral-400">No está en el catálogo. Escribe el RUT y el nombre.</div>
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
            <x-input-hint>Si ya existe, elígelo de la lista; si no, escríbelo.</x-input-hint>
            <x-input-error :messages="$errors->get('cliente_rut')" class="mt-2" />
        </div>

        {{-- Nombre y apellido --}}
        <div>
            <x-input-label for="cliente_nombre">Nombre y apellido <span class="text-red-500">*</span></x-input-label>
            <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 w-full" required
                maxlength="191" placeholder="Nombre del cliente"
                x-model="nombre" />
            <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-2" />
        </div>

        {{-- Telefono de contacto --}}
        <div>
            <x-input-label for="cliente_telefono" value="Teléfono" />
            <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 w-full"
                maxlength="30" placeholder="Ej. +56 9 1234 5678"
                x-model="telefono" />
            <x-input-hint>Para avisarle cuando el equipo esté listo.</x-input-hint>
            <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-2" />
        </div>
    </div>
</div>
