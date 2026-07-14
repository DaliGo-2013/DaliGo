<x-app-layout>
    @php
        $esReparacion = $orden->condicion_efectiva === 'reparacion';
        $equipo = collect([
            ucfirst($orden->tipo_equipo),
            $orden->producto?->sku,
            $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
        ])->filter()->implode(' · ');

        $repuestosInit = $orden->repuestos->map(fn ($r) => [
            'nombre' => $r->nombre,
            'cantidad' => $r->cantidad,
            'precio_unitario' => $r->precio_unitario,
        ])->values();
    @endphp

    <x-slot name="header">
        <x-page-header :title="'Reparación · '.$orden->folio" :subtitle="$orden->cliente_nombre.($equipo ? ' · '.$equipo : '')">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver al listado">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-icon-button type="submit" form="reparacion-form" size="lg" variant="primary" label="Guardar" title="Guardar reparación">
                        <x-icon.check class="h-5 w-5" />
                    </x-icon-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            {{-- Volver a la primera vista (recepcion): datos del ingreso de la maquina. --}}
            <a href="{{ route('admin.servicio-tecnico.edit', $orden) }}"
               class="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-neutral-200 bg-neutral-50 p-4 shadow-sm transition hover:bg-neutral-100">
                <span class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-neutral-200 text-neutral-600">
                        <x-icon.document-text class="h-5 w-5" />
                    </span>
                    <span>
                        <span class="block font-medium text-neutral-900">Ver datos de recepción</span>
                        <span class="block text-sm text-neutral-500">Cliente, equipo, garantía y falla del ingreso.</span>
                    </span>
                </span>
                <x-icon.arrow-left class="h-5 w-5 shrink-0 text-neutral-500" />
            </a>

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
                </div>

                <form id="reparacion-form" method="POST" action="{{ route('admin.servicio-tecnico.reparacion.guardar', $orden) }}"
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
                    <div>
                        <x-input-label for="trabajo_realizado" value="Trabajo realizado" />
                        <x-select id="trabajo_realizado" name="trabajo_realizado" class="mt-1.5">
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
                        <x-input-error :messages="$errors->get('trabajo_realizado')" class="mt-2" />
                    </div>

                    {{-- Causa de la falla (diagnóstico del técnico): alimenta el
                         indicador del informe para reforzar capacitación al cliente.
                         OBLIGATORIA al cerrar como «Reparado» o «Sin solución»: el
                         asterisco y el 'required' aparecen en vivo según el estado
                         elegido arriba (mismo patrón que el N° de serie del ingreso). --}}
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

                    {{-- Categoría de cierre: SOLO para máquinas propias (IMP. DALI)
                         que se reacondicionan para revender. Para clientes comunes
                         este campo no aparece (se decide por el nombre del cliente). --}}
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

                    {{-- Repuestos (lista variable) --}}
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
                                {{-- Movil: tarjeta apilada (nombre arriba, controles abajo).
                                     Desktop (sm+): una sola fila inline. --}}
                                <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-2 sm:flex-row sm:items-start sm:gap-2 sm:rounded-none sm:border-0 sm:p-0">
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
                                                            <span x-show="s.precio !== null && s.precio !== undefined" class="shrink-0 text-xs font-medium text-neutral-500" x-text="'$' + Number(s.precio).toLocaleString('es-CL')"></span>
                                                        </button>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                    </div>

                                    {{-- Controles: cantidad, precio, subtotal y quitar. --}}
                                    <div class="flex items-start gap-2">
                                        <div class="w-20 sm:w-16">
                                            <label class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Cant.</label>
                                            <input type="number" min="1" x-model.number="r.cantidad" :name="`repuestos[${i}][cantidad]`"
                                                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                        </div>
                                        @if ($esReparacion)
                                            <div class="flex-1 sm:w-28 sm:flex-none">
                                                <label class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Precio c/u</label>
                                                <input type="number" min="0" step="1" x-model.number="r.precio_unitario" :name="`repuestos[${i}][precio_unitario]`"
                                                    placeholder="Precio"
                                                    class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                            </div>
                                            <div class="w-24 shrink-0 text-right text-sm text-neutral-600">
                                                <span class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Subtotal</span>
                                                <span class="block sm:pt-2" x-text="clp(subtotal(r))"></span>
                                            </div>
                                        @endif
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
                        <div class="mt-1 hidden gap-3 text-xs text-neutral-400 sm:flex">
                            <span class="flex-1">Repuesto</span>
                            <span class="w-16 text-center">Cant.</span>
                            @if ($esReparacion)
                                <span class="w-28">Precio c/u</span>
                                <span class="w-24 text-right">Subtotal</span>
                            @endif
                            <span class="w-9"></span>
                        </div>
                    </div>

                    @if ($esReparacion)
                        {{-- Mano de obra + costo total (solo si se cobra) --}}
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="space-y-3">
                                {{-- Horas + Mano de obra, lado a lado (compacto). La calculadora
                                     por horas llena la mano de obra (editable igual). --}}
                                <div class="grid grid-cols-2 gap-3">
                                    @if ($precioHoraServicio)
                                        <div>
                                            <x-input-label for="horas_servicio" value="Horas de servicio técnico" />
                                            <x-text-input id="horas_servicio" class="mt-1.5" type="number" min="0" step="0.5"
                                                x-model.number="horas" x-on:input="calcularManoObra()" placeholder="Ej. 1, 1.5, 2" />
                                            <x-input-hint>
                                                Valor hora: {{ '$'.number_format($precioHoraServicio, 0, ',', '.') }} (cód. {{ config('servicio_tecnico.sku_hora_servicio') }}). La mano de obra se calcula sola; la puedes ajustar.
                                            </x-input-hint>
                                        </div>
                                    @endif
                                    <div>
                                        <x-input-label for="mano_obra" value="Mano de obra ($)" />
                                        <x-text-input id="mano_obra" class="mt-1.5" type="number" min="0" step="1" name="mano_obra"
                                            x-model.number="manoObra" :value="old('mano_obra', $orden->mano_obra)" />
                                        <x-input-error :messages="$errors->get('mano_obra')" class="mt-2" />
                                    </div>
                                </div>

                                {{-- Descuento + Motivo, lado a lado (el motivo solo aparece si hay
                                     descuento; obligatorio cuando se aplica). --}}
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="descuento_pct" value="Descuento" />
                                        <x-select id="descuento_pct" name="descuento_pct" class="mt-1.5" x-model.number="descuentoPct">
                                            <option value="0">Sin descuento</option>
                                            @foreach (\App\Models\OrdenServicio::DESCUENTOS_PCT as $pct)
                                                <option value="{{ $pct }}">{{ $pct }}%</option>
                                            @endforeach
                                        </x-select>
                                        <x-input-error :messages="$errors->get('descuento_pct')" class="mt-2" />
                                    </div>
                                    <div x-show="descuentoPct > 0" x-cloak>
                                        <x-input-label for="descuento_motivo" value="Motivo *" />
                                        <x-select id="descuento_motivo" name="descuento_motivo" class="mt-1.5" x-bind:required="descuentoPct > 0">
                                            <option value="">— Selecciona —</option>
                                            @foreach (\App\Models\OrdenServicio::DESCUENTO_MOTIVOS as $val => $label)
                                                <option value="{{ $val }}" @selected(old('descuento_motivo', $orden->descuento_motivo) === $val)>{{ $label }}</option>
                                            @endforeach
                                        </x-select>
                                        <x-input-error :messages="$errors->get('descuento_motivo')" class="mt-2" />
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col justify-end">
                                <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
                                    <p class="text-sm text-neutral-600">Costo total a pagar</p>
                                    <p class="mt-0.5 text-2xl font-semibold text-neutral-900" x-text="clp(total)"></p>
                                    <p class="mt-0.5 text-xs text-neutral-500">
                                        Repuestos <span x-text="clp(totalRepuestos)"></span> + mano de obra.
                                        <span x-show="precioHora > 0 && Number(horas) > 0">(<span x-text="horas"></span> h × <span x-text="clp(precioHora)"></span>)</span>
                                    </p>
                                    <p x-show="descuentoPct > 0" x-cloak class="mt-1 text-xs font-medium text-brand-700">
                                        Descuento <span x-text="descuentoPct"></span>%: −<span x-text="clp(descuentoMonto)"></span>
                                        <span class="text-neutral-400">· subtotal <span x-text="clp(costoBruto)"></span></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

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
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
