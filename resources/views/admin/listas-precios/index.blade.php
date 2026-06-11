<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Listas de precios" subtitle="Espejo de Bsale: los valores se editan allá. Aquí defines el canal local." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            <x-list-card title="Listas" :count="$listas->count()" :countLabel="\Illuminate\Support\Str::plural('lista', $listas->count())">
                @forelse ($listas as $lista)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($lista->nombre, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $lista->nombre }}</p>
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

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-32 sm:shrink-0 sm:text-right">
                                {{ number_format($lista->precios_count, 0, ',', '.') }} {{ \Illuminate\Support\Str::plural('precio', $lista->precios_count) }}
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.listas-precios.show', $lista)" label="Ver precios" title="Ver precios">
                                <x-icon.eye class="h-5 w-5" />
                            </x-icon-button>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">
                        Aún no hay listas. Corre <span class="font-medium text-neutral-700">php artisan bsale:sync-prices</span> para espejarlas desde Bsale.
                    </li>
                @endforelse
            </x-list-card>
        </div>
    </div>
</x-app-layout>
