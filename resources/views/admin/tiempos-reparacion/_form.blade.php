{{-- Campos compartidos crear/editar de un tiempo estándar de reparación.
     $t = tiempo o null (crear). --}}
@php
    $t = $tiempo ?? null;
    $grupos = config('servicio_tecnico.respuestas_trabajo', []);
    $trabajosSugeridos = collect($grupos)->flatten()->all();
@endphp

<div class="space-y-5">
    <div>
        <x-input-label for="trabajo">Trabajo <span class="text-red-500">*</span>
            {{-- La ayuda decía «debe coincidir con la respuesta del parte del técnico», y desde
                 el 28-08 eso dejó de ser cierto: el técnico MARCA este trabajo de una lista, no
                 lo escribe, así que ya no hay ningún texto con el que tenga que coincidir. Esa
                 coincidencia por texto era justamente lo que hacía que una reparación mixta
                 quedara sin mano de obra. --}}
            <x-slot:ayuda>
                Así lo va a ver el técnico en la lista para marcarlo, y así entra en el texto que lee el
                cliente. Escribí solo el trabajo; si agregás «— funciona normal» al final, el cierre de
                la frase se elige aparte en el parte y no se repite por cada trabajo marcado.
            </x-slot:ayuda>
        </x-input-label>
        {{-- `?trabajo=` viene del listado, del apartado «escritos a mano por los técnicos»: así
             jefatura no vuelve a tipear el texto (ni le cambia una letra sin querer). --}}
        <x-text-input id="trabajo" name="trabajo" type="text" class="mt-1.5 w-full" list="trabajos-sugeridos" required
            maxlength="191" :value="old('trabajo', $t?->trabajo ?? request('trabajo'))" placeholder="Ej. Cambio de caldera" />
        <datalist id="trabajos-sugeridos">
            @foreach ($trabajosSugeridos as $tr)
                <option value="{{ $tr }}"></option>
            @endforeach
        </datalist>
        <x-input-error :messages="$errors->get('trabajo')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="horas">Horas estándar <span class="text-red-500">*</span>
                <x-slot:ayuda>Acepta coma decimal (1,5). Con esto se calcula la mano de obra (horas × valor hora); el técnico no la puede cambiar.</x-slot:ayuda>
            </x-input-label>
            <x-text-input id="horas" name="horas" type="text" class="mt-1.5 w-full" inputmode="decimal" required
                placeholder="Ej. 1, 1,5, 2" :value="old('horas', $t?->horas_fmt)" />
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
