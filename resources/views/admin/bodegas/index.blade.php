<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inventario" subtitle="Stock espejado desde Bsale; la clasificación por sucursal y propósito es local y editable.">
            @can('manage sucursales')
                <x-slot name="action">
                    <x-primary-button type="button" x-data="" x-on:click="$dispatch('open-modal', 'agregar-bodega')">
                        <x-icon.plus class="h-4 w-4" />
                        <span class="ms-1.5">Agregar bodega</span>
                    </x-primary-button>
                </x-slot>
            @endcan
        </x-page-header>
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
                     este repo ya corrigio en los formularios del QR. Editar vive
                     en el show (no un lapiz aca): la fila no admite un 2º control. --}}
                <x-list-row>
                    <a href="{{ route('admin.bodegas.show', $bodega) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $bodega->nombre }}</p>
                            @if ($bodega->alias)
                                <span class="truncate text-sm text-neutral-500">· {{ $bodega->alias }}</span>
                            @endif
                            @include('admin.bodegas.partials._badges', ['bodega' => $bodega])
                        </div>
                        @php
                            // @endif@if encadenado inline NO compila (bitacora
                            // 2026-06-15): la cadena se arma aca y se pinta una vez.
                            $detalle = collect([
                                $bodega->sucursal?->nombre ?? 'Transversal',
                                $bodega->direccion,
                                $bodega->comuna,
                            ])->filter()->implode(' · ');
                        @endphp
                        <p class="truncate text-sm text-neutral-500">{{ $detalle }}</p>
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

    {{-- Las bodegas NACEN en Bsale (master del stock): el boton instruye, no
         crea. Si D-005 revela que la API permite crear offices, este modal se
         reemplaza por el form real (F4) sin mover el boton. --}}
    <x-modal name="agregar-bodega" maxWidth="lg">
        <div class="p-4 sm:p-6">
            <h2 class="text-lg font-semibold text-neutral-900">Agregar una bodega</h2>
            <div class="mt-3 space-y-3 text-sm text-neutral-600">
                <p>
                    Las bodegas nacen en <span class="font-medium text-neutral-700">Bsale</span>, que es el
                    dueño del stock: créala allá (Configuración → Sucursales/Bodegas) y el espejo la traerá
                    solo — aparece aquí en <span class="font-medium text-neutral-700">15 minutos o menos</span>.
                </p>
                <p>
                    Llegará marcada <span class="font-medium text-neutral-700">«nueva — por clasificar»</span> y
                    con un aviso a quienes administran sucursales, para asignarle sucursal y propósito de inmediato.
                </p>
            </div>
            <div class="mt-5 flex justify-end">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Entendido</x-secondary-button>
            </div>
        </div>
    </x-modal>
</x-app-layout>
