{{-- Campos compartidos por create y edit. Recibe $tipo (null al crear). --}}
<div>
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" class="mt-1.5" type="text" name="nombre" :value="old('nombre', $tipo?->nombre)" required autofocus placeholder="Ej. Azul 20L c/manilla" />
    <x-input-hint>Es el texto que ve el soplador en el botón; corto y claro.</x-input-hint>
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div>
    <x-input-label for="codigo" value="Código" />
    <x-text-input id="codigo" class="mt-1.5" type="text" name="codigo" :value="old('codigo', $tipo?->codigo)" required placeholder="Ej. AZUL-20L-MANILLA" />
    <x-input-hint>Identificador corto y único; no cambia aunque renombres el tipo.</x-input-hint>
    <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
</div>

<div class="space-y-2">
    <x-checkbox-item name="activo" value="1" :checked="old('activo', $tipo?->activo ?? true)">
        Activo
        <x-slot name="note">los sopladores solo ven tipos activos</x-slot>
    </x-checkbox-item>
</div>
