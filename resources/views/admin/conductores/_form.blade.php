{{-- Campos compartidos crear/editar conductor. $c = conductor o null. --}}
@php $c = $conductor ?? null; @endphp

<div class="space-y-5">
    <div>
        <x-input-label for="nombre">Nombre del conductor <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="nombre" name="nombre" type="text" class="mt-1.5 w-full" required
            maxlength="191" placeholder="Ej. Ariel Hernández" :value="old('nombre', $c?->nombre)" />
        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
    </div>

    <div>
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $c?->activo ?? true))
                class="rounded border-neutral-300 text-brand-600 shadow-sm focus:ring-brand-500/30">
            <span class="text-sm text-neutral-700">Activo (aparece en el selector del ingreso por lote)</span>
        </label>
    </div>
</div>
