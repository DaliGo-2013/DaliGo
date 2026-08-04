{{-- Campos del vehículo, compartidos por crear y editar. Agrupados en
     <x-seccion> (sin marco en móvil, tarjeta desde sm:) para no cobrarle al
     celular el padding pensado para el monitor — regla «Marco horizontal». --}}

<x-seccion titulo="Identificación">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="ppu">Patente <span class="text-red-500">*</span></x-input-label>
            <x-text-input id="ppu" name="ppu" type="text" class="mt-1.5 w-full font-mono uppercase" required
                          maxlength="12" :value="old('ppu', $vehiculo->ppu)" placeholder="ej. PFBS22" />
            <x-input-hint>Se guarda en mayúsculas y sin espacios.</x-input-hint>
            <x-input-error :messages="$errors->get('ppu')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="alias" value="Cómo le dicen" />
            <x-text-input id="alias" name="alias" type="text" class="mt-1.5 w-full"
                          maxlength="191" :value="old('alias', $vehiculo->alias)" placeholder="ej. HD35 Coquimbo" />
            <x-input-hint>El nombre con el que lo piden en la operación.</x-input-hint>
            <x-input-error :messages="$errors->get('alias')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="tipo">Tipo <span class="text-red-500">*</span></x-input-label>
            <x-select id="tipo" name="tipo" class="mt-1.5" required>
                @foreach (\App\Models\Vehiculo::TIPOS as $valor => $label)
                    <option value="{{ $valor }}" @selected(old('tipo', $vehiculo->tipo) === $valor)>{{ $label }}</option>
                @endforeach
            </x-select>
            <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="combustible" value="Combustible" />
            <x-select id="combustible" name="combustible" class="mt-1.5">
                <option value="">Sin especificar</option>
                @foreach (\App\Models\Vehiculo::COMBUSTIBLES as $valor => $label)
                    <option value="{{ $valor }}" @selected(old('combustible', $vehiculo->combustible) === $valor)>{{ $label }}</option>
                @endforeach
            </x-select>
            <x-input-error :messages="$errors->get('combustible')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="marca" value="Marca" />
            <x-text-input id="marca" name="marca" type="text" class="mt-1.5 w-full"
                          maxlength="60" :value="old('marca', $vehiculo->marca)" placeholder="ej. Hyundai" />
            <x-input-error :messages="$errors->get('marca')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="modelo" value="Modelo" />
            <x-text-input id="modelo" name="modelo" type="text" class="mt-1.5 w-full"
                          maxlength="120" :value="old('modelo', $vehiculo->modelo)" placeholder="ej. HD35 LWB 2.5" />
            <x-input-error :messages="$errors->get('modelo')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="anio" value="Año" />
            <x-text-input id="anio" name="anio" type="number" inputmode="numeric" class="mt-1.5 w-full"
                          min="1980" :max="now()->year + 1" :value="old('anio', $vehiculo->anio)" />
            <x-input-error :messages="$errors->get('anio')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="vin" value="VIN / chasis" />
            <x-text-input id="vin" name="vin" type="text" class="mt-1.5 w-full font-mono"
                          maxlength="40" :value="old('vin', $vehiculo->vin)" />
            <x-input-error :messages="$errors->get('vin')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="numero_motor" value="N° de motor" />
            <x-text-input id="numero_motor" name="numero_motor" type="text" class="mt-1.5 w-full font-mono"
                          maxlength="40" :value="old('numero_motor', $vehiculo->numero_motor)" />
            <x-input-error :messages="$errors->get('numero_motor')" class="mt-2" />
        </div>
    </div>
</x-seccion>

<x-seccion titulo="Asignación">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="base" value="Base" />
            {{-- Texto con lista sugerida (datalist) y NO un select cerrado: la
                 flota se mueve entre bases que no son sucursales de DaliGo
                 (Jefaturas, Damimed) y no queremos un deploy para agregar una. --}}
            <x-text-input id="base" name="base" type="text" class="mt-1.5 w-full" list="bases-sugeridas"
                          maxlength="40" :value="old('base', $vehiculo->base)" placeholder="ej. Coquimbo" />
            <datalist id="bases-sugeridas">
                @foreach (\App\Models\Vehiculo::BASES as $b)
                    <option value="{{ $b }}"></option>
                @endforeach
            </datalist>
            <x-input-hint>Dónde vive el vehículo. Se puede escribir una que no esté en la lista.</x-input-hint>
            <x-input-error :messages="$errors->get('base')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="conductor_nombre" value="Conductor asignado" />
            <x-text-input id="conductor_nombre" name="conductor_nombre" type="text" class="mt-1.5 w-full"
                          maxlength="191" :value="old('conductor_nombre', $vehiculo->conductor_nombre)" />
            <x-input-hint>Déjalo vacío si no está asignado. El estado del vehículo va aparte.</x-input-hint>
            <x-input-error :messages="$errors->get('conductor_nombre')" class="mt-2" />
        </div>
    </div>
</x-seccion>

{{-- Documentos: el motivo del módulo. Cada fecha alimenta el semáforo y el
     aviso automático; vacía = sin dato y sin alerta. --}}
