<x-app-layout>
    @php
        $esReparacion = $orden->condicion_efectiva === 'reparacion';
        $equipo = collect([
            ucfirst($orden->tipo_equipo),
            $orden->producto?->sku,
            $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
        ])->filter()->implode(' · ');

        // El precio viaja OCULTO (se ingresa en Cotización): así re-guardar el
        // parte del técnico no borra lo que se cotizó.
        $repuestosInit = $orden->repuestos->map(fn ($r) => [
            'nombre' => $r->nombre,
            'cantidad' => $r->cantidad,
            'precio_unitario' => $r->precio_unitario,
        ])->values();
    @endphp

    <x-slot name="header">
        <x-page-header :title="'Parte del técnico · '.$orden->folio" :subtitle="$orden->cliente_nombre.($equipo ? ' · '.$equipo : '')">
            <x-slot name="action">
                <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver al listado">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8"
             x-data="{ editando: {{ $errors->any() ? 'true' : 'false' }} }">
            @include('admin.servicio-tecnico._tabs', ['activa' => 'tecnico'])

            <x-status-alert :status="session('status')" />

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                {{-- Resumen de la recepcion (solo lectura, para contexto del tecnico). --}}
                <div class="mb-6 rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm">
                    <p class="font-medium text-neutral-900">{{ $orden->cliente_nombre }} · {{ $orden->cliente_rut }}@if ($orden->cliente_telefono) · {{ $orden->cliente_telefono }}@endif</p>
                    @if ($equipo)
                        <p class="mt-0.5 text-neutral-500">{{ $equipo }}</p>
                    @endif
                    @if ($orden->falla_reportada)
                        <p class="mt-2 text-neutral-700"><span class="font-medium">Falla reportada:</span> {{ $orden->falla_reportada }}</p>
                    @endif
                    <p class="mt-2">
                        <x-badge :variant="$esReparacion ? 'brand' : 'neutral'">{{ $esReparacion ? 'Reparación' : 'Garantía' }}</x-badge>
                        @unless ($esReparacion)
                            <span class="ml-1 text-xs text-neutral-500">Garantía vigente: la reparación no se cobra.</span>
                        @endunless
                    </p>
                    <p class="mt-2 text-xs text-neutral-500">
                        Los precios (repuestos, mano de obra y total) se ingresan en la pestaña
                        <a href="{{ route('admin.servicio-tecnico.cotizacion', $orden) }}" class="font-medium text-brand-600 hover:text-brand-700">Cotización</a>.
                    </p>
                </div>

                {{-- ===================== INFORME (solo lectura) ===================== --}}
                @php
                    $trabajoTxt = $orden->trabajo_realizado;
                    $causaTxt = filled($orden->causa_falla) ? \App\Models\OrdenServicio::CAUSA_FALLA_ETIQUETAS[$orden->causa_falla] : null;
                @endphp
                <div x-show="!editando">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Detalle del trabajo realizado</h3>
                        <x-secondary-button type="button" x-on:click="editando = true">
                            <x-icon.pencil class="h-4 w-4" /> Editar
                        </x-secondary-button>
                    </div>

                    <dl class="divide-y divide-neutral-100 rounded-xl border border-neutral-200 text-sm">
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-neutral-500">Estado / etapa</dt>
                            <dd class="text-right"><x-badge variant="neutral">{{ \Illuminate\Support\Str::headline($orden->estado) }}</x-badge></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-neutral-500">Trabajo realizado</dt>
                            <dd class="text-right text-neutral-900">{{ $trabajoTxt ?: '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-neutral-500">Causa de la falla</dt>
                            <dd class="text-right text-neutral-900">{{ $causaTxt ?: 'Sin determinar' }}</dd>
                        </div>
                        @if ($orden->es_propia)
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <dt class="text-neutral-500">Categoría (reventa)</dt>
                                <dd class="text-right text-neutral-900">{{ $orden->categoria ? \App\Models\OrdenServicio::CATEGORIA_ETIQUETAS[$orden->categoria] : '—' }}</dd>
                            </div>
                        @endif
                        <div class="px-4 py-3">
                            <dt class="mb-1.5 text-neutral-500">Repuestos usados</dt>
                            <dd>
                                @forelse ($orden->repuestos as $r)
                                    <div class="flex items-center justify-between py-0.5 text-neutral-900">
                                        <span>{{ $r->nombre }}</span>
                                        <span class="text-neutral-400">× {{ $r->cantidad }}</span>
                                    </div>
                                @empty
                                    <span class="text-neutral-400">Sin repuestos registrados.</span>
                                @endforelse
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-neutral-500">Fecha de aviso</dt>
                            <dd class="text-right text-neutral-900">{{ $orden->fecha_aviso?->format('d-m-Y') ?: '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-neutral-500">Fecha de retiro</dt>
                            <dd class="text-right text-neutral-900">{{ $orden->fecha_retiro?->format('d-m-Y') ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- ===================== EDICIÓN (formulario) ===================== --}}
                <form x-show="editando" x-cloak id="reparacion-form" method="POST" action="{{ route('admin.servicio-tecnico.reparacion.guardar', $orden) }}"
                    class="space-y-6" data-una-vez
                    x-data="reparacionForm({ repuestos: @js($repuestosInit), manoObra: {{ (int) ($orden->mano_obra ?? 0) }}, endpointRepuestos: '{{ route('admin.servicio-tecnico.buscar-repuesto') }}', precioHora: {{ (int) ($precioHoraServicio ?? 0) }}, descuentoPct: {{ (int) old('descuento_pct', $orden->descuento_pct ?? 0) }} })">
                    @csrf
                    @method('PUT')

                    {{-- Estado / etapa --}}
                    <div>
                        <x-input-label for="estado">Estado / etapa <span class="text-red-500">*</span></x-input-label>
                        <x-select id="estado" name="estado" class="mt-1.5" required>
                            @foreach ($estados as $e)
                                <option value="{{ $e }}" @selected(old('estado', $orden->estado) === $e)>{{ \Illuminate\Support\Str::headline($e) }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('estado')" class="mt-2" />
                    </div>

                    {{-- Trabajo realizado: respuestas FIJAS del historial (el técnico
                         solo elige, no escribe). Agrupadas por resultado. Si la orden
                         ya trae un texto histórico que no está en la lista, se preserva
                         como opción seleccionada para no perderlo. --}}
                    @php
                        $trabajoActual = old('trabajo_realizado', $orden->trabajo_realizado);
                        $opcionesTrabajo = collect($respuestasTrabajo)->flatten()->all();
                        $trabajoFueraDeLista = filled($trabajoActual) && ! in_array($trabajoActual, $opcionesTrabajo, true);
                    @endphp
                    <div x-data="{ mapa: @js($tiemposMap), valorHora: {{ (int) ($precioHoraServicio ?? 0) }}, trabajo: @js(old('trabajo_realizado', $orden->trabajo_realizado)) }">
                        <x-input-label for="trabajo_realizado" value="Trabajo realizado" />
                        <x-select id="trabajo_realizado" name="trabajo_realizado" class="mt-1.5" x-model="trabajo">
                            <option value="">— Selecciona —</option>
                            @if ($trabajoFueraDeLista)
                                {{-- Valor histórico (texto libre anterior): se conserva. --}}
                                <option value="{{ $trabajoActual }}" selected>{{ $trabajoActual }}</option>
                            @endif
                            @foreach ($respuestasTrabajo as $grupo => $opciones)
                                <optgroup label="{{ $grupo }}">
                                    @foreach ($opciones as $op)
                                        <option value="{{ $op }}" @selected($trabajoActual === $op)>{{ $op }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </x-select>
                        <x-input-hint>Elige la respuesta que más se acerque al trabajo hecho.</x-input-hint>
                        {{-- Mano de obra FIJA por el trabajo: informativa (la fija jefatura). --}}
                        <div class="mt-2 text-sm" x-cloak>
                            <template x-if="trabajo && mapa[trabajo] !== undefined">
                                <p class="rounded-lg bg-neutral-50 px-3 py-2 text-neutral-600">
                                    Tiempo estándar: <span class="font-medium text-neutral-900" x-text="String(mapa[trabajo]).replace('.', ',')"></span> h
                                    → mano de obra <span class="font-medium text-neutral-900" x-text="'$' + Math.round(mapa[trabajo] * valorHora).toLocaleString('es-CL')"></span>
                                    <span class="text-neutral-400">· la fija jefatura, no se edita</span>
                                </p>
                            </template>
                            <template x-if="trabajo && mapa[trabajo] === undefined">
                                <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-700">
                                    Este trabajo no tiene tiempo estándar → mano de obra $0. Jefatura puede agregarlo en «Costos generales de reparación».
                                </p>
                            </template>
                        </div>
                        <x-input-error :messages="$errors->get('trabajo_realizado')" class="mt-2" />
                    </div>

                    {{-- Causa de la falla (diagnóstico del técnico): alimenta el
                         indicador del informe para reforzar capacitación al cliente.
                         OBLIGATORIA al cerrar como «Reparado» o «Sin solución». --}}
                    <div x-data="{
                            exige: false,
                            init() {
                                const sel = document.getElementById('estado');
                                const set = () => { this.exige = !!sel && ['reparado', 'sin_solucion'].includes(sel.value); };
                                set();
                                if (sel) sel.addEventListener('change', set);
                            },
                         }">
                        <x-input-label for="causa_falla">Causa de la falla <span x-show="exige" class="text-red-500">*</span></x-input-label>
                        <x-select id="causa_falla" name="causa_falla" class="mt-1.5" x-bind:required="exige">
                            <option value="">Sin determinar</option>
                            @foreach ($causasFalla as $c)
                                <option value="{{ $c }}" @selected(old('causa_falla', $orden->causa_falla) === $c)>{{ \App\Models\OrdenServicio::CAUSA_FALLA_ETIQUETAS[$c] }}</option>
                            @endforeach
                        </x-select>
                        <x-input-hint>¿La máquina falló por mal uso del cliente, desgaste normal o defecto de fábrica?</x-input-hint>
                        <x-input-hint x-show="exige" x-cloak>Obligatoria para cerrar la orden como «Reparado» o «Sin solución» (diagnóstico final).</x-input-hint>
                        <x-input-error :messages="$errors->get('causa_falla')" class="mt-2" />
                    </div>

                    {{-- Categoría de cierre: SOLO para máquinas propias (IMP. DALI). --}}
                    @if ($orden->es_propia)
                        <div>
                            <x-input-label for="categoria" value="Categoría (para reventa)" />
                            <x-select id="categoria" name="categoria" class="mt-1.5">
                                <option value="">— Sin determinar —</option>
                                @foreach (\App\Models\OrdenServicio::CATEGORIAS as $cat)
                                    <option value="{{ $cat }}" @selected(old('categoria', $orden->categoria) === $cat)>{{ \App\Models\OrdenServicio::CATEGORIA_ETIQUETAS[$cat] }}</option>
                                @endforeach
                            </x-select>
                            <x-input-hint>Máquina propia (IMP. DALI): con qué calidad queda para revender — Primera, Segunda o Desarme (repuestos).</x-input-hint>
                            <x-input-error :messages="$errors->get('categoria')" class="mt-2" />
                        </div>
                    @endif

                    {{-- Repuestos usados: el técnico declara QUÉ usó y CUÁNTOS.
                         El precio se pone en la pestaña Cotización (aquí va oculto). --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <x-input-label value="Repuestos usados" />
                            <button type="button" x-on:click="agregar()"
                                class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-sm font-medium text-neutral-700 shadow-sm hover:bg-neutral-50">
                                <x-icon.plus class="h-4 w-4" /> Agregar repuesto
                            </button>
                        </div>

                        <div class="mt-2 space-y-2">
                            <template x-for="(r, i) in repuestos" :key="i">
                                <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-2 sm:flex-row sm:items-start sm:gap-2 sm:rounded-none sm:border-0 sm:p-0">
                                    {{-- Precio conservado (oculto): se edita en Cotización. --}}
                                    <input type="hidden" :name="`repuestos[${i}][precio_unitario]`" :value="r.precio_unitario ?? 0">

                                    <div class="relative sm:flex-1" x-on:click.outside="filaActiva === i && cerrarSugerencias()">
                                        <input type="text" x-model="r.nombre" :name="`repuestos[${i}][nombre]`"
                                            placeholder="Código o nombre del repuesto" maxlength="191" autocomplete="off"
                                            x-on:input.debounce.250ms="buscarRepuesto(i)"
                                            x-on:focus="buscarRepuesto(i)"
                                            x-on:keydown.escape="cerrarSugerencias()"
                                            class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">

                                        <div x-show="filaActiva === i && (buscandoRepuesto || sugerencias.length)" x-cloak
                                            class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                                            <template x-if="buscandoRepuesto && sugerencias.length === 0">
                                                <div class="px-3.5 py-2.5 text-sm text-neutral-400">Buscando…</div>
                                            </template>
                                            <ul class="max-h-60 divide-y divide-neutral-100 overflow-auto">
                                                <template x-for="(s, si) in sugerencias" :key="si">
                                                    <li>
                                                        <button type="button" x-on:click="elegirRepuesto(i, s)"
                                                            class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50">
                                                            <span class="min-w-0">
                                                                <span x-show="s.sku" class="font-mono text-xs text-neutral-400" x-text="s.sku"></span>
                                                                <span x-text="s.nombre"></span>
                                                            </span>
                                                        </button>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                    </div>

                                    {{-- Cantidad + quitar. --}}
                                    <div class="flex items-start gap-2">
                                        <div class="w-20 sm:w-16">
                                            <label class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Cant.</label>
                                            <input type="number" min="1" x-model.number="r.cantidad" :name="`repuestos[${i}][cantidad]`"
                                                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                        </div>
                                        <button type="button" x-on:click="quitar(i)"
                                            class="shrink-0 self-end rounded-lg p-2 text-neutral-400 hover:bg-red-50 hover:text-red-600 sm:self-start" title="Quitar">
                                            <x-icon.trash class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <p x-show="repuestos.length === 0" class="py-2 text-sm text-neutral-400">
                                Sin repuestos. Usa «Agregar repuesto» si corresponde.
                            </p>
                        </div>
                    </div>

                    {{-- Fechas de aviso y retiro --}}
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="fecha_aviso" value="Fecha de aviso al cliente" />
                            <x-text-input id="fecha_aviso" class="mt-1.5" type="date" name="fecha_aviso"
                                :value="old('fecha_aviso', $orden->fecha_aviso?->format('Y-m-d'))" />
                            <x-input-hint>Cuando se le avisó que el equipo está listo.</x-input-hint>
                            <x-input-error :messages="$errors->get('fecha_aviso')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fecha_retiro" value="Fecha de retiro" />
                            <x-text-input id="fecha_retiro" class="mt-1.5" type="date" name="fecha_retiro"
                                :value="old('fecha_retiro', $orden->fecha_retiro?->format('Y-m-d'))" />
                            <x-input-hint>Cuando el cliente retiró el equipo (respaldo).</x-input-hint>
                            <x-input-error :messages="$errors->get('fecha_retiro')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-5">
                        <button type="button" x-on:click="editando = false"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-500 hover:text-neutral-700">Cancelar</button>
                        <x-primary-button>
                            <x-icon.check class="h-4 w-4" /> Guardar
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
