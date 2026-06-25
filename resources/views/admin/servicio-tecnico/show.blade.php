<x-app-layout>
    @php
        $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $tieneReparacion = $orden->trabajo_realizado || $orden->repuestos->isNotEmpty()
            || $orden->mano_obra || $orden->fecha_aviso || $orden->fecha_retiro;
        $esReparacion = $orden->facturacion === 'reparacion';
    @endphp

    <x-slot name="header">
        <x-page-header :title="'Orden '.$orden->folio" :subtitle="$orden->cliente_nombre">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    @can('manage servicio tecnico')
                        <x-icon-button :href="route('admin.servicio-tecnico.reparacion', $orden)" size="lg" variant="secondary" label="Reparación" title="Reparación (taller)">
                            <x-icon.wrench-screwdriver class="h-5 w-5" />
                        </x-icon-button>
                        <x-icon-button :href="route('admin.servicio-tecnico.edit', $orden)" size="lg" variant="primary" label="Editar" title="Editar recepción">
                            <x-icon.pencil class="h-5 w-5" />
                        </x-icon-button>
                    @endcan
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Estado + condicion --}}
            <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <span class="text-sm text-neutral-500">Estado actual:</span>
                <x-badge :variant="$orden->estado_variante">{{ \Illuminate\Support\Str::headline($orden->estado) }}</x-badge>
                <x-badge variant="neutral">{{ ucfirst($orden->facturacion) }}</x-badge>
                @if ($orden->facturacion === 'garantia')
                    <span class="text-xs {{ $orden->garantia_vigente ? 'text-green-600' : 'text-red-600' }}">
                        {{ $orden->garantia_vigente ? 'Garantía vigente' : 'Garantía vencida' }}
                    </span>
                @endif
            </div>

            {{-- Cliente --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Cliente</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div><dt class="text-xs text-neutral-400">Nombre</dt><dd class="text-sm text-neutral-900">{{ $orden->cliente_nombre }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">RUT</dt><dd class="text-sm text-neutral-900">{{ $orden->cliente_rut }}</dd></div>
                </dl>
            </div>

            {{-- Equipo --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Equipo</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div><dt class="text-xs text-neutral-400">Tipo</dt><dd class="text-sm text-neutral-900">{{ ucfirst($orden->tipo_equipo) }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">Código (producto Dali)</dt><dd class="text-sm text-neutral-900">{{ $orden->producto ? $orden->producto->sku.' — '.$orden->producto->nombre : '—' }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">N° de serie</dt><dd class="text-sm text-neutral-900">{{ $orden->numero_serie ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">Sucursal de recepción</dt><dd class="text-sm text-neutral-900">{{ $orden->sucursal?->nombre ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">Fecha de ingreso</dt><dd class="text-sm text-neutral-900">{{ $orden->fecha_ingreso?->format('d-m-Y') ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">Fecha de entrega (estimada)</dt><dd class="text-sm text-neutral-900">{{ $orden->fecha_entrega?->format('d-m-Y') ?: '—' }}</dd></div>
                </dl>
            </div>

            {{-- Garantia (si aplica) --}}
            @if ($orden->facturacion === 'garantia')
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Documento de garantía</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-3">
                        <div><dt class="text-xs text-neutral-400">Documento</dt><dd class="text-sm text-neutral-900">{{ $orden->garantia_doc_tipo ? ucfirst($orden->garantia_doc_tipo) : '—' }}</dd></div>
                        <div><dt class="text-xs text-neutral-400">N° de documento</dt><dd class="text-sm text-neutral-900">{{ $orden->garantia_doc_numero ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-neutral-400">Fecha de compra</dt><dd class="text-sm text-neutral-900">{{ $orden->garantia_doc_fecha?->format('d-m-Y') ?: '—' }}</dd></div>
                    </dl>
                </div>
            @endif

            {{-- Falla --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Falla reportada</h3>
                <p class="whitespace-pre-line text-sm text-neutral-900">{{ $orden->falla_reportada ?: '—' }}</p>
            </div>

            {{-- Reparacion (taller) --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Reparación (taller)</h3>
                    @can('manage servicio tecnico')
                        <a href="{{ route('admin.servicio-tecnico.reparacion', $orden) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Editar →</a>
                    @endcan
                </div>

                @unless ($tieneReparacion)
                    <p class="text-sm text-neutral-400">Aún sin registro de reparación.</p>
                @else
                    @if ($orden->trabajo_realizado)
                        <div class="mb-4">
                            <dt class="text-xs text-neutral-400">Trabajo realizado</dt>
                            <dd class="whitespace-pre-line text-sm text-neutral-900">{{ $orden->trabajo_realizado }}</dd>
                        </div>
                    @endif

                    @if ($orden->repuestos->isNotEmpty())
                        <div class="mb-4">
                            <dt class="mb-1 text-xs text-neutral-400">Repuestos</dt>
                            <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200 text-sm">
                                @foreach ($orden->repuestos as $r)
                                    <li class="flex items-center justify-between px-3 py-2">
                                        <span class="text-neutral-900">{{ $r->nombre }} <span class="text-neutral-400">× {{ $r->cantidad }}</span></span>
                                        @if ($esReparacion)
                                            <span class="text-neutral-600">{{ $clp($r->subtotal) }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @if ($esReparacion)
                            <div><dt class="text-xs text-neutral-400">Mano de obra</dt><dd class="text-sm text-neutral-900">{{ $clp($orden->mano_obra ?? 0) }}</dd></div>
                            <div><dt class="text-xs text-neutral-400">Costo total</dt><dd class="text-sm font-semibold text-neutral-900">{{ $clp($orden->costo_total) }}</dd></div>
                        @endif
                        <div><dt class="text-xs text-neutral-400">Fecha de aviso al cliente</dt><dd class="text-sm text-neutral-900">{{ $orden->fecha_aviso?->format('d-m-Y') ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-neutral-400">Fecha de retiro</dt><dd class="text-sm text-neutral-900">{{ $orden->fecha_retiro?->format('d-m-Y') ?: '—' }}</dd></div>
                    </dl>
                @endunless
            </div>

        </div>
    </div>
</x-app-layout>
