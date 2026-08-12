<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Listas de precios" subtitle="Espejo de Bsale: los valores se editan allá. Aquí defines el canal local." />
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        @include('admin.catalogo._tabs')

        <x-list-card title="Listas" :count="$listas->count()" :countLabel="\Illuminate\Support\Str::plural('lista', $listas->count())">
            @forelse ($listas as $lista)
                {{-- La fila entera abre la lista (patron bodegas/ST, pedido del dueño
                     03-08: fuera el ojito). Sin @can: el resource entero esta detras
                     de 'manage productos'. --}}
                <x-list-row>
                    <a href="{{ route('admin.listas-precios.show', $lista) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $lista->nombre }}</p>
                            @if ($lista->bsale_coin_id === \App\Models\ListaPrecio::COIN_CLP)
                                <x-badge variant="neutral">CLP</x-badge>
                            @endif
                            @if ($lista->canal)
                                <x-badge>{{ $lista->canal }}</x-badge>
                            @endif
                            @unless ($lista->activa)
                                <x-badge variant="neutral">inactiva</x-badge>
                            @endunless
                        </div>
                        @if ($lista->descripcion)
                            <p class="truncate text-sm text-neutral-500">{{ $lista->descripcion }}</p>
                        @endif
                    </a>

                    <x-slot name="meta">
                        <div class="text-sm text-neutral-500 sm:w-32 sm:shrink-0 sm:text-right">
                            {{ number_format($lista->precios_count, 0, ',', '.') }} {{ \Illuminate\Support\Str::plural('precio', $lista->precios_count) }}
                        </div>
                    </x-slot>

                    <x-slot name="actions">
                        <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    Aún no hay listas. Corre <span class="font-medium text-neutral-700">php artisan bsale:sync-prices</span> para espejarlas desde Bsale.
                </li>
            @endforelse
        </x-list-card>
    </div>
</x-app-layout>
