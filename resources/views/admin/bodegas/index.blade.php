<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inventario" subtitle="Stock por bodega, espejado desde Bsale (solo lectura)." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            <x-list-card title="Bodegas" :count="$bodegas->count()" :countLabel="\Illuminate\Support\Str::plural('bodega', $bodegas->count())">
                @forelse ($bodegas as $bodega)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($bodega->nombre, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $bodega->nombre }}</p>
                            @if ($bodega->es_virtual)
                                <x-badge variant="neutral">virtual</x-badge>
                            @endif
                            @unless ($bodega->activa)
                                <x-badge variant="neutral">inactiva</x-badge>
                            @endunless
                        </div>
                        @if ($bodega->comuna || $bodega->direccion)
                            <p class="truncate text-sm text-neutral-500">{{ $bodega->direccion }}@if ($bodega->comuna) · {{ $bodega->comuna }}@endif</p>
                        @endif

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-32 sm:shrink-0 sm:text-right">
                                {{ number_format($bodega->stocks_count, 0, ',', '.') }} {{ \Illuminate\Support\Str::plural('producto', $bodega->stocks_count) }}
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.bodegas.show', $bodega)" label="Ver stock" title="Ver stock">
                                <x-icon.eye class="h-5 w-5" />
                            </x-icon-button>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">
                        Aún no hay bodegas. Corre <span class="font-medium text-neutral-700">php artisan bsale:sync-stock</span> para espejarlas desde Bsale.
                    </li>
                @endforelse
            </x-list-card>
        </div>
    </div>
</x-app-layout>
