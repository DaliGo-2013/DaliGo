<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$bodega->nombre" subtitle="Stock espejado desde Bsale (solo lectura)." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600 shadow-sm">
                @unless ($bodega->activa)
                    <x-badge variant="neutral">inactiva</x-badge>
                @endunless
                @if ($bodega->es_virtual)
                    <x-badge variant="neutral">virtual</x-badge>
                @endif
                <span>{{ number_format($bodega->stocks_count, 0, ',', '.') }} {{ \Illuminate\Support\Str::plural('producto', $bodega->stocks_count) }}</span>
                @if ($bodega->direccion)
                    <span class="text-neutral-400">· {{ $bodega->direccion }}@if ($bodega->comuna), {{ $bodega->comuna }}@endif</span>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.bodegas.show', $bodega) }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
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
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($stock->producto->nombre, 0, 1) }}</x-avatar>
                        </x-slot>

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

            <div>
                <x-secondary-link :href="route('admin.bodegas.index')">← Volver a bodegas</x-secondary-link>
            </div>
        </div>
    </div>
</x-app-layout>
