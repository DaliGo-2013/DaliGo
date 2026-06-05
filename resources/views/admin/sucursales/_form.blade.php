{{-- Campos compartidos por create y edit. Recibe $sucursal (null al crear). --}}
<div>
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" class="mt-1.5" type="text" name="nombre" :value="old('nombre', $sucursal?->nombre)" required autofocus placeholder="Ej. Mirador" />
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div>
    <x-input-label for="codigo" value="Código" />
    <x-text-input id="codigo" class="mt-1.5" type="text" name="codigo" :value="old('codigo', $sucursal?->codigo)" required placeholder="Ej. MIRADOR" />
    <x-input-hint>Identificador corto y único.</x-input-hint>
    <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
</div>

<div>
    <x-input-label for="ciudad" value="Ciudad" />
    <x-text-input id="ciudad" class="mt-1.5" type="text" name="ciudad" :value="old('ciudad', $sucursal?->ciudad)" placeholder="Opcional" />
    <x-input-error :messages="$errors->get('ciudad')" class="mt-2" />
</div>

<div>
    <x-input-label for="direccion" value="Dirección" />
    <x-text-input id="direccion" class="mt-1.5" type="text" name="direccion" :value="old('direccion', $sucursal?->direccion)" placeholder="Opcional" />
    <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
</div>

<div class="space-y-2">
    <x-checkbox-item name="es_central" value="1" :checked="old('es_central', $sucursal?->es_central)">
        Es la sucursal central
    </x-checkbox-item>
    <x-checkbox-item name="activa" value="1" :checked="old('activa', $sucursal?->activa ?? true)">
        Activa
    </x-checkbox-item>
</div>
