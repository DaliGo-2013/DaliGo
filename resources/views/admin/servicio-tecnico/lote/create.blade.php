<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Ingreso por lote" subtitle="Retiro en ruta · varias máquinas de una empresa.">
            <x-slot name="action">
                <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver al listado">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-xl space-y-5 px-4 sm:px-6">
            <x-status-alert :status="session('status')" />
            <x-produccion.indicador-red />

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    Revisa los datos: hay {{ $errors->count() }} campo(s) con problemas más abajo.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.servicio-tecnico.lote.store') }}" enctype="multipart/form-data"
                  x-data="loteServicioForm({ endpointProducto: '{{ route('admin.servicio-tecnico.lote.buscar-producto') }}', endpointCliente: '{{ route('admin.servicio-tecnico.lote.buscar-cliente') }}' })"
                  class="space-y-5" data-una-vez>
                @csrf

                {{-- Empresa (una vez) --}}
                <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Empresa</h3>
                    <input type="hidden" name="cliente_id" :value="clienteId">
                    <div class="space-y-4">
                        {{-- RUT (buscador) --}}
                        <div class="relative">
                            <x-input-label for="cliente_rut">RUT de la empresa <span class="text-red-500">*</span></x-input-label>
                            <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 w-full" autocomplete="off" required
                                placeholder="Ej. 76.123.456-7" x-model="rut"
                                x-on:input.debounce.300ms="buscarEmpresa()"
                                x-on:keydown.escape="empresaAbierto = false"
                                x-on:click.outside="empresaAbierto = false" />
                            <div x-show="empresaAbierto && (empresaBuscando || empresaResultados.length)" x-cloak
                                 class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                                <template x-if="empresaBuscando && empresaResultados.length === 0">
                                    <div class="px-3.5 py-2.5 text-sm text-neutral-400">Buscando…</div>
                                </template>
                                <ul class="max-h-60 divide-y divide-neutral-100 overflow-auto">
                                    <template x-for="r in empresaResultados" :key="r.id">
                                        <li>
                                            <button type="button" x-on:click="elegirEmpresa(r)"
                                                class="block w-full px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50"
                                                x-text="r.label"></button>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                            <x-input-hint>Si ya existe, elígela de la lista; si no, escribe el RUT y el nombre.</x-input-hint>
                            <x-input-error :messages="$errors->get('cliente_rut')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="cliente_nombre">Nombre / razón social <span class="text-red-500">*</span></x-input-label>
                            <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 w-full" required
                                maxlength="191" placeholder="Empresa" x-model="nombre" />
                            <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="cliente_email" value="Correo (opcional)" />
                                <x-text-input id="cliente_email" name="cliente_email" type="email" class="mt-1.5 w-full"
                                    maxlength="191" placeholder="empresa@correo.cl" x-model="email" />
                                <x-input-hint>Para el aviso de recepción del lote.</x-input-hint>
                                <x-input-error :messages="$errors->get('cliente_email')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="cliente_telefono" value="Teléfono (opcional)" />
                                <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 w-full"
                                    maxlength="30" placeholder="+56 9 1234 5678" x-model="telefono" />
                                <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Datos del retiro + valores por defecto del lote --}}
                <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm space-y-4">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Datos del retiro</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="origen_ciudad">Origen (ciudad) <span class="text-red-500">*</span></x-input-label>
                            <x-select id="origen_ciudad" name="origen_ciudad" class="mt-1.5" required>
                                <option value="">— Elige —</option>
                                @foreach ($ciudades as $c)
                                    <option value="{{ $c }}" @selected(old('origen_ciudad') === $c)>{{ $c }}</option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errors->get('origen_ciudad')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="sucursal_id">Destino (recepción) <span class="text-red-500">*</span></x-input-label>
                            <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5" required>
                                @foreach ($sucursales as $s)
                                    <option value="{{ $s->id }}" @selected((string) old('sucursal_id', optional($sucursalCentral)->id) === (string) $s->id)>{{ $s->nombre }}</option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fecha_ingreso" value="Fecha de retiro" />
                            <x-text-input id="fecha_ingreso" name="fecha_ingreso" type="date" class="mt-1.5 w-full"
                                :value="old('fecha_ingreso', now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('fecha_ingreso')" class="mt-2" />
                        </div>
                    </div>

                    <div class="rounded-xl bg-neutral-50 p-3">
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Valores por defecto (para todas las máquinas)</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="tipo_default" value="Tipo de equipo" />
                                <x-select id="tipo_default" name="tipo_default" class="mt-1.5">
                                    @foreach ($tipos as $t)
                                        <option value="{{ $t }}" @selected(old('tipo_default', 'dispensador') === $t)>{{ \App\Models\OrdenServicio::etiquetaTipo($t) }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                            <div>
                                <x-input-label for="facturacion_default" value="Condición" />
                                <x-select id="facturacion_default" name="facturacion_default" class="mt-1.5">
                                    @foreach ($facturaciones as $f)
                                        <option value="{{ $f }}" @selected(old('facturacion_default', 'reparacion') === $f)>{{ ucfirst($f) }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                        </div>
                        <div class="mt-4">
                            <x-input-label for="falla_default" value="Falla común (se aplica a todas)" />
                            <x-textarea id="falla_default" name="falla_default" rows="2" class="mt-1.5"
                                placeholder="Ej. No enfría, no calienta">{{ old('falla_default') }}</x-textarea>
                            <x-input-hint>Si una máquina tiene otra falla, la ajusta el técnico al revisar.</x-input-hint>
                        </div>
                    </div>
                </div>

                {{-- Máquinas (filas) --}}
                <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                            Máquinas (<span x-text="maquinas.length"></span>)
                        </h3>
                        <button type="button" x-on:click="agregar()"
                            class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-sm font-medium text-neutral-700 shadow-sm hover:bg-neutral-50">
                            <x-icon.plus class="h-4 w-4" /> Agregar máquina
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(m, i) in maquinas" :key="i">
                            <div class="rounded-xl border border-neutral-200 p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-neutral-500">Máquina <span x-text="i + 1"></span></span>
                                    <button type="button" x-on:click="quitar(i)" class="rounded-lg p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600" title="Quitar">
                                        <x-icon.trash class="h-4 w-4" />
                                    </button>
                                </div>

                                {{-- Código Dali (obligatorio, autocompletado) --}}
                                <div class="relative" x-on:click.outside="filaActiva === i && cerrar()">
                                    <label class="mb-0.5 block text-xs text-neutral-500">Código (producto Dali) *</label>
                                    <input type="hidden" :name="`maquinas[${i}][producto_id]`" x-model="m.producto_id">
                                    <input type="text" x-model="m.producto_label" autocomplete="off"
                                        placeholder="Código o nombre del equipo"
                                        x-on:input.debounce.250ms="buscar(i)" x-on:focus="buscar(i)"
                                        x-on:keydown.escape="cerrar()"
                                        class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                                        :class="filaIncompleta(m) ? 'border-red-300' : ''">
                                    <div x-show="filaActiva === i && (buscando || sugerencias.length)" x-cloak
                                        class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                                        <template x-if="buscando && sugerencias.length === 0">
                                            <div class="px-3.5 py-2.5 text-sm text-neutral-400">Buscando…</div>
                                        </template>
                                        <ul class="max-h-56 divide-y divide-neutral-100 overflow-auto">
                                            <template x-for="(s, si) in sugerencias" :key="si">
                                                <li>
                                                    <button type="button" x-on:click="elegir(i, s)"
                                                        class="block w-full px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50">
                                                        <span class="font-mono text-xs text-neutral-400" x-text="s.sku"></span>
                                                        <span x-text="' ' + s.nombre"></span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                    <p x-show="filaIncompleta(m)" x-cloak class="mt-1 text-xs text-red-500">Elige el código del catálogo.</p>
                                </div>

                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="mb-0.5 block text-xs text-neutral-500">N° de serie</label>
                                        <input type="text" x-model="m.numero_serie" :name="`maquinas[${i}][numero_serie]`" maxlength="191"
                                            class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    </div>
                                    <div>
                                        <label class="mb-0.5 block text-xs text-neutral-500">Modelo (opcional)</label>
                                        <input type="text" x-model="m.modelo" :name="`maquinas[${i}][modelo]`" maxlength="191"
                                            class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    </div>
                                </div>

                                {{-- Foto de respaldo (opcional, se comprime en el navegador) --}}
                                <div class="mt-2">
                                    <label class="mb-0.5 block text-xs text-neutral-500">Foto de respaldo</label>
                                    <input type="file" :name="`maquinas[${i}][foto]`" accept="image/*" capture="environment"
                                        x-on:change="fotoInput(i, $event.target)"
                                        class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-700">
                                    <p x-show="m.foto_nombre" x-cloak class="mt-1 text-xs text-green-600" x-text="'✓ ' + m.foto_nombre"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <x-primary-button class="w-full justify-center py-3 text-base">Guardar lote</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
