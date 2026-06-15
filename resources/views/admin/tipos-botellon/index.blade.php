<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tipos de botellón" subtitle="Los formatos que el soplador selecciona al registrar producción.">
            <x-slot name="action">
                <x-button-link :href="route('admin.tipos-botellon.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Crear tipo
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            <x-list-card title="Tipos de botellón" :count="$tipos->count()" :countLabel="\Illuminate\Support\Str::plural('tipo', $tipos->count())">
                @forelse ($tipos as $tipo)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($tipo->nombre, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $tipo->nombre }}</p>
                            @unless ($tipo->activo)
                                <x-badge variant="neutral">inactivo</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $tipo->codigo }}</p>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-28 sm:shrink-0 sm:text-right">
                                {{ $tipo->registros_count }} {{ \Illuminate\Support\Str::plural('registro', $tipo->registros_count) }}
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.tipos-botellon.edit', $tipo)" label="Editar" title="Editar">
                                <x-icon.pencil class="h-5 w-5" />
                            </x-icon-button>
                            <form method="POST" action="{{ route('admin.tipos-botellon.destroy', $tipo) }}" onsubmit="return confirm('¿Eliminar el tipo {{ $tipo->nombre }}?');">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                    <x-icon.trash class="h-5 w-5" />
                                </x-icon-button>
                            </form>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">Aún no hay tipos de botellón.</li>
                @endforelse
            </x-list-card>

            <div class="mt-6">
                <x-secondary-link :href="route('admin.produccion.index')">← Volver a Producción</x-secondary-link>
            </div>
        </div>
    </div>
</x-app-layout>
