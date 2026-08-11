<x-app-layout>
    <x-slot name="header">
        {{-- Ítem del menú (doctrina P-NAV-08: sin Volver). --}}
        <x-page-header title="Moldes"
                       subtitle="Cada molde acumula sus ciclos solo, al aprobar reportes, y avisa cuando le toca mantención.">
            <x-slot name="action">
                <x-button-link :href="route('admin.moldes.create')">
                    <x-icon.plus class="h-4 w-4" />
                    <span class="ms-1.5">Agregar molde</span>
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        <x-list-card title="Moldes" :count="$moldes->count()" :countLabel="\Illuminate\Support\Str::plural('molde', $moldes->count())">
            @forelse ($moldes as $molde)
                @php
                    // La cadena se arma acá (gotcha @endif@if, bitácora 2026-06-15).
                    $detalle = collect([
                        $molde->tipoBotellon?->nombre,
                        number_format($molde->ciclos_acumulados, 0, ',', '.').' ciclos',
                        $molde->umbralLabel(),
                    ])->filter()->implode(' · ');
                @endphp
                <x-list-row>
                    <a href="{{ route('admin.moldes.show', $molde) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $molde->nombre }}</p>
                            <x-badge :variant="$molde->varianteBadge()">{{ $molde->estadoLabel() }}</x-badge>
                            @if ($molde->correctivaPendiente())
                                <x-badge variant="brand">correctiva pendiente</x-badge>
                            @endif
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $detalle }}</p>
                    </a>

                    <x-slot name="actions">
                        <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    Aún no hay moldes. Agrega el primero: su contador de ciclos se alimenta solo con cada reporte aprobado.
                </li>
            @endforelse
        </x-list-card>
    </div>
</x-app-layout>
