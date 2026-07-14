<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Despachos" subtitle="Retiros y entregas sobre los documentos de venta de Bsale.">
            <x-slot name="action">
                <x-button-link :href="route('admin.despachos.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Nuevo despacho
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <x-status-alert :status="session('status')" />

            {{-- Filtro por estado --}}
            <form method="GET" action="{{ route('admin.despachos.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
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
                        <x-secondary-link :href="route('admin.despachos.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Despachos" :count="$despachos->total()" :countLabel="\Illuminate\Support\Str::plural('despacho', $despachos->total())">
                @forelse ($despachos as $despacho)
                    <x-list-row>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $despacho->codigo }}</p>
                            <x-despacho.estado-badge :estado="$despacho->estado" />
                        </div>
                        <p class="truncate text-sm text-neutral-500">
                            Folio {{ $despacho->documento?->folio ?? '—' }}
                            · {{ $despacho->documento?->cliente?->razon_social ?? 'Sin cliente' }}
                            @if ($despacho->zona)
                                · {{ $despacho->zona->nombre }}
                            @endif
                            @if ($despacho->conductor)
                                · {{ $despacho->conductor->name }}
                            @endif
                        </p>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-40 sm:shrink-0 sm:text-right">
                                {{ $despacho->created_at?->format('d-m-Y H:i') }}
                            </div>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">
                        No hay despachos aún. Crea el primero desde un documento de venta espejado.
                    </li>
                @endforelse
            </x-list-card>

            @if ($despachos->hasPages())
                <div>{{ $despachos->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
