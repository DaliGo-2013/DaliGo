<x-app-layout ancho="formulario">
    @php
        $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $esGarantia = $orden->condicion_efectiva === 'garantia';
        // tipo_equipo_label + el `modelo` que escribió el cliente (ver reparacion.blade.php).
        $equipo = collect([
            $orden->tipo_equipo_label,
            $orden->modelo,
            $orden->producto?->sku,
            $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
        ])->filter()->implode(' · ');
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
                // Accessor del modelo: indexar la constante revienta con una causa
                // guardada fuera de la lista (dato histórico). Ver reparacion.blade.php.
                $causaTxt = $orden->causa_falla_label;
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
                // MANO DE OBRA VIGENTE, no la columna guardada: el parte la recalcula
                // desde el catálogo cada vez que guarda, así que mostrar la guardada
                // sería prometer un total que el próximo guardado baja (bitácora
                // 2026-08-07). Se pisa el atributo EN MEMORIA —esto es un GET, no se
                // guarda nada— para que el total, el neto, el IVA y el descuento salgan
                // de los accessors del modelo y no de una segunda cuenta escrita acá:
                // dos cuentas del mismo dinero es exactamente lo que un día difiere.
                $orden->mano_obra = $manoObraVigente;
            @endphp
            {{-- ============ REPARACIÓN: VISTA PREVIA, SIN EDITAR NADA ============
                 Dueño 20-08-2026: «que la cotización no tenga opción de modificarse»,
                 «el detalle de los repuestos se repite… sácalo, sino es doble
                 información» y el descuento «que pase a la parte del técnico».

                 Así que acá NO hay formulario, ni filas de repuestos, ni selector de
                 descuento, ni botón de enviar: todo eso vive en el parte del técnico,
                 en una sola definición. Lo que queda es el DINERO que el cliente va a
                 leer, y abajo la constancia de lo que ya se le mandó. --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Lo que se le cotiza al cliente</h3>
                    <a href="{{ route('admin.servicio-tecnico.reparacion', $orden) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Editar en parte del técnico →</a>
                </div>

                <dl class="divide-y divide-neutral-100 rounded-xl border border-neutral-200 text-sm">
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Trabajo realizado</dt>
                        <dd class="text-right text-neutral-900">{{ $orden->trabajo_realizado ?: '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">
                            Repuestos
                            <span class="block text-xs text-neutral-400">{{ $orden->repuestos->count() }} {{ \Illuminate\Support\Str::plural('ítem', $orden->repuestos->count()) }} · el detalle está en el parte del técnico</span>
                        </dt>
                        <dd class="text-right text-neutral-900">{{ $clp($orden->costo_repuestos) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">
                            Mano de obra
                            {{-- Por qué es ese monto — y cuando es $0 por un hueco de
                                 datos, se dice (el envío queda bloqueado hasta cerrarlo). --}}
                            @if (blank($orden->trabajo_realizado))
                                <span class="block text-xs text-neutral-400">Falta el «Trabajo realizado» en el parte del técnico.</span>
                            @elseif ($horasTrabajo === null)
                                <span class="block text-xs text-amber-700">El trabajo «{{ $orden->trabajo_realizado }}» no tiene tiempo estándar: queda en $0 hasta que jefatura lo cargue.</span>
                            @elseif (! $precioHoraServicio)
                                <span class="block text-xs text-amber-700">El código de hora de servicio técnico ({{ config('servicio_tecnico.sku_hora_servicio') }}) no tiene precio en la lista oficial de ventas.</span>
                            @else
                                <span class="block text-xs text-neutral-400">
                                    {{ rtrim(rtrim(number_format((float) $horasTrabajo, 1, ',', ''), '0'), ',') }} h
                                    × ${{ number_format($precioHoraServicio, 0, ',', '.') }} · la fija jefatura
                                </span>
                            @endif
                        </dt>
                        <dd class="text-right text-neutral-900">{{ $clp($orden->mano_obra) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Descuento
                            <span class="block text-xs text-neutral-400">Lo aplica jefatura de ventas en el parte del técnico.</span>
                        </dt>
                        <dd class="text-right text-neutral-900">
                            @if ($orden->descuento_pct > 0)
                                −{{ $clp($orden->descuento_monto) }}
                                <span class="block text-xs text-neutral-400">{{ $orden->descuento_pct }}% · {{ $orden->descuento_motivo_label }}</span>
                            @else
                                <span class="text-neutral-400">Sin descuento</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50 p-4">
                    <p class="text-sm text-neutral-600">Costo total a pagar (IVA incluido)</p>
                    <p class="mt-0.5 text-2xl font-semibold text-neutral-900">{{ $clp($orden->costo_total) }}</p>
                    <div class="mt-1.5 space-y-0.5 border-t border-brand-200 pt-1.5 text-xs text-neutral-600">
                        <div class="flex justify-between"><span>Neto</span><span>{{ $clp($orden->costo_neto) }}</span></div>
                        <div class="flex justify-between"><span>IVA (19%)</span><span>{{ $clp($orden->costo_iva) }}</span></div>
                    </div>
                </div>

                {{-- Advertencia de gasto alto: la misma regla del parte (>40% del valor
                     del equipo), acá calculada en el servidor porque no hay nada que
                     recalcular en vivo. --}}
                @if ($precioVentaEquipo && $orden->costo_total > (int) round($precioVentaEquipo * \App\Models\OrdenServicio::UMBRAL_REPARACION_ALTA))
                    <div class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">⚠️ Costo de reparación alto</p>
                        <p class="mt-0.5">
                            El total ({{ $clp($orden->costo_total) }}) es el
                            <span class="font-semibold">{{ (int) round($orden->costo_total / $precioVentaEquipo * 100) }}%</span>
                            del valor del equipo ({{ $clp($precioVentaEquipo) }}) y supera el 40%.
                            <span class="font-medium">Consulta con el cliente</span> si le conviene reparar o cambiar el equipo.
                        </p>
                    </div>
                @endif

                {{-- Dónde se envía. No es un botón: el envío guarda primero, y lo que
                     se guarda se escribe en el parte — un «Enviar» acá mandaría el
                     snapshot de otra pantalla. --}}
                <p class="mt-4 border-t border-neutral-100 pt-4 text-xs text-neutral-500">
                    @if ($faltaManoObra)
                        Todavía no se puede enviar al cliente: {{ $faltaManoObra }}.
                    @elseif (blank($orden->cliente_email))
                        Todavía no se puede enviar al cliente: la orden no tiene correo (agrégalo en la recepción).
                    @else
                        Se envía desde
                        <a href="{{ route('admin.servicio-tecnico.reparacion', $orden) }}" class="font-medium text-brand-600 hover:text-brand-700">Parte del técnico</a>,
                        con el botón «Enviar cotización» junto a «Guardar».
                    @endif
                </p>
            </div>

            {{-- NI CONSTANCIA NI «LISTO PARA RETIRAR» ACÁ (dueño 20-08-2026, señalando
                 las dos tarjetas): «se repite, ya aparece abajo en la vista de parte
                 del técnico». Las dos viven en el parte, que es la pantalla de la
                 orden; esta pestaña es solo la vista previa de lo que paga el cliente.

                 OJO: en GARANTÍA sí siguen arriba, en su propia rama — ahí el parte no
                 las incluye (no hay cotización que enviar) y esta pestaña es la única
                 pantalla donde existe el botón de avisar el retiro. Sacarlas de ahí no
                 sería quitar una repetición: sería borrar la función. --}}
        @endif
    </div>
</x-app-layout>
