{{-- Form compartido crear/editar. Recibe $nota (null al crear) y $sopladores. --}}
<div class="space-y-4">
    <div>
        <x-input-label for="texto" value="Nota" />
        <x-text-input id="texto" name="texto" type="text" class="mt-1.5 block w-full"
                      maxlength="191" required
                      :value="old('texto', $nota?->texto)" />
        <x-input-hint>Lo que el soplador va a leer en su pantalla. Corto y fáctico («Hoy llegan preformas nuevas a las 15:00»).</x-input-hint>
        <x-input-error :messages="$errors->get('texto')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="soplador_id" value="Para quién" />
        <x-select id="soplador_id" name="soplador_id" class="mt-1.5 block w-full">
            <option value="">Para todos los sopladores</option>
            @foreach ($sopladores as $soplador)
                <option value="{{ $soplador->id }}" @selected((int) old('soplador_id', $nota?->soplador_id) === $soplador->id)>
                    {{ $soplador->name }}
                </option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('soplador_id')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="vigente_desde" value="Visible desde (opcional)" />
            <x-text-input id="vigente_desde" name="vigente_desde" type="date" class="mt-1.5 block w-full"
                          :value="old('vigente_desde', $nota?->vigente_desde?->toDateString())" />
            <x-input-hint>Vacío = desde ya.</x-input-hint>
            <x-input-error :messages="$errors->get('vigente_desde')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="vigente_hasta" value="Visible hasta (opcional)" />
            <x-text-input id="vigente_hasta" name="vigente_hasta" type="date" class="mt-1.5 block w-full"
                          :value="old('vigente_hasta', $nota?->vigente_hasta?->toDateString())" />
            <x-input-hint>Vacío = sin fecha de término.</x-input-hint>
            <x-input-error :messages="$errors->get('vigente_hasta')" class="mt-2" />
        </div>
    </div>
</div>
