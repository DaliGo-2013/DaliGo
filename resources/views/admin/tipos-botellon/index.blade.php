<x-app-layout>
    <x-slot name="header">
        {{-- Ítem del menú desde P-NAV-06: sin Volver (doctrina P-NAV-08). --}}
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
        <x-status-alert :status="session('status')" class="mb-6" />

        <x-list-card title="Tipos de botellón" :count="$tipos->count()" :countLabel="\Illuminate\Support\Str::plural('tipo', $tipos->count())">
            @forelse ($tipos as $tipo)
                {{-- La fila abre la edicion (patron 03-08: fuera el lapiz). El enlace
                     "Ver produccion" queda FUERA del <a>: es otro destino. --}}
                <x-list-row>
                    <a href="{{ route('admin.tipos-botellon.edit', $tipo) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $tipo->nombre }}</p>
                            @unless ($tipo->activo)
                                <x-badge variant="neutral">inactivo</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $tipo->codigo }}</p>
                    </a>

                    <x-slot name="meta">
                        <div class="sm:w-32 sm:shrink-0 sm:text-right">
                            <a href="{{ route('admin.produccion.tipo', $tipo) }}"
                               class="text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">Ver producción</a>
                        </div>
                    </x-slot>

                    <x-slot name="actions">
                        <form method="POST" action="{{ route('admin.tipos-botellon.destroy', $tipo) }}"
                              x-data x-on:submit="if (! confirm('¿Eliminar el tipo ' + @js($tipo->nombre) + '?')) $event.preventDefault()">
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

    </div>
</x-app-layout>
