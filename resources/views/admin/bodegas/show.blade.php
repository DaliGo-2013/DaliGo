<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$bodega->nombre" subtitle="Stock espejado desde Bsale (solo lectura); la clasificación de la bodega es local y editable."
                       :back="route('admin.bodegas.index')" backTitle="Volver a bodegas">
            @can('manage sucursales')
                <x-slot name="action">
                    <div class="flex flex-wrap items-center gap-2">
                        @unless ($bodega->enBaja())
                            {{-- «Eliminar» = baja lógica con guarda de traslado
                                 (M04-F2): el wizard decide si es inmediata o
                                 exige orden. Jamás un delete. --}}
                            <x-secondary-button-link :href="route('admin.bodegas.baja', $bodega)"
                                                     title="Dar de baja esta bodega (con traslado si tiene stock)">
                                Dar de baja
                            </x-secondary-button-link>
                        @endunless
                        <x-button-link :href="route('admin.bodegas.edit', $bodega)">Editar bodega</x-button-link>
                    </div>
                </x-slot>
            @endcan
        </x-page-header>
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-neutral-200 bg-white p-3 text-sm text-neutral-600 shadow-sm sm:p-4">
            @include('admin.bodegas.partials._badges', ['bodega' => $bodega])
            <span>{{ $bodega->sucursal?->nombre ?? 'Transversal' }}</span>
            @if ($bodega->alias)
                <span class="text-neutral-400">· {{ $bodega->alias }}</span>
            @endif
            <span class="text-neutral-400">· {{ number_format($bodega->stocks_count, 0, ',', '.') }} {{ \Illuminate\Support\Str::plural('producto', $bodega->stocks_count) }}</span>
            @if ($bodega->direccion)
                <span class="text-neutral-400">· {{ $bodega->direccion }}@if ($bodega->comuna), {{ $bodega->comuna }}@endif</span>
            @endif
        </div>

        @if ($traslados->isNotEmpty())
            {{-- Las órdenes de baja de esta bodega (F2): el descubrimiento es
                 acá, junto a la bodega — sin index de órdenes hasta F3. --}}
            <x-list-card title="Órdenes de traslado por baja" :count="$traslados->count()"
                         :countLabel="\Illuminate\Support\Str::plural('orden', $traslados->count())">
                @foreach ($traslados as $orden)
                    <x-list-row>
                        <a href="{{ route('admin.bodegas.traslados.show', $orden) }}" class="block">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-medium text-neutral-900 hover:text-brand-600">Orden #{{ $orden->id }} → {{ $orden->destino->nombre }}</p>
                                @if ($orden->estado === \App\Models\BodegaTraslado::PENDIENTE)
                                    <x-badge variant="brand">pendiente</x-badge>
                                @elseif ($orden->estado === \App\Models\BodegaTraslado::COMPLETADO)
                                    <x-badge variant="neutral">completada</x-badge>
                                @else
                                    <x-badge variant="neutral">anulada</x-badge>
                                @endif
                            </div>
                            <p class="truncate text-sm text-neutral-500">{{ $orden->solicitante_nombre }} · {{ $orden->created_at?->enChile()->format('d-m-Y H:i') }}</p>
                        </a>
                        <x-slot name="actions">
                            <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                        </x-slot>
                    </x-list-row>
                @endforeach
            </x-list-card>
        @endif

        <form method="GET" action="{{ route('admin.bodegas.show', $bodega) }}"
              class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <x-input-label for="q" value="Buscar (SKU o nombre)" />
                <x-text-input id="q" name="q" class="mt-1.5" type="text" :value="$filtros['q'] ?? ''" placeholder="ej. botellón" />
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-neutral-700">
                <x-checkbox name="con_stock" value="1" :checked="($filtros['con_stock'] ?? '') === '1'" />
                Solo con stock disponible
            </label>
            <div class="flex items-center gap-3">
                <x-primary-button>Filtrar</x-primary-button>
                @if (array_filter($filtros))
                    <x-secondary-link :href="route('admin.bodegas.show', $bodega)">Limpiar</x-secondary-link>
                @endif
            </div>
        </form>

        <x-list-card title="Stock" :count="$stocks->total()" :countLabel="\Illuminate\Support\Str::plural('producto', $stocks->total())">
            @forelse ($stocks as $stock)
                <x-list-row>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate font-medium text-neutral-900">{{ $stock->producto->nombre }}</p>
                        @unless ($stock->producto->activo)
                            <x-badge variant="neutral">inactivo</x-badge>
                        @endunless
                    </div>
                    <p class="truncate text-sm text-neutral-500">{{ $stock->producto->sku }}</p>

                    <x-slot name="meta">
                        <div class="text-sm sm:w-48 sm:shrink-0 sm:text-right">
                            <p class="font-medium text-neutral-900">{{ \App\Models\Stock::formatear($stock->stock_disponible) }} disp.</p>
                            <p class="text-xs text-neutral-500">{{ \App\Models\Stock::formatear($stock->stock_real) }} real · {{ \App\Models\Stock::formatear($stock->stock_reservado) }} reserv.</p>
                        </div>
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    @if (array_filter($filtros))
                        Sin resultados para el filtro.
                    @else
                        Esta bodega no tiene stock espejado. Corre <span class="font-medium text-neutral-700">php artisan bsale:sync-stock</span>.
                    @endif
                </li>
            @endforelse
        </x-list-card>

        @if ($stocks->hasPages())
            <div>{{ $stocks->links() }}</div>
        @endif

    </div>
</x-app-layout>