<x-seccion titulo="Documentos y vencimientos">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach (\App\Models\Vehiculo::DOCUMENTOS as $clave => $label)
            <div>
                <x-input-label :for="$clave" :value="$label" />
                <x-text-input :id="$clave" :name="$clave" type="date" class="mt-1.5 w-full"
                              :value="old($clave, $vehiculo->{$clave}?->toDateString())" />
                <x-input-error :messages="$errors->get($clave)" class="mt-2" />
            </div>
        @endforeach
        <div>
            <x-input-label for="extintor_capacidad_kg" value="Capacidad del extintor (kg)" />
            <x-text-input id="extintor_capacidad_kg" name="extintor_capacidad_kg" type="number" step="0.5" min="0"
                          class="mt-1.5 w-full" :value="old('extintor_capacidad_kg', $vehiculo->extintor_capacidad_kg)" />
            <x-input-error :messages="$errors->get('extintor_capacidad_kg')" class="mt-2" />
        </div>
    </div>
    <p class="text-xs text-neutral-500">
        Una fecha vacía no genera alerta. El aviso llega {{ \App\Models\Vehiculo::DIAS_AVISO }} días antes y el día que vence.
    </p>
</x-seccion>

<x-seccion titulo="Dimensiones y capacidades">
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
            <x-input-label for="cilindrada" value="Cilindrada (cc)" />
            <x-text-input id="cilindrada" name="cilindrada" type="number" inputmode="numeric" min="0"
                          class="mt-1.5 w-full" :value="old('cilindrada', $vehiculo->cilindrada)" />
            <x-input-error :messages="$errors->get('cilindrada')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="pbv_kg" value="PBV (kg)" />
            <x-text-input id="pbv_kg" name="pbv_kg" type="number" inputmode="numeric" min="0"
                          class="mt-1.5 w-full" :value="old('pbv_kg', $vehiculo->pbv_kg)" />
            <x-input-error :messages="$errors->get('pbv_kg')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="capacidad_carga_kg" value="Carga (kg)" />
            <x-text-input id="capacidad_carga_kg" name="capacidad_carga_kg" type="number" inputmode="numeric" min="0"
                          class="mt-1.5 w-full" :value="old('capacidad_carga_kg', $vehiculo->capacidad_carga_kg)" />
            <x-input-error :messages="$errors->get('capacidad_carga_kg')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="presion_psi" value="Presión (PSI)" />
            <x-text-input id="presion_psi" name="presion_psi" type="number" inputmode="numeric" min="0"
                          class="mt-1.5 w-full" :value="old('presion_psi', $vehiculo->presion_psi)" />
            <x-input-error :messages="$errors->get('presion_psi')" class="mt-2" />
        </div>
    </div>
</x-seccion>

{{-- Estado del vehículo. Va SEPARADO del conductor a propósito: en la planilla
     "PERDIDA TOTAL" y "VENTA FEBRERO 2023" se escribían en la columna del
     chofer, y con eso no se puede contar la flota. Los campos de la baja
     aparecen solo cuando corresponden. --}}
<x-seccion titulo="Estado del vehículo" x-data="{ estado: '{{ old('estado', $vehiculo->estado ?: \App\Models\Vehiculo::ESTADO_ACTIVO) }}' }">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="estado">Estado <span class="text-red-500">*</span></x-input-label>
            <x-select id="estado" name="estado" class="mt-1.5" required x-model="estado">
                @foreach (\App\Models\Vehiculo::ESTADOS as $valor => $label)
                    <option value="{{ $valor }}" @selected(old('estado', $vehiculo->estado) === $valor)>{{ $label }}</option>
                @endforeach
            </x-select>
            <x-input-error :messages="$errors->get('estado')" class="mt-2" />
        </div>
        <div x-show="estado !== '{{ \App\Models\Vehiculo::ESTADO_ACTIVO }}'" x-cloak>
            <x-input-label for="baja_at" value="Fecha de salida" />
            <x-text-input id="baja_at" name="baja_at" type="date" class="mt-1.5 w-full"
                          :value="old('baja_at', $vehiculo->baja_at?->toDateString())" />
            <x-input-error :messages="$errors->get('baja_at')" class="mt-2" />
        </div>
        <div class="sm:col-span-2" x-show="estado !== '{{ \App\Models\Vehiculo::ESTADO_ACTIVO }}'" x-cloak>
            <x-input-label for="baja_motivo">Por qué sale de la flota <span class="text-red-500">*</span></x-input-label>
            <x-text-input id="baja_motivo" name="baja_motivo" type="text" class="mt-1.5 w-full"
                          maxlength="191" :value="old('baja_motivo', $vehiculo->baja_motivo)"
                          placeholder="ej. Venta febrero 2023 · Pérdida total, indemnización $5.276.236" />
            <x-input-error :messages="$errors->get('baja_motivo')" class="mt-2" />
        </div>
    </div>
</x-seccion>

<x-seccion titulo="Observaciones">
    <div>
        <x-textarea id="observaciones" name="observaciones" rows="3"
                    placeholder="ej. Sin extintor · TAG dado de baja">{{ old('observaciones', $vehiculo->observaciones) }}</x-textarea>
        <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
    </div>
</x-seccion>
