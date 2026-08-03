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
        <x-status-alert :status="session('status')" class="mb-6" />

        <x-list-card title="Sucursales" :count="$sucursales->count()" :countLabel="\Illuminate\Support\Str::plural('sucursal', $sucursales->count())">
            @forelse ($sucursales as $sucursal)
                {{-- La fila entera abre la edicion (pedido del dueño 03-08: fuera el
                     lapiz, la sucursal ES el boton). Mismo patron que bodegas y ST.
                     Sin @can: el resource completo esta detras de 'manage sucursales',
                     asi que quien puede VER este listado puede editar. --}}
                <x-list-row>
                    <a href="{{ route('admin.sucursales.edit', $sucursal) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $sucursal->nombre }}</p>
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
                    </a>

                    <x-slot name="meta">
                        <div class="text-sm text-neutral-500 sm:w-28 sm:shrink-0 sm:text-right">
                            {{ $sucursal->users_count }} {{ \Illuminate\Support\Str::plural('usuario', $sucursal->users_count) }}
                        </div>
                    </x-slot>

                    <x-slot name="actions">
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
</x-app-layout>
