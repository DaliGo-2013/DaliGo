<x-app-layout ancho="formulario">
    @php
        $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $esGarantia = $orden->condicion_efectiva === 'garantia';
        $esReparacion = ! $esGarantia;
        // tipo_equipo_label + el `modelo` que escribió el cliente (ver reparacion.blade.php).
        $equipo = collect([
            $orden->tipo_equipo_label,
            $orden->modelo,
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

            @include('admin.servicio-tecnico._listo-retiro')
        @else
            @php
                $ultima = $cotizaciones->first();
                // Qué falta para poder enviar (espejo de la validación del server).
                // Las etapas PREVIAS ya no bloquean: al enviar, la orden pasa sola a
                // «Cotización» (dueño 06-08). El total en $0 tampoco: el botón vive
                // dentro del formulario y se habilita con el total EN PANTALLA
                // (x-bind:disabled), porque enviar guarda primero.
                $faltas = collect([
                    in_array($orden->estado, ['recibido', 'en_revision', 'cotizacion'], true) ? null : 'la orden ya pasó la etapa de cotización (para re-cotizar, vuélvela a «Cotización» en Parte del técnico)',
                    blank($orden->cliente_email) ? 'la orden no tiene correo del cliente (agrégalo en la recepción)' : null,
                    // Sin mano de obra calculable no sale al cliente: el total le
                    // cobraría de menos sin que nadie lo note. Guardar sigue libre.
                    $faltaManoObra,
                ])->filter();
            @endphp
            {{-- ===================== REPARACIÓN: armar el precio ===================== --}}
            <form method="POST" action="{{ route('admin.servicio-tecnico.cotizacion.guardar', $orden) }}"
                  class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8" data-una-vez
                  {{-- `manoObra` se siembra con la VIGENTE del catálogo (lo que va a
                       quedar al guardar), no con `$orden->mano_obra`: si el trabajo
                       perdió su tiempo estándar, el total no puede prometer un monto
                       que el guardado baja a $0. --}}
                  x-data="reparacionForm({ repuestos: @js($repInit), manoObra: {{ (int) $manoObraVigente }}, endpointRepuestos: '{{ route('admin.servicio-tecnico.buscar-repuesto') }}', precioHora: {{ (int) ($precioHoraServicio ?? 0) }}, descuentoPct: {{ (int) old('descuento_pct', $orden->descuento_pct ?? 0) }} })">
                @csrf
                @method('PUT')

                @include('admin.servicio-tecnico.partials._presupuesto-campos')

                {{-- Los dos botones en la MISMA fila (dueño 07-08: no gastar una
                     tarjeta entera en el envío). «Enviar» es submit de ESTE
                     formulario con enviar=1: guarda y manda en un paso, así lo que
                     sale es lo que está en pantalla — pegado a «Guardar», mandar el
                     snapshot viejo sin darse cuenta era demasiado fácil. Queda
                     secundario a propósito: sale un correo al cliente, no debe
                     pesar lo mismo que guardar. --}}
                <div class="mt-5 flex flex-wrap items-center justify-end gap-x-3 gap-y-2 border-t border-neutral-100 pt-5">
                    {{-- La ayuda ocupa el hueco de la izquierda (mr-auto): si no cabe,
                         se parte ELLA. Los dos botones van en su propio flex para que
                         nunca se separen — es justo lo que pidió el dueño. --}}
                    <p class="mr-auto max-w-sm text-xs text-neutral-400">
                        @if ($faltas->isEmpty())
                            «Enviar» guarda y manda la carta a {{ $orden->cliente_email }}.
                            @if ($ultima && $ultima->estado === 'enviada') Reemplaza la anterior. @endif
                        @else
                            Para enviarla al cliente: {{ $faltas->implode('; ') }}.
                        @endif
                    </p>
                    <div class="flex items-center gap-2">
                        @if ($faltas->isEmpty())
                            <x-secondary-button type="submit" name="enviar" value="1"
                                                x-bind:disabled="total <= 0"
                                                x-bind:title="total <= 0 ? 'Pon precios antes de enviar' : ''"
                                                x-on:click="if (! confirm('Se guardará y se enviará la cotización por ' + clp(total) + ' a ' + {{ Js::from($orden->cliente_email) }} + '. ¿Continuar?')) $event.preventDefault()">
                                {{ $ultima && $ultima->estado !== 'reemplazada' ? 'Enviar cotización nueva' : 'Enviar cotización' }}
                            </x-secondary-button>
                        @endif
                        <x-primary-button>
                            <x-icon.check class="h-4 w-4" /> Guardar cotización
                        </x-primary-button>
                    </div>
                </div>
            </form>

            @include('admin.servicio-tecnico.partials._envio-historial')
        @endif
    </div>
</x-app-layout>
