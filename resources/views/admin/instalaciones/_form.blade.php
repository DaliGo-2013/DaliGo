{{-- Campos compartidos crear/editar de una instalación de terreno.
     $i = instalación o null (crear). Requiere estar dentro del x-data
     instalacionForm (buscador de cliente que rellena nombre/rut/comuna). --}}
@php $i = $instalacion ?? null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    {{-- Fecha --}}
    <div>
        <x-input-label for="fecha">Fecha <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="fecha" name="fecha" type="date" class="mt-1.5 w-full" required
            :value="old('fecha', $i?->fecha?->format('Y-m-d') ?? \App\Support\FechaNegocio::hoy())" />
        <x-input-error :messages="$errors->get('fecha')" class="mt-2" />
    </div>

    {{-- Categoría --}}
    <div>
        <x-input-label for="categoria">Categoría <span class="text-red-500">*</span></x-input-label>
        <x-select id="categoria" name="categoria" class="mt-1.5" required>
            <option value="">— Selecciona —</option>
            @foreach ($categorias as $cat)
                <option value="{{ $cat }}" @selected(old('categoria', $i?->categoria) === $cat)>{{ \App\Models\Instalacion::CATEGORIA_ETIQUETAS[$cat] }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('categoria')" class="mt-2" />
    </div>

    {{-- Cliente: buscador por RUT/razón social; al elegir rellena nombre/rut/comuna. --}}
    <div class="sm:col-span-2">
        <x-input-label for="buscador_cliente" value="Buscar cliente (RUT o razón social)" />
        <div class="relative mt-1.5">
            <x-text-input id="buscador_cliente" type="text" class="w-full" autocomplete="off"
                placeholder="Ej. 76.543.210-9 o Agua Purificada…" x-model="rutBusqueda"
                x-on:input.debounce.300ms="buscarCliente()"
                x-on:keydown.escape="abierto = false"
                x-on:click.outside="abierto = false" />
            <div x-show="abierto && (buscando || resultados.length)" x-cloak
                 class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                <ul class="max-h-60 divide-y divide-neutral-100 overflow-auto">
                    <template x-for="r in resultados" :key="r.id">
                        <li>
                            <button type="button" x-on:click="elegirCliente(r)"
                                class="block w-full px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50"
                                x-text="r.label"></button>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
        <x-input-hint>Si ya existe, elígelo y se rellenan sus datos; si no, escríbelos abajo.</x-input-hint>
    </div>

    <input type="hidden" name="cliente_id" :value="clienteId">

    <div>
        <x-input-label for="cliente_nombre">Cliente / empresa <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 w-full" required
            maxlength="191" :value="old('cliente_nombre', $i?->cliente_nombre)" />
        <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="cliente_rut" value="RUT (opcional)" />
        <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 w-full"
            maxlength="20" :value="old('cliente_rut', $i?->cliente_rut)" />
        <x-input-error :messages="$errors->get('cliente_rut')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="comuna_region" value="Comuna / región" />
        <x-text-input id="comuna_region" name="comuna_region" type="text" class="mt-1.5 w-full"
            maxlength="191" :value="old('comuna_region', $i?->comuna_region)" placeholder="Ej. Copiapó, Serena…" />
        <x-input-error :messages="$errors->get('comuna_region')" class="mt-2" />
    </div>

    {{-- Producto instalado: texto libre con sugerencias del catálogo (datalist).
         No es lista cerrada — se puede escribir cualquier cosa. --}}
    <div class="sm:col-span-2">
        <x-input-label for="producto" value="Producto instalado" />
        <x-text-input id="producto" name="producto" type="text" class="mt-1.5 w-full" list="productos-catalogo"
            maxlength="191" :value="old('producto', $i?->producto)"
            placeholder="Ej. LAVADORA BOTELLON 20L-220V, PLANTA DE OSMOSIS 1T…" />
        <datalist id="productos-catalogo">
            @foreach ($productos as $p)
                <option value="{{ $p }}"></option>
            @endforeach
        </datalist>
        <x-input-hint>Empieza a escribir y aparecen productos del catálogo; también puedes escribir uno nuevo.</x-input-hint>
        <x-input-error :messages="$errors->get('producto')" class="mt-2" />
    </div>

    {{-- Instalación / puesta en marcha (SÍ/NO del Excel) --}}
    <div class="space-y-2 sm:col-span-2">
        <x-checkbox-item name="instalacion" value="1" :checked="old('instalacion', $i?->instalacion ?? false)">
            Se instaló <x-slot name="note">marca si la instalación quedó hecha</x-slot>
        </x-checkbox-item>
        <x-checkbox-item name="puesta_en_marcha" value="1" :checked="old('puesta_en_marcha', $i?->puesta_en_marcha ?? false)">
            Puesta en marcha <x-slot name="note">marca si además se dejó funcionando</x-slot>
        </x-checkbox-item>
    </div>

    {{-- Días trabajados --}}
    <div>
        <x-input-label for="dias" value="Días trabajados" />
        <x-text-input id="dias" name="dias" type="number" min="0" max="365" class="mt-1.5 w-full"
            :value="old('dias', $i?->dias)" placeholder="Ej. 2" />
        <x-input-error :messages="$errors->get('dias')" class="mt-2" />
    </div>

    {{-- Vendedor (texto con sugerencias) --}}
    <div>
        <x-input-label for="vendedor" value="Vendedor" />
        <x-text-input id="vendedor" name="vendedor" type="text" class="mt-1.5 w-full" list="vendedores-sugeridos"
            maxlength="191" :value="old('vendedor', $i?->vendedor)" placeholder="Nombre del vendedor" />
        <datalist id="vendedores-sugeridos">
            @foreach ($vendedores as $v)
                <option value="{{ $v }}"></option>
            @endforeach
        </datalist>
        <x-input-error :messages="$errors->get('vendedor')" class="mt-2" />
    </div>

    {{-- Datos de factura / pago --}}
    <div>
        <x-input-label for="n_factura" value="N° de factura" />
        <x-text-input id="n_factura" name="n_factura" type="text" class="mt-1.5 w-full"
            maxlength="50" :value="old('n_factura', $i?->n_factura)" />
        <x-input-error :messages="$errors->get('n_factura')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="fecha_factura" value="Fecha de factura" />
        <x-text-input id="fecha_factura" name="fecha_factura" type="date" class="mt-1.5 w-full"
            :value="old('fecha_factura', $i?->fecha_factura?->format('Y-m-d'))" />
        <x-input-error :messages="$errors->get('fecha_factura')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="forma_pago" value="Forma de pago" />
        <x-select id="forma_pago" name="forma_pago" class="mt-1.5">
            <option value="">— Sin especificar —</option>
            @foreach ($formasPago as $fp)
                <option value="{{ $fp }}" @selected(old('forma_pago', $i?->forma_pago) === $fp)>{{ \App\Models\Instalacion::FORMA_PAGO_ETIQUETAS[$fp] }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('forma_pago')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="fecha_pago" value="Fecha de pago" />
        <x-text-input id="fecha_pago" name="fecha_pago" type="date" class="mt-1.5 w-full"
            :value="old('fecha_pago', $i?->fecha_pago?->format('Y-m-d'))" />
        <x-input-error :messages="$errors->get('fecha_pago')" class="mt-2" />
    </div>
</div>
