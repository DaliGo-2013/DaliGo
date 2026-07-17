{{-- Campos compartidos crear/editar servicio del catálogo de terreno.
     $s = servicio o null. --}}
@php $s = $servicio ?? null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-input-label for="nombre">Nombre del servicio <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="nombre" name="nombre" type="text" class="mt-1.5 w-full" required
            maxlength="191" placeholder="Ej. Full planta 1T" :value="old('nombre', $s?->nombre)" />
        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="valor_uf" value="Valor (UF neto)" />
        <x-text-input id="valor_uf" name="valor_uf" type="text" class="mt-1.5 w-full" inputmode="decimal"
            placeholder="Ej. 2,5" :value="old('valor_uf', $s?->valor_uf_fmt)" />
        <x-input-hint>Acepta coma decimal (2,5). Vacío = sin tarifa fija.</x-input-hint>
        <x-input-error :messages="$errors->get('valor_uf')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="duracion" value="Duración" />
        <x-text-input id="duracion" name="duracion" type="text" class="mt-1.5 w-full"
            maxlength="191" placeholder="Ej. 1 día, 1/2 día, 1/2 mañana" :value="old('duracion', $s?->duracion)" />
        <x-input-error :messages="$errors->get('duracion')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="incluye" value="Qué incluye" />
        <x-textarea id="incluye" name="incluye" rows="2" class="mt-1.5"
            placeholder="Ej. Pack: cambio de filtro carbón, resina y gravilla. Cambio de membranas y filtro papel.">{{ old('incluye', $s?->incluye) }}</x-textarea>
        <x-input-error :messages="$errors->get('incluye')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="observaciones" value="Observaciones" />
        <x-textarea id="observaciones" name="observaciones" rows="2" class="mt-1.5"
            placeholder="Ej. No incluye cambio de cabezal, estanque y/o reparaciones.">{{ old('observaciones', $s?->observaciones) }}</x-textarea>
        <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $s?->activo ?? true))
                class="rounded border-neutral-300 text-brand-600 shadow-sm focus:ring-brand-500/30">
            <span class="text-sm text-neutral-700">Activo (aparece en el selector de la agenda)</span>
        </label>
    </div>
</div>
