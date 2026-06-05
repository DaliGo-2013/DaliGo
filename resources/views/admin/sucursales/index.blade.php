<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Sucursales" subtitle="Bodegas y sucursales de DALI.">
            <x-slot name="action">
                <x-button-link :href="route('admin.sucursales.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Crear sucursal
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            <x-list-card title="Sucursales" :count="$sucursales->count()" :countLabel="\Illuminate\Support\Str::plural('sucursal', $sucursales->count())">
                @forelse ($sucursales as $sucursal)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($sucursal->nombre, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $sucursal->nombre }}</p>
                            @if ($sucursal->es_central)
                                <x-badge variant="neutral">central</x-badge>
                            @endif
                            @unless ($sucursal->activa)
                                <x-badge variant="neutral">inactiva</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">
                            {{ $sucursal->codigo }}@if ($sucursal->ciudad) · {{ $sucursal->ciudad }}@endif
                        </p>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-28 sm:shrink-0 sm:text-right">
                                {{ $sucursal->users_count }} {{ \Illuminate\Support\Str::plural('usuario', $sucursal->users_count) }}
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.sucursales.edit', $sucursal)" label="Editar" title="Editar">
                                <x-icon.pencil class="h-5 w-5" />
                            </x-icon-button>
                            <form method="POST" action="{{ route('admin.sucursales.destroy', $sucursal) }}" onsubmit="return confirm('¿Eliminar la sucursal {{ $sucursal->nombre }}?');">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                    <x-icon.trash class="h-5 w-5" />
                                </x-icon-button>
                            </form>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">Aún no hay sucursales.</li>
                @endforelse
            </x-list-card>
        </div>
    </div>
</x-app-layout>
