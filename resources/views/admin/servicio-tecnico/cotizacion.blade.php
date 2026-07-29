<x-app-layout ancho="formulario">
    @php
        $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $esGarantia = $orden->condicion_efectiva === 'garantia';
        $esReparacion = ! $esGarantia;
        $equipo = collect([
            ucfirst($orden->tipo_equipo),
            $orden->producto?->sku,
            $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
        ])->filter()->implode(' · ');

        // Repuestos que dejó el técnico (nombre + cantidad): aquí se les pone precio.
        $repInit = $orden->repuestos->map(fn ($r) => [
            'nombre' => $r->nombre,
            // El SKU viaja para no perderlo al re-guardar (el documento tributario
            // se factura con el código de catálogo, regla 4 de Contabilidad).
            'sku' => $r->sku,
            'cantidad' => $r->cantidad,
            'precio_unitario' => $r->precio_unitario,
        ])->values();
    @endphp

    <x-slot name="header">
        <x-page-header :title="'Cotización · '.$orden->folio" :subtitle="$orden->cliente_nombre.($equipo ? ' · '.$equipo : '')"
                       :back="route('admin.servicio-tecnico.index')" backTitle="Volver al listado" />
    </x-slot>

    <div class="py-12">
        @include('admin.servicio-tecnico._tabs', ['activa' => 'cotizacion'])

        <x-status-alert :status="session('status')" />

        @if ($esGarantia)
            {{-- ===================== GARANTÍA: detalle sin cobro ===================== --}}
            @php
                $causaTxt = filled($orden->causa_falla) ? \App\Models\OrdenServicio::CAUSA_FALLA_ETIQUETAS[$orden->causa_falla] : null;
                $faltas = collect([
                    blank($orden->cliente_email) ? 'la orden no tiene correo del cliente (agrégalo en la recepción)' : null,
                    blank($orden->trabajo_realizado) ? 'registra el trabajo realizado en «Parte del técnico»' : null,
                ])->filter();
            @endphp
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Detalle del trabajo (garantía)</h3>
                    <x-badge variant="neutral">Garantía</x-badge>
                </div>
                <p class="mb-4 text-sm text-neutral-600">
                    Equipo en garantía vigente: <span class="font-medium">no se cobra</span>, así que no se cotiza.
                    Al cliente se le envía solo el detalle de lo que se hizo.
                </p>

                <dl class="divide-y divide-neutral-100 rounded-xl border border-neutral-200 text-sm">
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Trabajo realizado</dt>
                        <dd class="text-right text-neutral-900">{{ $orden->trabajo_realizado ?: '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Causa de la falla</dt>
                        <dd class="text-right text-neutral-900">{{ $causaTxt ?: 'Sin determinar' }}</dd>
                    </div>
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
                </dl>

                <div class="mt-4">
                    @if ($faltas->isEmpty())
                        <form method="POST" action="{{ route('admin.servicio-tecnico.detalle-trabajo.enviar', $orden) }}" data-una-vez
                              onsubmit="return confirm('Se enviará el detalle del trabajo (sin costo, garantía) a {{ $orden->cliente_email }}. ¿Continuar?');">
                            @csrf
                            <x-primary-button type="submit">Enviar detalle del trabajo</x-primary-button>
                        </form>
                        <p class="mt-2 text-xs text-neutral-400">
                            El cliente recibe el trabajo realizado, la causa de la falla y los repuestos usados (sin precios),
                            con la nota «Sin costo — cubierto por la garantía».
                        </p>
                    @else
                        <p class="text-sm text-neutral-500">Para enviar el detalle: {{ $faltas->implode('; ') }}.</p>
                    @endif
                </div>
            </div>
        @else
            {{-- ===================== REPARACIÓN: armar el precio ===================== --}}
            <form method="POST" action="{{ route('admin.servicio-tecnico.cotizacion.guardar', $orden) }}"
                  class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8" data-una-vez
                  x-data="reparacionForm({ repuestos: @js($repInit), manoObra: {{ (int) old('mano_obra', $orden->mano_obra ?? 0) }}, endpointRepuestos: '{{ route('admin.servicio-tecnico.buscar-repuesto') }}', precioHora: {{ (int) ($precioHoraServicio ?? 0) }}, descuentoPct: {{ (int) old('descuento_pct', $orden->descuento_pct ?? 0) }} })">
                @csrf
                @method('PUT')

                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Detalle del presupuesto</h3>
                    <a href="{{ route('admin.servicio-tecnico.reparacion', $orden) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Ver parte del técnico →</a>
                </div>

                {{-- Repuestos: se pueden agregar buscándolos del catálogo, con precio.
                     (También llegan los que declaró el técnico en su parte.) --}}
                <div>
                    <div class="flex items-center justify-between">
                        <x-input-label value="Repuestos" />
                        <x-agregar-fila-button x-on:click="agregar()">Agregar repuesto</x-agregar-fila-button>
                    </div>

                    <div class="mt-2 space-y-2">
                        <template x-for="(r, i) in repuestos" :key="i">
                            <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-2 sm:flex-row sm:items-start sm:gap-2 sm:rounded-none sm:border-0 sm:p-0">
                                {{-- SKU del catálogo (oculto): lo pone el buscador al elegir, y se
                                     conserva al re-guardar. Vacío si se escribió a mano. --}}
                                <input type="hidden" :name="`repuestos[${i}][sku]`" :value="r.sku ?? ''">
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

                                {{-- Cantidad, precio, subtotal y quitar. --}}
                                <div class="flex items-start gap-2">
                                    <div class="w-20 sm:w-16">
                                        <label class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Cant.</label>
                                        <input type="number" min="1" x-model.number="r.cantidad" :name="`repuestos[${i}][cantidad]`"
                                            class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    </div>
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
                                    <button type="button" x-on:click="quitar(i)"
                                        class="shrink-0 self-end rounded-lg p-2 text-neutral-400 hover:bg-red-50 hover:text-red-600 sm:self-start" title="Quitar">
                                        <x-icon.trash class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        </template>

                        <p x-show="repuestos.length === 0" class="py-2 text-sm text-neutral-400">
                            Sin repuestos. Usa «Agregar repuesto» y búscalos del catálogo.
                        </p>
                    </div>
                    <div class="mt-1 hidden gap-3 text-xs text-neutral-400 sm:flex">
                        <span class="flex-1">Repuesto</span>
                        <span class="w-16 text-center">Cant.</span>
                        <span class="w-28">Precio c/u</span>
                        <span class="w-24 text-right">Subtotal</span>
                        <span class="w-9"></span>
                    </div>
                    @php $errBag = $errors->getMessages(); @endphp
                    @foreach ($errBag as $key => $msgs)
                        @if (\Illuminate\Support\Str::startsWith($key, 'repuestos.'))
                            <p class="mt-1 text-sm text-red-600">{{ $msgs[0] }}</p>
                        @endif
                    @endforeach
                </div>

                {{-- Mano de obra + descuento --}}
                <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="space-y-3">
                        {{-- Mano de obra FIJA por el trabajo (no editable aquí):
                             horas estándar del catálogo × valor hora. --}}
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                            <p class="text-xs text-neutral-500">Mano de obra (fijada por el trabajo)</p>
                            <p class="mt-0.5 text-lg font-semibold text-neutral-900" x-text="clp(manoObra)"></p>
                            @if ($orden->trabajo_realizado && $horasTrabajo !== null)
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    {{ rtrim(rtrim(number_format((float) $horasTrabajo, 1, ',', ''), '0'), ',') }} h
                                    × {{ $precioHoraServicio ? '$'.number_format($precioHoraServicio, 0, ',', '.') : '—' }}
                                    · «{{ $orden->trabajo_realizado }}»
                                </p>
                                <p class="mt-1 text-xs text-neutral-400">La define jefatura en «Costos generales de reparación»; el técnico no la modifica.</p>
                            @elseif ($orden->trabajo_realizado)
                                <p class="mt-0.5 text-xs text-amber-700">El trabajo «{{ $orden->trabajo_realizado }}» no tiene tiempo estándar. Agrégalo en «Costos generales de reparación».</p>
                            @else
                                <p class="mt-0.5 text-xs text-neutral-400">Elige el «Trabajo realizado» en Parte del técnico para fijar la mano de obra.</p>
                            @endif
                        </div>
                        {{-- El descuento es decisión COMERCIAL: solo jefatura de ventas
                             (permiso 'aplicar descuento servicio tecnico') lo edita. El
                             técnico lo ve en solo lectura (el total ya lo refleja). --}}
                        @can('aplicar descuento servicio tecnico')
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
                        @else
                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-sm">
                                <p class="text-xs text-neutral-500">Descuento</p>
                                @if ($orden->descuento_pct > 0)
                                    <p class="mt-0.5 font-medium text-neutral-900">{{ $orden->descuento_pct }}%
                                        <span class="text-neutral-400">({{ $orden->descuento_motivo_label }})</span></p>
                                @else
                                    <p class="mt-0.5 text-neutral-500">Sin descuento.</p>
                                @endif
                                <p class="mt-1 text-xs text-neutral-400">Solo jefatura de ventas aplica descuentos.</p>
                            </div>
                        @endcan
                    </div>
                    <div class="flex flex-col justify-end">
                        <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
                            <p class="text-sm text-neutral-600">Costo total a pagar (IVA incluido)</p>
                            <p class="mt-0.5 text-2xl font-semibold text-neutral-900" x-text="clp(total)"></p>
                            {{-- Desglose de IVA en vivo: el total ya viene con IVA → neto = total/1,19. --}}
                            <div class="mt-1.5 space-y-0.5 border-t border-brand-200 pt-1.5 text-xs text-neutral-600">
                                <div class="flex justify-between"><span>Neto</span><span x-text="clp(Math.round(total / 1.19))"></span></div>
                                <div class="flex justify-between"><span>IVA (19%)</span><span x-text="clp(total - Math.round(total / 1.19))"></span></div>
                            </div>
                            <p class="mt-1.5 text-xs text-neutral-500">
                                Repuestos <span x-text="clp(totalRepuestos)"></span> + mano de obra.
                            </p>
                            <p x-show="descuentoPct > 0" x-cloak class="mt-1 text-xs font-medium text-brand-700">
                                Descuento <span x-text="descuentoPct"></span>%: −<span x-text="clp(descuentoMonto)"></span>
                                <span class="text-neutral-400">· subtotal <span x-text="clp(costoBruto)"></span></span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Advertencia de gasto: si la reparación supera el 40% del
                     valor del equipo (como la "pérdida total" de los autos).
                     No bloquea: avisa para consultar al cliente. --}}
                @if ($precioVentaEquipo)
                    @php $umbralAlto = (int) round($precioVentaEquipo * \App\Models\OrdenServicio::UMBRAL_REPARACION_ALTA); @endphp
                    <div x-show="total > {{ $umbralAlto }}" x-cloak class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">⚠️ Costo de reparación alto</p>
                        <p class="mt-0.5">
                            El total (<span x-text="clp(total)"></span>) es el
                            <span class="font-semibold" x-text="Math.round(total / {{ $precioVentaEquipo }} * 100)"></span>%
                            del valor del equipo ({{ $clp($precioVentaEquipo) }}) y supera el 40%.
                            <span class="font-medium">Consulta con el cliente</span> si le conviene reparar o cambiar el equipo antes de continuar.
                        </p>
                    </div>
                @endif

                <div class="mt-5 flex items-center justify-end border-t border-neutral-100 pt-5">
                    <x-primary-button>
                        <x-icon.check class="h-4 w-4" /> Guardar cotización
                    </x-primary-button>
                </div>
            </form>

            {{-- ===== Envío al cliente + historial (P-M12-02) =====
                 Usa lo GUARDADO (snapshot), no lo que esté a medio editar. --}}
            @php $ultima = $cotizaciones->first(); @endphp
            <div class="mt-5 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-neutral-900">Cotización al cliente</h3>
                    @if ($ultima)
                        <x-badge :variant="$ultima->estado_variante">{{ $ultima->estado_label }}</x-badge>
                    @endif
                </div>

                @if ($ultima)
                    <p class="mt-2 text-sm text-neutral-600">
                        Última: enviada el {{ $ultima->created_at->format('d-m-Y H:i') }}
                        a {{ $ultima->cliente_email }} por
                        <span class="font-semibold">${{ number_format((int) $ultima->costo_total, 0, ',', '.') }}</span>@if ($ultima->respondida_at) · respondida el {{ $ultima->respondida_at->format('d-m-Y H:i') }}@endif.
                    </p>
                    @if (! $ultima->correo_enviado_at && $ultima->esRespondible())
                        <form method="POST" action="{{ route('admin.servicio-tecnico.cotizacion.reintentar', [$orden, $ultima->id]) }}" class="mt-3" data-una-vez>
                            @csrf
                            <x-secondary-button type="submit">Reintentar correo</x-secondary-button>
                            <span class="ml-2 text-xs text-red-600">El correo no salió al enviarla.</span>
                        </form>
                    @endif
                @endif

                @php
                    // Qué falta para poder enviar (espejo de la validación del server).
                    $faltas = collect([
                        $orden->estado !== 'cotizacion' ? 'pon la orden en etapa «Cotización» (en Parte del técnico) y guarda' : null,
                        blank($orden->cliente_email) ? 'la orden no tiene correo del cliente (agrégalo en la recepción)' : null,
                        (int) $orden->costo_total <= 0 ? 'lo guardado suma $0 (pon precios arriba y guarda la cotización)' : null,
                    ])->filter();
                @endphp
                <div class="mt-4">
                    @if ($faltas->isEmpty())
                        <form method="POST" action="{{ route('admin.servicio-tecnico.cotizacion.enviar', $orden) }}" data-una-vez
                              onsubmit="return confirm('Se enviará la cotización GUARDADA por ${{ number_format((int) $orden->costo_total, 0, ',', '.') }} a {{ $orden->cliente_email }}. ¿Continuar?');">
                            @csrf
                            <x-primary-button type="submit">
                                {{ $ultima && $ultima->estado !== 'reemplazada' ? 'Enviar cotización nueva' : 'Enviar cotización' }}
                            </x-primary-button>
                        </form>
                        <p class="mt-2 text-xs text-neutral-400">
                            Se envía lo último <span class="font-medium">guardado</span> (guarda arriba antes de enviar).
                            El cliente responde ACEPTO / NO ACEPTO por un link y el aviso llega a taller y ventas.
                            @if ($ultima && $ultima->estado === 'enviada') Enviar una nueva reemplaza la anterior. @endif
                        </p>
                    @else
                        <p class="text-sm text-neutral-500">Para enviar la cotización: {{ $faltas->implode('; ') }}.</p>
                    @endif
                </div>

                {{-- Historial (re-envíos y respuestas anteriores) --}}
                @if ($cotizaciones->count() > 1)
                    <div class="mt-4 border-t border-neutral-100 pt-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-400">Historial</p>
                        <ul class="mt-1.5 space-y-1">
                            @foreach ($cotizaciones->slice(1) as $c)
                                <li class="text-xs text-neutral-500">
                                    {{ $c->created_at->format('d-m-Y H:i') }} · ${{ number_format((int) $c->costo_total, 0, ',', '.') }} · {{ $c->estado_label }}@if ($c->respondida_at) ({{ $c->respondida_at->format('d-m-Y H:i') }})@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
