<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'Hoja de ruta · folio '.$hoja->folio" :subtitle="($hoja->zona?->nombre ?? 'Sin zona').' · '.$hoja->vehiculo.' · '.$hoja->conductor?->name"
                       :back="route('admin.hojas-ruta.index')" backTitle="Volver a hojas de ruta" />
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="space-y-6">

            <x-status-alert :status="session('status')" />
            @if ($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" data-error-message>
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- La cadena de llaves (R11): qué se dio, quién y cuándo; y el
                 botón de la SIGUIENTE, visible solo para quien porta su
                 permiso. La secuencia la protege el service — esto es UI. --}}
            <x-seccion titulo="Autorizaciones">
                <div class="flex flex-wrap items-center gap-2">
                    <x-hoja-ruta.estado-badge :estado="$hoja->estado" />
                    <span class="text-xs text-neutral-500">La cadena avanza en orden: pagos → ruta → carga → salida.</span>
                </div>

                <ul class="space-y-2 text-sm">
                    @foreach ([
                        ['label' => 'Llave 1 · Pagos (ventas)', 'at' => $hoja->pagos_ok_at, 'por' => $hoja->pagosOkPor],
                        ['label' => 'Llave 2 · Ruta (despacho)', 'at' => $hoja->ruta_autorizada_at, 'por' => $hoja->rutaAutorizadaPor],
                        ['label' => 'Llave 3 · Carga (bodega)', 'at' => $hoja->cargada_at, 'por' => $hoja->cargadaPor],
                        ['label' => 'Salida a ruta', 'at' => $hoja->en_ruta_at, 'por' => $hoja->enRutaPor],
                    ] as $llave)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-neutral-100 px-3 py-2">
                            <span class="{{ $llave['at'] ? 'font-medium text-neutral-900' : 'text-neutral-400' }}">{{ $llave['label'] }}</span>
                            @if ($llave['at'])
                                <span class="text-xs text-neutral-500">
                                    {{ $llave['por']?->name ?? '—' }} · {{ $llave['at']->enChile()->format('d-m-Y H:i') }}
                                </span>
                            @else
                                <span class="text-xs text-neutral-400">Pendiente</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div class="flex flex-wrap gap-3">
                    @if ($hoja->estado === \App\Models\HojaDeRuta::BORRADOR)
                        @can('autorizar pagos ruta')
                            <form method="POST" action="{{ route('admin.hojas-ruta.autorizar-pagos', $hoja) }}" data-una-vez>
                                @csrf
                                <x-primary-button>Autorizar pagos</x-primary-button>
                            </form>
                        @endcan
                    @elseif ($hoja->estado === \App\Models\HojaDeRuta::PAGOS_OK)
                        @can('autorizar ruta')
                            <form method="POST" action="{{ route('admin.hojas-ruta.autorizar-ruta', $hoja) }}" data-una-vez>
                                @csrf
                                <x-primary-button>Autorizar ruta</x-primary-button>
                            </form>
                        @endcan
                    @elseif ($hoja->estado === \App\Models\HojaDeRuta::RUTA_AUTORIZADA)
                        @can('autorizar carga')
                            <form method="POST" action="{{ route('admin.hojas-ruta.autorizar-carga', $hoja) }}" data-una-vez>
                                @csrf
                                <x-primary-button>Autorizar carga</x-primary-button>
                            </form>
                        @endcan
                    @elseif ($hoja->estado === \App\Models\HojaDeRuta::CARGADA)
                        @can('autorizar carga')
                            <form method="POST" action="{{ route('admin.hojas-ruta.salir', $hoja) }}" data-una-vez>
                                @csrf
                                <x-primary-button>Registrar salida a ruta</x-primary-button>
                            </form>
                        @endcan
                    @endif
                </div>
            </x-seccion>

            {{-- Los datos del viaje (el snapshot: si el vehículo cambia en la
                 flota, la hoja histórica no cambia). --}}
            <x-seccion titulo="El viaje">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Sucursal</dt>
                        <dd class="mt-0.5 text-neutral-900">{{ $hoja->sucursal?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Zona</dt>
                        <dd class="mt-0.5 text-neutral-900">{{ $hoja->zona?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Vehículo</dt>
                        <dd class="mt-0.5 text-neutral-900">{{ $hoja->vehiculo }} · {{ $hoja->patente }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Conductor</dt>
                        <dd class="mt-0.5 text-neutral-900">
                            {{ $hoja->conductor?->name ?? '—' }}
                            @if ($hoja->peoneta_nombre)
                                <span class="text-neutral-500">· Peoneta: {{ $hoja->peoneta_nombre }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-seccion>

            {{-- Las paradas en el orden pactado (R3). El ↑ reordena mandando la
                 secuencia completa recalculada — sin JS, un form por fila. --}}
            <x-seccion titulo="Paradas">
                @php
                    $puedeReordenar = auth()->user()->can('manage hojas ruta')
                        && $hoja->estado !== \App\Models\HojaDeRuta::EN_RUTA
                        && $hoja->estado !== \App\Models\HojaDeRuta::CERRADA;
                    $idsEnOrden = $hoja->paradas->pluck('id')->all();
                @endphp

                <ul class="divide-y divide-neutral-100">
                    @forelse ($hoja->paradas as $i => $parada)
                        <li class="flex items-center gap-3 py-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-sm font-semibold text-neutral-700">{{ $parada->orden }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-neutral-900">
                                    Folio {{ $parada->despacho?->documento?->folio ?? '—' }}
                                    · {{ $parada->despacho?->documento?->cliente?->razon_social ?? 'Sin cliente' }}
                                </p>
                                <p class="truncate text-xs text-neutral-500">
                                    {{ $parada->despacho?->codigo }}
                                    · Cobro: {{ ['pagado' => 'Pagado', 'cobrar_en_entrega' => 'Cobrar en entrega', 'credito' => 'Crédito'][$parada->estado_cobro] ?? $parada->estado_cobro }}
                                </p>
                            </div>
                            <x-despacho.estado-badge :estado="$parada->despacho->estado" class="shrink-0" />
                            @if ($puedeReordenar && $i > 0)
                                @php
                                    $nuevoOrden = $idsEnOrden;
                                    [$nuevoOrden[$i - 1], $nuevoOrden[$i]] = [$nuevoOrden[$i], $nuevoOrden[$i - 1]];
                                @endphp
                                <form method="POST" action="{{ route('admin.hojas-ruta.orden', $hoja) }}">
                                    @csrf
                                    @method('PUT')
                                    @foreach ($nuevoOrden as $id)
                                        <input type="hidden" name="paradas[]" value="{{ $id }}">
                                    @endforeach
                                    <button type="submit" title="Subir una posición"
                                            class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700">
                                        <span class="sr-only">Subir la parada {{ $parada->orden }}</span>
                                        <x-icon.chevron-down class="h-4 w-4 rotate-180" />
                                    </button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-neutral-500">
                            La hoja quedó sin paradas. Se puede completar mientras esté en borrador.
                        </li>
                    @endforelse
                </ul>
            </x-seccion>
        </div>
    </div>
</x-app-layout>
