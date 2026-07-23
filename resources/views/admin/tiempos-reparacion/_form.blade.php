{{-- Campos compartidos crear/editar de un tiempo estándar de reparación.
     $t = tiempo o null (crear). --}}
@php
    $t = $tiempo ?? null;
    $grupos = config('servicio_tecnico.respuestas_trabajo', []);
    $trabajosSugeridos = collect($grupos)->flatten()->all();
@endphp

<div class="space-y-5">
    <div>
        <x-input-label for="trabajo">Trabajo <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="trabajo" name="trabajo" type="text" class="mt-1.5 w-full" list="trabajos-sugeridos" required
            maxlength="191" :value="old('trabajo', $t?->trabajo)" placeholder="Ej. Cambio de caldera — funciona normal" />
        <datalist id="trabajos-sugeridos">
            @foreach ($trabajosSugeridos as $tr)
                <option value="{{ $tr }}"></option>
            @endforeach
        </datalist>
        <x-input-hint>Debe coincidir con la respuesta de «Trabajo realizado» del parte del técnico. Empieza a escribir y aparecen las de la lista.</x-input-hint>
        <x-input-error :messages="$errors->get('trabajo')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="horas">Horas estándar <span class="text-red-500">*</span></x-input-label>
            <x-text-input id="horas" name="horas" type="text" class="mt-1.5 w-full" inputmode="decimal" required
                placeholder="Ej. 1, 1,5, 2" :value="old('horas', $t?->horas_fmt)" />
            <x-input-hint>Acepta coma decimal (1,5). Con esto se calcula la mano de obra (horas × valor hora); el técnico no la puede cambiar.</x-input-hint>
            <x-input-error :messages="$errors->get('horas')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="grupo" value="Grupo (opcional)" />
            <x-select id="grupo" name="grupo" class="mt-1.5">
                <option value="">— Sin grupo —</option>
                @foreach (array_keys($grupos) as $g)
                    <option value="{{ $g }}" @selected(old('grupo', $t?->grupo) === $g)>{{ $g }}</option>
                @endforeach
            </x-select>
            <x-input-hint>Solo para ordenar el listado.</x-input-hint>
            <x-input-error :messages="$errors->get('grupo')" class="mt-2" />
        </div>
    </div>

    <div>
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $t?->activo ?? true))
                class="rounded border-neutral-300 text-brand-600 shadow-sm focus:ring-brand-500/30">
            <span class="text-sm text-neutral-700">Activo (se aplica al calcular la mano de obra)</span>
        </label>
    </div>
</div>
