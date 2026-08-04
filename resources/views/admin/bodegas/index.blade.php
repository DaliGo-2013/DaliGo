<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inventario" subtitle="Stock por bodega, espejado desde Bsale (solo lectura)." />
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        <x-list-card title="Bodegas" :count="$bodegas->count()" :countLabel="\Illuminate\Support\Str::plural('bodega', $bodegas->count())">
            @forelse ($bodegas as $bodega)
                {{-- La fila entera lleva al stock de la bodega (pedido del dueño
                     03-08: fuera el ojito, la bodega ES el enlace). Mismo patron
                     que el listado de servicio tecnico: el contenido va dentro de
                     un <a class="block"> y el ⓘ queda FUERA del enlace, en el slot
                     meta — un control interactivo dentro de otro es justo lo que
                     este repo ya corrigio en los formularios del QR. --}}
                <x-list-row>
                    <a href="{{ route('admin.bodegas.show', $bodega) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $bodega->nombre }}</p>
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
                    </a>

                    <x-slot name="meta">
                        <div class="flex items-center gap-1 text-sm text-neutral-500 sm:w-48 sm:shrink-0 sm:justify-end">
                            <span>{{ number_format($bodega->stocks_count, 0, ',', '.') }} {{ \Illuminate\Support\Str::plural('producto', $bodega->stocks_count) }} en catálogo</span>
                            <x-info-tip>
                                Cuenta los productos del catálogo con seguimiento en esta bodega (espejo de Bsale) — no indica cuántas unidades hay disponibles. Toca la bodega y usa el filtro "Solo con stock disponible" para ver el stock real.
                            </x-info-tip>
                        </div>
                    </x-slot>

                    <x-slot name="actions">
                        {{-- Flecha DECORATIVA (aria-hidden, sin foco propio): señala que la
                             fila navega, sin volver a ser un segundo control como el ojito. --}}
                        <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    Aún no hay bodegas. Corre <span class="font-medium text-neutral-700">php artisan bsale:sync-stock</span> para espejarlas desde Bsale.
                </li>
            @endforelse
        </x-list-card>
    </div>
</x-app-layout>
