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
     aviso automático; vacía = sin dato y sin alerta.

     LA FOTO VA JUNTO A SU FECHA (pedido del dueño 11-08-2026: «necesito un botón
     de guardar para guardar las fotos»). Un documento son DOS datos —la foto y
     hasta cuándo vale— y vivían en pantallas distintas: la foto se subía en la
     ficha y la fecha había que escribirla acá, así que cargar un permiso de
     circulación eran dos viajes. Ahora el «Guardar cambios» del final deja los
     dos. La subida de a una de la ficha sigue existiendo y no se toca: esa es
     para el teléfono, parado al lado del camión. --}}
@php
    $respaldosPorDoc = $vehiculo->exists
        ? $vehiculo->respaldos->sortByDesc('id')->groupBy('documento')
        : collect();
@endphp
<x-seccion titulo="Documentos y vencimientos">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {{-- El catálogo COMPLETO: los cinco de la ley (que son columnas) más los que
             se hayan creado desde Tipos de documento. Los creados guardan su fecha en
             `doc_creado[{id}]`; de acá para abajo se ven y se cargan igual. --}}
        @foreach (\App\Models\Vehiculo::catalogoDocumentos() as $clave => $label)
            {{-- Lo que a ESTE vehículo no le toca, no se pide. Un semirremolque no rinde
                 emisiones y una póliza de carga peligrosa puede ser solo de los camiones:
                 ofrecer el campo igual invita a escribir una fecha que la ficha después
                 va a mostrar como «No aplica», o sea un dato que se guarda y no sirve.

                 En el ALTA se muestran todos: el vehículo todavía no existe, así que no
                 hay tipo contra el cual decidir (y el selector de tipo está en esta misma
                 pantalla, sin recargar). --}}
            @continue($vehiculo->exists && ! $vehiculo->documentoAplica($clave))
            @php
                $respaldo = $respaldosPorDoc->get($clave)?->first();
                $idCreado = \App\Models\VehiculoDocumentoTipo::idDeClave($clave);
                $campoFecha = $idCreado === null ? $clave : "doc_creado[$idCreado]";
                $errorFecha = $idCreado === null ? $clave : "doc_creado.$idCreado";
                $valorFecha = $vehiculo->exists ? $vehiculo->venceDe($clave)?->toDateString() : null;
            @endphp
            <div>
                <x-input-label :for="$campoFecha" :value="$label" />
                <x-text-input :id="$campoFecha" :name="$campoFecha" type="date" class="mt-1.5 w-full"
                              :value="old($errorFecha, $valorFecha)" />
                <x-input-error :messages="$errors->get($errorFecha)" class="mt-2" />

                {{-- Sin `required` y sin auto-enviar, al revés que en la ficha: acá
                     el archivo es opcional (casi siempre se viene a corregir una
                     fecha) y el envío lo manda el botón del final, que es justo lo
                     que se pidió. --}}
                <div class="mt-1.5">
                    <x-archivo-input :name="'respaldos['.$clave.']'"
                                     accept="image/jpeg,image/png,image/webp,application/pdf"
                                     capture="environment"
                                     :texto="$respaldo ? 'Reemplazar la foto' : 'Subir la foto'"
                                     :vacio="$respaldo
                                        ? 'Hay una foto cargada · '.$respaldo->tamano_kb.' KB'
                                        : 'Sin foto · se comprime sola, queda liviana'" />
                    <x-input-error :messages="$errors->get('respaldos.'.$clave)" class="mt-2" />
                </div>
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
        Una fecha vacía no genera alerta. El aviso llega {{ \App\Models\Vehiculo::diasAviso() }} días antes y el día que vence.
        Las fotos se guardan con el botón del final, junto con el resto de los cambios.
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

{{-- Caja de carga: lo que necesita el Simulador de carga. Va en su propia sección
     porque no es dato administrativo del vehículo sino del ESPACIO, y porque el
     aviso de "medir por dentro" tiene que leerse antes de escribir los números:
     la diferencia entre exterior e interior es 10-20% del volumen, o sea la
     diferencia entre que la carga entre o quede en el andén. --}}
<x-seccion titulo="Caja de carga">
    <p class="mb-3 text-sm text-neutral-500">
        Medidas <strong>útiles, por dentro</strong> de la caja — no las del folleto. Con las tres cargadas, el
        vehículo aparece en el <a href="{{ route('admin.carga.index') }}" class="font-medium text-brand-600 hover:text-brand-700">Simulador de carga</a>.
    </p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <x-input-label for="largo_util_cm" value="Largo útil (cm)" />
            <x-text-input id="largo_util_cm" name="largo_util_cm" type="number" inputmode="numeric" min="0" max="20000"
                          class="mt-1.5 w-full" :value="old('largo_util_cm', $vehiculo->largo_util_cm)" placeholder="ej. 430" />
            <x-input-error :messages="$errors->get('largo_util_cm')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="ancho_util_cm" value="Ancho útil (cm)" />
            <x-text-input id="ancho_util_cm" name="ancho_util_cm" type="number" inputmode="numeric" min="0" max="20000"
                          class="mt-1.5 w-full" :value="old('ancho_util_cm', $vehiculo->ancho_util_cm)" placeholder="ej. 200" />
            <x-input-error :messages="$errors->get('ancho_util_cm')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="alto_util_cm" value="Alto útil (cm)" />
            <x-text-input id="alto_util_cm" name="alto_util_cm" type="number" inputmode="numeric" min="0" max="20000"
                          class="mt-1.5 w-full" :value="old('alto_util_cm', $vehiculo->alto_util_cm)" placeholder="ej. 220" />
            <x-input-error :messages="$errors->get('alto_util_cm')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="pasillo_cm" value="Pasillo a reservar (cm)" />
            <x-text-input id="pasillo_cm" name="pasillo_cm" type="number" inputmode="numeric" min="0" max="500"
                          class="mt-1.5 w-full" :value="old('pasillo_cm', $vehiculo->pasillo_cm)" placeholder="0" />
            <x-input-hint>Paso que la bodega necesita para cargar. Se descuenta del largo.</x-input-hint>
            <x-input-error :messages="$errors->get('pasillo_cm')" class="mt-2" />
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
