<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Hojas de ruta" subtitle="Cada salida de un vehículo, con sus paradas y la cadena de autorizaciones.">
            <x-slot name="action">
                @can('manage hojas ruta')
                    <x-button-link :href="route('admin.hojas-ruta.create')">
                        <x-icon.plus class="h-4 w-4" />
                        Nueva hoja
                    </x-button-link>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="space-y-6">

            <x-status-alert :status="session('status')" />

            {{-- Filtro por estado --}}
            <form method="GET" action="{{ route('admin.hojas-ruta.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4 sm:flex-row sm:items-end">
                <div class="flex-1 sm:max-w-xs">
                    <x-input-label for="estado" value="Estado" />
                    <x-select id="estado" name="estado" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($estados as $e)
                            <option value="{{ $e }}" @selected($filtroEstado === $e)>{{ ucfirst(str_replace('_', ' ', $e)) }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if ($filtroEstado)
                        <x-secondary-link :href="route('admin.hojas-ruta.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Hojas de ruta" :count="$hojas->total()" :countLabel="\Illuminate\Support\Str::plural('hoja', $hojas->total())">
                @forelse ($hojas as $hoja)
                    {{-- La fila ES el enlace (patrón 03-08: fuera el ojito). --}}
                    <x-list-row>
                        <a href="{{ route('admin.hojas-ruta.show', $hoja) }}" class="block">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-medium text-neutral-900 hover:text-brand-600">Folio {{ $hoja->folio }}</p>
                                <x-hoja-ruta.estado-badge :estado="$hoja->estado" />
                            </div>
                            <p class="truncate text-sm text-neutral-500">
                                {{ $hoja->zona?->nombre ?? 'Sin zona' }}
                                · {{ $hoja->paradas_count }} {{ \Illuminate\Support\Str::plural('parada', $hoja->paradas_count) }}
                                · {{ $hoja->patente }}
                                @if ($hoja->conductor)
                                    · {{ $hoja->conductor->name }}
                                @endif
                            </p>
                        </a>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-40 sm:shrink-0 sm:text-right">
                                {{ $hoja->created_at?->enChile()->format('d-m-Y H:i') }}
                            </div>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">
                        No hay hojas de ruta aún. La primera parte con el folio 1000.
                    </li>
                @endforelse
            </x-list-card>

            @if ($hojas->hasPages())
                <div>{{ $hojas->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
