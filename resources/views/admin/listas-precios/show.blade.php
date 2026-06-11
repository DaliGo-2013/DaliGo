<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$lista->nombre" subtitle="Valores espejados desde Bsale (solo lectura)." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Cabecera: datos de la lista + canal local editable --}}
            <div class="flex flex-col gap-4 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-wrap items-center gap-2 text-sm text-neutral-600">
                    @if ($lista->bsale_coin_id === \App\Models\ListaPrecio::COIN_CLP)
                        <x-badge variant="neutral">CLP</x-badge>
                    @endif
                    @unless ($lista->activa)
                        <x-badge variant="neutral">inactiva</x-badge>
                    @endunless
                    <span>{{ number_format($lista->precios_count, 0, ',', '.') }} {{ \Illuminate\Support\Str::plural('precio', $lista->precios_count) }}</span>
                    @if ($lista->descripcion)
                        <span class="text-neutral-400">· {{ $lista->descripcion }}</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.listas-precios.update', $lista) }}" class="flex items-end gap-3">
                    @csrf
                    @method('PUT')
                    <div class="w-full sm:w-56">
                        <x-input-label for="canal" value="Canal (convención DaliGo)" />
                        <x-text-input id="canal" name="canal" class="mt-1.5" type="text" :value="old('canal', $lista->canal)" placeholder="ej. mayorista, web" />
                        <x-input-error :messages="$errors->get('canal')" class="mt-2" />
                    </div>
                    <x-primary-button>Guardar</x-primary-button>
                </form>
            </div>

            {{-- Filtro --}}
            <form method="GET" action="{{ route('admin.listas-precios.show', $lista) }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="q" value="Buscar (SKU o nombre)" />
                    <x-text-input id="q" name="q" class="mt-1.5" type="text" :value="$filtros['q'] ?? ''" placeholder="ej. botellón" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.listas-precios.show', $lista)">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Precios" :count="$precios->total()" :countLabel="\Illuminate\Support\Str::plural('precio', $precios->total())">
                @forelse ($precios as $precio)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($precio->producto->nombre, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $precio->producto->nombre }}</p>
                            @unless ($precio->producto->activo)
                                <x-badge variant="neutral">inactivo</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $precio->producto->sku }}</p>

                        <x-slot name="meta">
                            <div class="text-sm sm:w-44 sm:shrink-0 sm:text-right">
                                <p class="font-medium text-neutral-900">${{ \App\Models\Precio::formatear($precio->precio_con_iva) ?? '—' }}</p>
                                <p class="text-xs text-neutral-500">neto ${{ \App\Models\Precio::formatear($precio->precio_neto) ?? '—' }}</p>
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.productos.edit', $precio->producto)" label="Ver producto" title="Ver producto">
                                <x-icon.pencil class="h-5 w-5" />
                            </x-icon-button>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">
                        @if (array_filter($filtros))
                            Sin resultados para el filtro.
                        @else
                            Esta lista aún no tiene precios espejados. Corre <span class="font-medium text-neutral-700">php artisan bsale:sync-prices</span>.
                        @endif
                    </li>
                @endforelse
            </x-list-card>

            @if ($precios->hasPages())
                <div>{{ $precios->links() }}</div>
            @endif

            <div>
                <x-secondary-link :href="route('admin.listas-precios.index')">← Volver a listas</x-secondary-link>
            </div>
        </div>
    </div>
</x-app-layout>
