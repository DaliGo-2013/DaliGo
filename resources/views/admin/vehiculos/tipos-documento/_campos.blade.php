{{--
    Los campos de un tipo de documento. Requiere: $tipo (el modelo, o null si es alta).

    Uno solo para el alta y para la edición: son los mismos tres datos, y dos copias
    es exactamente como se termina con un campo que existe en una pantalla y no en la
    otra.
--}}
@php
    $id = $tipo?->id ?? 'nuevo';
    // Sin marcas = «a todos», y así se dibuja: todas tildadas. Guardarlo como lista
    // vacía es lo que hace que un tipo de vehículo NUEVO en el catálogo quede incluido
    // (ver el controlador); acá solo hay que mostrarlo de la forma que se entiende.
    $marcados = $tipo && filled($tipo->aplica_a) ? $tipo->aplica_a : array_keys(\App\Models\Vehiculo::TIPOS);
    $marcados = old($tipo ? "aplica_a_$id" : 'aplica_a', $marcados);
@endphp

<div>
    <x-input-label :for="'nombre_'.$id" value="Nombre del documento" />
    <x-text-input :id="'nombre_'.$id" name="nombre" type="text" class="mt-1.5 w-full" maxlength="80"
                  :value="old('nombre', $tipo?->nombre)"
                  placeholder="ej. Póliza de carga peligrosa" />
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div>
    <x-input-label value="¿A qué vehículos les aplica?" />
    <div class="mt-1.5 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
        @foreach (\App\Models\Vehiculo::TIPOS as $clave => $label)
            <label class="flex min-h-8 items-center gap-2 text-sm text-neutral-700">
                <input type="checkbox" name="aplica_a[]" value="{{ $clave }}"
                       @checked(in_array($clave, (array) $marcados, true))
                       class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                {{ $label }}
            </label>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('aplica_a')" class="mt-2" />
    <p class="mt-1 text-xs text-neutral-500">
        Los que queden sin marcar no lo piden, así que no aparecen en rojo por no tenerlo.
    </p>
</div>

@if ($tipo)
    <label class="flex min-h-8 items-center gap-2 text-sm text-neutral-700">
        {{-- El hidden va ANTES: un checkbox destildado no se envía, y sin esto no habría
             forma de desactivar un tipo desde el formulario. --}}
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" name="activo" value="1" @checked($tipo->activo)
               class="rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
        En uso
    </label>
@endif
