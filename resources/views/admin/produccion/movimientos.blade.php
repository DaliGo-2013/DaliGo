<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Kardex de producción"
                       subtitle="Movimientos generados al aprobar reportes. Ledger local; no toca el stock de Bsale."
                       :back="route('admin.produccion.index')" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Resumen del filtro actual --}}
            <div class="dg-enter grid grid-cols-2 gap-4 sm:grid-cols-4">
                @php
                    $chips = [
                        ['Consumo preforma', $resumen[\App\Models\ProduccionMovimiento::TIPO_CONSUMO_PREFORMA] ?? 0],
                        ['Producción 1ª', $resumen[\App\Models\ProduccionMovimiento::TIPO_PRODUCCION_PRIMERA] ?? 0],
                        ['Producción 2ª', $resumen[\App\Models\ProduccionMovimiento::TIPO_PRODUCCION_SEGUNDA] ?? 0],
                        ['Merma', $resumen[\App\Models\ProduccionMovimiento::TIPO_MERMA] ?? 0],
                    ];
                @endphp
                @foreach ($chips as [$label, $valor])
                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ number_format($valor, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.produccion.movimientos') }}"
                  class="dg-enter grid grid-cols-1 gap-4 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-input-label for="q" value="Producto (nombre o SKU)" />
                    <x-text-input id="q" class="mt-1.5 w-full" type="search" name="q" :value="$filtros['q'] ?? ''" placeholder="Buscar…" />
                </div>
                <div>
                    <x-input-label for="tipo" value="Tipo" />
                    <x-select id="tipo" name="tipo" class="mt-1.5 w-full">
                        <option value="">Todos</option>
                        @foreach ($tipos as $tipo)
                            <option value="{{ $tipo }}" @selected(($filtros['tipo'] ?? '') === $tipo)>{{ $etiquetasTipos[$tipo] ?? $tipo }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-input-label for="desde" value="Desde" />
                    <x-text-input id="desde" class="mt-1.5 w-full" type="date" name="desde" :value="$filtros['desde'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="hasta" value="Hasta" />
                    <x-text-input id="hasta" class="mt-1.5 w-full" type="date" name="hasta" :value="$filtros['hasta'] ?? ''" />
                </div>
                <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-4">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.produccion.movimientos')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Movimientos" :count="$movimientos->total()" :countLabel="\Illuminate\Support\Str::plural('movimiento', $movimientos->total())">
                @forelse ($movimientos as $movimiento)
                    <x-list-row>
                        <p class="truncate font-medium text-neutral-900">
                            {{ $etiquetasTipos[$movimiento->tipo] ?? $movimiento->tipo }}
                        </p>
                        <p class="truncate text-sm text-neutral-500">
                            {{ $movimiento->producto?->nombre ?? 'Sin producto enlazado' }}
                            @if ($movimiento->producto?->sku)
                                · {{ $movimiento->producto->sku }}
                            @endif
                        </p>

                        <x-slot name="meta">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
                                <span class="font-semibold {{ $movimiento->tipo === \App\Models\ProduccionMovimiento::TIPO_PRODUCCION_PRIMERA || $movimiento->tipo === \App\Models\ProduccionMovimiento::TIPO_PRODUCCION_SEGUNDA ? 'text-brand-600' : 'text-neutral-900' }}">
                                    {{ number_format($movimiento->cantidad, 0, ',', '.') }}
                                </span>
                                <span class="text-neutral-400">{{ $movimiento->fecha->format('d-m-Y') }}</span>
                                @if ($movimiento->reporte?->soplador)
                                    <span class="text-neutral-400">{{ $movimiento->reporte->soplador->name }}</span>
                                @endif
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            @if ($movimiento->reporte)
                                <a href="{{ route('admin.produccion.reporte.show', $movimiento->reporte) }}"
                                   class="whitespace-nowrap text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">
                                    Ver reporte
                                </a>
                            @endif
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-neutral-500">
                        No hay movimientos para este filtro. El kardex se genera al <span class="font-medium text-neutral-700">aprobar</span> un reporte.
                    </li>
                @endforelse
            </x-list-card>

            @if ($movimientos->hasPages())
                <div>{{ $movimientos->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
