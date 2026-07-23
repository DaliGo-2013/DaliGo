<x-app-layout>
    @php
        $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $esGarantia = $orden->condicion_efectiva === 'garantia';
        $esReparacion = ! $esGarantia;
        $equipo = collect([
            ucfirst($orden->tipo_equipo),
            $orden->producto?->sku,
            $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
        ])->filter()->implode(' · ');
        $tieneDetalle = $orden->repuestos->isNotEmpty() || $orden->mano_obra || $orden->descuento_pct;
    @endphp

    <x-slot name="header">
        <x-page-header :title="'Cotización · '.$orden->folio" :subtitle="$orden->cliente_nombre.($equipo ? ' · '.$equipo : '')">
            <x-slot name="action">
                <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver al listado"
                    onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            @include('admin.servicio-tecnico._tabs', ['activa' => 'cotizacion'])

            <x-status-alert :status="session('status')" />

            {{-- Desglose GUARDADO (solo lectura). Los números se ingresan en la
                 etapa «Parte del técnico»; aquí solo se ven y se envían. --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Detalle del presupuesto</h3>
                    <a href="{{ route('admin.servicio-tecnico.reparacion', $orden) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Editar en parte del técnico →</a>
                </div>

                @if ($esGarantia)
                    <div class="rounded-xl bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
                        <x-badge variant="neutral">Garantía</x-badge>
                        <span class="ml-1">Garantía vigente: la reparación no se cobra, así que no se cotiza.</span>
                    </div>
                @elseif (! $tieneDetalle)
                    <p class="text-sm text-neutral-400">Aún sin repuestos ni mano de obra. Regístralos en «Parte del técnico».</p>
                @else
                    @if ($orden->repuestos->isNotEmpty())
                        <div class="mb-4">
                            <dt class="mb-1 text-xs text-neutral-400">Repuestos</dt>
                            <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200 text-sm">
                                @foreach ($orden->repuestos as $r)
                                    <li class="flex items-center justify-between px-3 py-2">
                                        <span class="text-neutral-900">{{ $r->nombre }} <span class="text-neutral-400">× {{ $r->cantidad }}</span></span>
                                        <span class="text-neutral-600">{{ $clp($r->subtotal) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div><dt class="text-xs text-neutral-400">Mano de obra</dt><dd class="text-sm text-neutral-900">{{ $clp($orden->mano_obra ?? 0) }}</dd></div>
                        @if ($orden->descuento_pct > 0)
                            <div>
                                <dt class="text-xs text-neutral-400">Descuento</dt>
                                <dd class="text-sm text-neutral-900">{{ $orden->descuento_pct }}% · −{{ $clp($orden->descuento_monto) }}
                                    <span class="text-neutral-400">({{ $orden->descuento_motivo_label }})</span></dd>
                            </div>
                        @endif
                        <div><dt class="text-xs text-neutral-400">Costo total</dt><dd class="text-sm font-semibold text-neutral-900">{{ $clp($orden->costo_total) }}</dd></div>
                    </dl>
                @endif
            </div>

            {{-- ===== Envío al cliente + historial (P-M12-02) =====
                 Solo reparaciones que se cobran (garantía no cotiza). Usa lo
                 GUARDADO (snapshot), no lo que esté a medio editar en reparación. --}}
            @if ($esReparacion)
                @php $ultima = $cotizaciones->first(); @endphp
                <div class="mt-5 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
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
                            $orden->estado !== 'cotizacion' ? 'pon la orden en etapa «Cotización» y guarda' : null,
                            blank($orden->cliente_email) ? 'la orden no tiene correo del cliente (agrégalo en la recepción)' : null,
                            (int) $orden->costo_total <= 0 ? 'lo guardado suma $0 (registra repuestos o mano de obra y guarda)' : null,
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
                                Se envía lo último <span class="font-medium">guardado</span> (guarda antes de enviar en «Parte del técnico»).
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
    </div>
</x-app-layout>
