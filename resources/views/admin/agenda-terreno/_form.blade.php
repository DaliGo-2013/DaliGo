{{-- Campos compartidos de crear/editar trabajo de la agenda de terreno.
     $t = trabajo o null (crear). Requiere estar dentro del x-data
     agendaTerrenoForm (buscador de cliente + detalle del servicio). --}}
@php $t = $trabajo ?? null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    {{-- Tipo de trabajo --}}
    <div>
        <x-input-label for="tipo">Tipo de trabajo <span class="text-red-500">*</span></x-input-label>
        <x-select id="tipo" name="tipo" class="mt-1.5" required>
            <option value="">— Selecciona —</option>
            @foreach ($tipos as $tp)
                <option value="{{ $tp }}" @selected(old('tipo', $t?->tipo ?? request('tipo')) === $tp)>{{ \App\Models\AgendaTrabajo::TIPO_ETIQUETAS[$tp] }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
    </div>

    {{-- Fecha desde (una SOLICITUD del cliente aún no la tiene: se pone al
         coordinar). Se puede prellenar desde el calendario (?fecha=&hora=). --}}
    <div>
        <x-input-label for="fecha">Fecha (desde) <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="fecha" name="fecha" type="date" class="mt-1.5 w-full"
            :value="old('fecha', $t?->fecha?->format('Y-m-d') ?? request('fecha'))" />
        @if ($t?->fecha_preferida)
            <x-input-hint>El cliente prefiere: <span class="font-medium">{{ $t->fecha_preferida->format('d-m-Y') }}</span>.</x-input-hint>
        @endif
        <input type="hidden" name="fecha_preferida" value="{{ old('fecha_preferida', $t?->fecha_preferida?->format('Y-m-d')) }}">
        <x-input-error :messages="$errors->get('fecha')" class="mt-2" />
    </div>

    {{-- Fecha hasta (opcional): para viajes de varios días (ej. Puerto Montt del
         7 al 10). Vacío = trabajo de un solo día. --}}
    <div>
        <x-input-label for="fecha_fin" value="Hasta (opcional)" />
        <x-text-input id="fecha_fin" name="fecha_fin" type="date" class="mt-1.5 w-full"
            :value="old('fecha_fin', $t?->fecha_fin?->format('Y-m-d'))" />
        <x-input-hint>Solo si el trabajo/viaje abarca varios días. Esos días quedan ocupados.</x-input-hint>
        <x-input-error :messages="$errors->get('fecha_fin')" class="mt-2" />
    </div>

    {{-- Hora en FRANJAS de 2 horas (deja holgura para viajar entre trabajos). La
         usa el calendario para ubicar el trabajo en su franja del día. --}}
    @php
        $franjas = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
        $horaActual = old('hora', $t?->hora_corta ?? request('hora'));
        $horaFinActual = old('hora_fin', $t?->hora_fin_corta);
    @endphp
    <div>
        <x-input-label for="hora" value="Hora (desde)" />
        <x-select id="hora" name="hora" class="mt-1.5 w-full">
            <option value="">— Sin hora —</option>
            {{-- Conserva una hora previa que no calce con las franjas (datos antiguos). --}}
            @if ($horaActual && ! in_array($horaActual, $franjas, true))
                <option value="{{ $horaActual }}" selected>{{ $horaActual }} (actual)</option>
            @endif
            @foreach ($franjas as $f)
                <option value="{{ $f }}" @selected((string) $horaActual === $f)>{{ $f }} hs</option>
            @endforeach
        </x-select>
        <x-input-hint>Franjas de 2 h por los viajes. Opcional: «Sin hora» si aún no se define.</x-input-hint>
        <x-input-error :messages="$errors->get('hora')" class="mt-2" />
    </div>

    {{-- Hora hasta (opcional): para trabajos de día completo (ej. instalación de
         08:00 a 18:00). Vacío = una sola franja. --}}
    <div>
        <x-input-label for="hora_fin" value="Hora fin (opcional)" />
        <x-select id="hora_fin" name="hora_fin" class="mt-1.5 w-full">
            <option value="">— Una sola franja —</option>
            @if ($horaFinActual && ! in_array($horaFinActual, $franjas, true))
                <option value="{{ $horaFinActual }}" selected>{{ $horaFinActual }} (actual)</option>
            @endif
            @foreach ($franjas as $f)
                <option value="{{ $f }}" @selected((string) $horaFinActual === $f)>{{ $f }} hs</option>
            @endforeach
        </x-select>
        <x-input-hint>Para un trabajo que dura varias horas (ej. 08:00 a 18:00).</x-input-hint>
        <x-input-error :messages="$errors->get('hora_fin')" class="mt-2" />
    </div>

    {{-- Servicio del catálogo (opcional) + detalle en vivo --}}
    <div class="sm:col-span-2">
        <x-input-label for="servicio_terreno_id" value="Servicio del catálogo" />
        <x-select id="servicio_terreno_id" name="servicio_terreno_id" class="mt-1.5" x-model="servicioId">
            <option value="">— Trabajo fuera de tarifa / solo descripción —</option>
            @foreach ($servicios as $s)
                <option value="{{ $s->id }}" @selected((string) old('servicio_terreno_id', $t?->servicio_terreno_id) === (string) $s->id)>
                    {{ $s->nombre }}@if ($s->valor_uf_fmt) · {{ $s->valor_uf_fmt }} UF @endif @unless ($s->activo) (inactivo) @endunless
                </option>
            @endforeach
        </x-select>
        <template x-if="servicioDetalle">
            <div class="mt-2 rounded-xl bg-neutral-50 p-3 text-sm text-neutral-600">
                <p>
                    <span class="font-medium text-neutral-900" x-text="servicioDetalle.valor_uf ? servicioDetalle.valor_uf + ' UF neto' : ''"></span>
                    <span x-show="servicioDetalle.duracion" x-text="' · ' + servicioDetalle.duracion"></span>
                </p>
                <p x-show="servicioDetalle.incluye" class="mt-1"><span class="font-medium">Incluye:</span> <span x-text="servicioDetalle.incluye"></span></p>
                <p x-show="servicioDetalle.observaciones" class="mt-1 text-neutral-500" x-text="servicioDetalle.observaciones"></p>
            </div>
        </template>
        <x-input-error :messages="$errors->get('servicio_terreno_id')" class="mt-2" />
    </div>

    {{-- Cliente: buscador por RUT/razón social; al elegir rellena todo (editable). --}}
    <div class="sm:col-span-2">
        <x-input-label for="buscador_cliente" value="Buscar cliente (RUT o razón social)" />
        <div class="relative mt-1.5">
            <x-text-input id="buscador_cliente" type="text" class="w-full" autocomplete="off"
                placeholder="Ej. 76.543.210-9 o Aguas JB" x-model="rutBusqueda"
                x-on:input.debounce.300ms="buscarCliente()"
                x-on:keydown.escape="abierto = false"
                x-on:click.outside="abierto = false" />
            <div x-show="abierto && (buscando || resultados.length)" x-cloak
                 class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                <ul class="max-h-60 divide-y divide-neutral-100 overflow-auto">
                    <template x-for="r in resultados" :key="r.id">
                        <li>
                            <button type="button" x-on:click="elegirCliente(r)"
                                class="block w-full px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50"
                                x-text="r.label"></button>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
        <x-input-hint>Si ya existe, elígelo y se rellenan sus datos; si no, escríbelos abajo.</x-input-hint>
    </div>

    <input type="hidden" name="cliente_id" :value="clienteId">

    <div>
        <x-input-label for="cliente_nombre">Cliente / empresa <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 w-full" required
            maxlength="191" :value="old('cliente_nombre', $t?->cliente_nombre)" />
        <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="cliente_rut">RUT <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 w-full" required
            maxlength="20" :value="old('cliente_rut', $t?->cliente_rut)" />
        <x-input-error :messages="$errors->get('cliente_rut')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="cliente_telefono">Teléfono <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 w-full" required
            maxlength="30" :value="old('cliente_telefono', $t?->cliente_telefono)" />
        <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="cliente_email">Correo <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="cliente_email" name="cliente_email" type="email" class="mt-1.5 w-full" required
            maxlength="191" :value="old('cliente_email', $t?->cliente_email)" />
        <x-input-error :messages="$errors->get('cliente_email')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="direccion">Dirección (donde se hace el trabajo) <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="direccion" name="direccion" type="text" class="mt-1.5 w-full" required
            maxlength="191" :value="old('direccion', $t?->direccion)" />
        <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="ciudad">Ciudad <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="ciudad" name="ciudad" type="text" class="mt-1.5 w-full" required
            maxlength="191" :value="old('ciudad', $t?->ciudad)" />
        <x-input-error :messages="$errors->get('ciudad')" class="mt-2" />
    </div>

    {{-- Técnico industrial asignado. Si hay UN solo técnico (el caso normal:
         Carlos Tablante), queda pre-seleccionado por defecto al agendar; con
         varios, o al editar, respeta lo ya elegido. Igual se puede dejar
         «Sin asignar». --}}
    @php
        $tecnicoDefault = old(
            'tecnico_id',
            $t?->tecnico_id ?? ($tecnicos->count() === 1 ? $tecnicos->first()->id : '')
        );
    @endphp
    <div>
        <x-input-label for="tecnico_id" value="Técnico asignado" />
        <x-select id="tecnico_id" name="tecnico_id" class="mt-1.5">
            <option value="">— Sin asignar —</option>
            @foreach ($tecnicos as $tec)
                <option value="{{ $tec->id }}" @selected((string) $tecnicoDefault === (string) $tec->id)>{{ $tec->name }}</option>
            @endforeach
        </x-select>
        @if ($tecnicos->isEmpty())
            <x-input-hint>No hay usuarios con rol «técnico industrial» todavía (se crean en Usuarios).</x-input-hint>
        @elseif ($tecnicos->count() === 1)
            <x-input-hint>Pre-seleccionado {{ $tecnicos->first()->name }} (único técnico industrial). Puedes cambiarlo si hace falta.</x-input-hint>
        @endif
        <x-input-error :messages="$errors->get('tecnico_id')" class="mt-2" />
    </div>

    {{-- Estado (solo al editar) --}}
    @if ($t)
        <div>
            <x-input-label for="estado">Estado <span class="text-red-500">*</span></x-input-label>
            <x-select id="estado" name="estado" class="mt-1.5" required>
                @foreach ($estados as $e)
                    <option value="{{ $e }}" @selected(old('estado', $t->estado) === $e)>{{ ucfirst($e) }}</option>
                @endforeach
            </x-select>
            <x-input-error :messages="$errors->get('estado')" class="mt-2" />
        </div>
    @endif

    {{-- Qué hay que hacer --}}
    <div class="sm:col-span-2">
        <x-input-label for="descripcion">Trabajo a realizar (detalle) <span class="text-red-500">*</span></x-input-label>
        <x-textarea id="descripcion" name="descripcion" rows="3" class="mt-1.5" required
            placeholder="Ej. Mantención full planta 1T; revisar bomba que pierde presión; llevar membranas.">{{ old('descripcion', $t?->descripcion) }}</x-textarea>
        <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
    </div>

    @if ($t)
        <div class="sm:col-span-2">
            <x-input-label for="notas_tecnico" value="Notas del técnico (al cerrar)" />
            <x-textarea id="notas_tecnico" name="notas_tecnico" rows="2" class="mt-1.5"
                placeholder="Qué se hizo, repuestos usados, pendientes…">{{ old('notas_tecnico', $t?->notas_tecnico) }}</x-textarea>
            <x-input-error :messages="$errors->get('notas_tecnico')" class="mt-2" />
        </div>
    @endif
</div>
