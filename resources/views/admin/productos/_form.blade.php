@php $p = $producto ?? null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div class="sm:col-span-1">
        <x-input-label for="sku" value="SKU" />
        <x-text-input id="sku" class="mt-1.5" type="text" name="sku" :value="old('sku', $p?->sku)" required maxlength="64" placeholder="ej. BOT-20L-MAN" />
        <x-input-error :messages="$errors->get('sku')" class="mt-2" />
    </div>

    <div class="sm:col-span-1">
        <x-input-label for="nombre" value="Nombre" />
        <x-text-input id="nombre" class="mt-1.5" type="text" name="nombre" :value="old('nombre', $p?->nombre)" required />
        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="descripcion" value="Descripción" />
        <x-textarea id="descripcion" class="mt-1.5" name="descripcion" rows="2">{{ old('descripcion', $p?->descripcion) }}</x-textarea>
        <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="categoria" value="Categoría" />
        <x-text-input id="categoria" class="mt-1.5" type="text" name="categoria" :value="old('categoria', $p?->categoria)" list="categorias-list" />
        <datalist id="categorias-list">
            @foreach ($categorias as $c)
                <option value="{{ $c }}"></option>
            @endforeach
        </datalist>
        <x-input-error :messages="$errors->get('categoria')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="marca" value="Marca" />
        <x-text-input id="marca" class="mt-1.5" type="text" name="marca" :value="old('marca', $p?->marca)" list="marcas-list" />
        <datalist id="marcas-list">
            @foreach ($marcas as $m)
                <option value="{{ $m }}"></option>
            @endforeach
        </datalist>
        <x-input-error :messages="$errors->get('marca')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="peso_kg" value="Peso (kg)" />
        <x-text-input id="peso_kg" class="mt-1.5" type="number" step="0.001" min="0" name="peso_kg" :value="old('peso_kg', $p?->peso_kg)" />
        <x-input-error :messages="$errors->get('peso_kg')" class="mt-2" />
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div>
            <x-input-label for="alto_cm" value="Alto (cm)" />
            <x-text-input id="alto_cm" class="mt-1.5" type="number" step="0.01" min="0" name="alto_cm" :value="old('alto_cm', $p?->alto_cm)" />
            <x-input-error :messages="$errors->get('alto_cm')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="ancho_cm" value="Ancho (cm)" />
            <x-text-input id="ancho_cm" class="mt-1.5" type="number" step="0.01" min="0" name="ancho_cm" :value="old('ancho_cm', $p?->ancho_cm)" />
            <x-input-error :messages="$errors->get('ancho_cm')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="largo_cm" value="Largo (cm)" />
            <x-text-input id="largo_cm" class="mt-1.5" type="number" step="0.01" min="0" name="largo_cm" :value="old('largo_cm', $p?->largo_cm)" />
            <x-input-error :messages="$errors->get('largo_cm')" class="mt-2" />
        </div>
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="atributos" value="Atributos (JSON, opcional)" />
        <x-textarea id="atributos" class="mt-1.5 font-mono text-xs" name="atributos" rows="3" placeholder='{"color":"azul"}'>{{ old('atributos', $p && $p->atributos ? json_encode($p->atributos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</x-textarea>
        <x-input-hint>Metadata libre en formato JSON (un objeto).</x-input-hint>
        <x-input-error :messages="$errors->get('atributos')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-checkbox-item name="activo" value="1" :checked="old('activo', $p?->activo ?? true)">
            Activo
        </x-checkbox-item>
    </div>
</div>
