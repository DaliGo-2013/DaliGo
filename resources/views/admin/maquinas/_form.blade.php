{{-- Campos compartidos por create y edit. Recibe $maquina (null al crear) y $sucursales. --}}
<div>
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" class="mt-1.5" type="text" name="nombre" :value="old('nombre', $maquina?->nombre)" required autofocus placeholder="Ej. Sopladora 1" />
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div>
    <x-input-label for="sucursal_id" value="Sucursal" />
    <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5" required>
        <option value="">— Elige una sucursal —</option>
        @foreach ($sucursales as $sucursal)
            <option value="{{ $sucursal->id }}" @selected((int) old('sucursal_id', $maquina?->sucursal_id) === $sucursal->id)>{{ $sucursal->nombre }}</option>
        @endforeach
    </x-select>
    <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
</div>

<div class="space-y-2">
    <x-checkbox-item name="activa" value="1" :checked="old('activa', $maquina?->activa ?? true)">
        Activa
        <x-slot name="note">los sopladores solo ven máquinas activas</x-slot>
    </x-checkbox-item>
</div>
