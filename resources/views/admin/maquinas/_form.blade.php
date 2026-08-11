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

<div>
    <x-input-label for="oee_target" value="Meta de OEE (%)" />
    <x-text-input id="oee_target" class="mt-1.5" type="number" name="oee_target" min="1" max="100" step="1"
                  :value="old('oee_target', $maquina?->oee_target)" placeholder="Ej. 85" />
    <x-input-error :messages="$errors->get('oee_target')" class="mt-2" />
    <x-input-hint class="mt-2">El informe de rendimiento pinta el OEE del período contra esta meta. Vacío = sin meta declarada.</x-input-hint>
</div>

<div class="space-y-2">
    <x-checkbox-item name="activa" value="1" :checked="old('activa', $maquina?->activa ?? true)">
        Activa
        <x-slot name="note">los sopladores solo ven máquinas activas</x-slot>
    </x-checkbox-item>
</div>
