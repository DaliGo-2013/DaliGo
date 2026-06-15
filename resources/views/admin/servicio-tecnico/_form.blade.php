@php
    use Illuminate\Support\Str;

    $o = $orden ?? null;
    $clienteActual = $o?->cliente;
    $clienteActualLabel = $clienteActual
        ? (($clienteActual->rut ? $clienteActual->rut.' — ' : '').$clienteActual->razon_social)
        : '';
@endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    {{-- Cliente: se busca por RUT o razon social contra un endpoint JSON; el id
         elegido viaja en el <input hidden>. cliente_label solo sirve para
         repoblar el texto si la validacion falla (no se persiste). --}}
    <div class="sm:col-span-2"
         x-data="buscadorCliente({
            endpoint: '{{ route('admin.servicio-tecnico.buscar-cliente') }}',
            inicialId: {{ (int) old('cliente_id', $clienteActual?->id ?? 0) }},
            inicialLabel: @js(old('cliente_label', $clienteActualLabel))
         })">
        <x-input-label for="cliente_buscar" value="Cliente (buscar por RUT o nombre)" />

        <input type="hidden" name="cliente_id" :value="clienteId">
        <input type="hidden" name="cliente_label" :value="elegidoLabel">

        <div class="relative mt-1.5">
            <x-text-input id="cliente_buscar" type="text" class="w-full" autocomplete="off"
                placeholder="Escribe RUT o razón social…"
                x-model="term"
                @input.debounce.300ms="buscar()"
                @focus="if (resultados.length) abierto = true"
                @keydown.escape="abierto = false"
                @click.outside="abierto = false" />

            <div x-show="abierto" x-cloak
                 class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                <template x-if="cargando">
                    <div class="px-3.5 py-2.5 text-sm text-neutral-400">Buscando…</div>
                </template>
                <template x-if="!cargando && resultados.length === 0 && term.length >= 2">
                    <div class="px-3.5 py-2.5 text-sm text-neutral-400">Sin resultados para “<span x-text="term"></span>”.</div>
                </template>
                <ul class="max-h-60 divide-y divide-neutral-100 overflow-auto">
                    <template x-for="r in resultados" :key="r.id">
                        <li>
                            <button type="button" @click="elegir(r)"
                                class="block w-full px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50">
                                <span x-text="r.label"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div x-show="clienteId" x-cloak class="mt-2 flex flex-wrap items-center gap-2 text-sm">
            <x-badge>Cliente</x-badge>
            <span class="font-medium text-neutral-800" x-text="elegidoLabel"></span>
            <button type="button" @click="limpiar()" class="text-xs text-neutral-400 underline hover:text-neutral-600">cambiar</button>
        </div>

        <x-input-hint x-show="!clienteId" x-cloak>Opcional. Si el cliente no existe aún, puedes dejarlo en blanco.</x-input-hint>
        <x-input-error :messages="$errors->get('cliente_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="fecha_ingreso" value="Fecha de ingreso" />
        <x-text-input id="fecha_ingreso" class="mt-1.5" type="date" name="fecha_ingreso"
            :value="old('fecha_ingreso', $o?->fecha_ingreso?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
        <x-input-error :messages="$errors->get('fecha_ingreso')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="tipo_equipo" value="Tipo de equipo" />
        <x-select id="tipo_equipo" name="tipo_equipo" class="mt-1.5" required>
            @foreach ($tipos as $t)
                <option value="{{ $t }}" @selected(old('tipo_equipo', $o?->tipo_equipo) === $t)>{{ ucfirst($t) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('tipo_equipo')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="marca" value="Marca" />
        <x-text-input id="marca" class="mt-1.5" type="text" name="marca" :value="old('marca', $o?->marca)" maxlength="191" />
        <x-input-error :messages="$errors->get('marca')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="modelo" value="Modelo" />
        <x-text-input id="modelo" class="mt-1.5" type="text" name="modelo" :value="old('modelo', $o?->modelo)" maxlength="191" />
        <x-input-error :messages="$errors->get('modelo')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="numero_serie" value="N° de serie" />
        <x-text-input id="numero_serie" class="mt-1.5" type="text" name="numero_serie" :value="old('numero_serie', $o?->numero_serie)" maxlength="191" />
        <x-input-error :messages="$errors->get('numero_serie')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="sucursal_id" value="Sucursal de recepción" />
        <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5">
            <option value="">— Sin sucursal —</option>
            @foreach ($sucursales as $s)
                <option value="{{ $s->id }}" @selected((int) old('sucursal_id', $o?->sucursal_id) === $s->id)>{{ $s->nombre }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="tecnico_id" value="Técnico / responsable" />
        <x-select id="tecnico_id" name="tecnico_id" class="mt-1.5">
            <option value="">— Sin asignar —</option>
            @foreach ($tecnicos as $t)
                <option value="{{ $t->id }}" @selected((int) old('tecnico_id', $o?->tecnico_id) === $t->id)>{{ $t->name }}</option>
            @endforeach
        </x-select>
        @if ($tecnicos->isEmpty())
            <x-input-hint>No hay usuarios con rol técnico todavía.</x-input-hint>
        @endif
        <x-input-error :messages="$errors->get('tecnico_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="estado" value="Estado" />
        <x-select id="estado" name="estado" class="mt-1.5" required>
            @foreach ($estados as $e)
                <option value="{{ $e }}" @selected(old('estado', $o?->estado ?? 'recibido') === $e)>{{ Str::headline($e) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('estado')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="fecha_entrega" value="Fecha de entrega" />
        <x-text-input id="fecha_entrega" class="mt-1.5" type="date" name="fecha_entrega"
            :value="old('fecha_entrega', $o?->fecha_entrega?->format('Y-m-d'))" />
        <x-input-hint>Opcional. Se completa al entregar el equipo.</x-input-hint>
        <x-input-error :messages="$errors->get('fecha_entrega')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="falla_reportada" value="Falla reportada" />
        <x-textarea id="falla_reportada" class="mt-1.5" name="falla_reportada" rows="2">{{ old('falla_reportada', $o?->falla_reportada) }}</x-textarea>
        <x-input-error :messages="$errors->get('falla_reportada')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="accesorios" value="Accesorios recibidos" />
        <x-textarea id="accesorios" class="mt-1.5" name="accesorios" rows="2">{{ old('accesorios', $o?->accesorios) }}</x-textarea>
        <x-input-hint>Ej.: cable, manguera, filtro, control.</x-input-hint>
        <x-input-error :messages="$errors->get('accesorios')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="observaciones" value="Observaciones" />
        <x-textarea id="observaciones" class="mt-1.5" name="observaciones" rows="2">{{ old('observaciones', $o?->observaciones) }}</x-textarea>
        <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
    </div>
</div>
