<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Máquinas" subtitle="Máquinas sopladoras por sucursal.">
            <x-slot name="action">
                <x-button-link :href="route('admin.maquinas.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Crear máquina
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            <x-list-card title="Máquinas" :count="$maquinas->count()" :countLabel="\Illuminate\Support\Str::plural('máquina', $maquinas->count())">
                @forelse ($maquinas as $maquina)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($maquina->nombre, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $maquina->nombre }}</p>
                            @unless ($maquina->activa)
                                <x-badge variant="neutral">inactiva</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $maquina->sucursal->nombre }}</p>

                        <x-slot name="meta">
                            <div class="sm:w-32 sm:shrink-0 sm:text-right">
                                <a href="{{ route('admin.produccion.maquina', $maquina) }}"
                                   class="text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">Ver rendimiento</a>
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.maquinas.edit', $maquina)" label="Editar" title="Editar">
                                <x-icon.pencil class="h-5 w-5" />
                            </x-icon-button>
                            <form method="POST" action="{{ route('admin.maquinas.destroy', $maquina) }}"
                                  x-data x-on:submit="if (! confirm('¿Eliminar la máquina ' + @js($maquina->nombre) + '?')) $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                    <x-icon.trash class="h-5 w-5" />
                                </x-icon-button>
                            </form>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">
                        Aún no hay máquinas. Créalas para que los sopladores puedan atribuir su producción.
                    </li>
                @endforelse
            </x-list-card>

            <div class="mt-6">
                <x-secondary-link :href="route('admin.produccion.index')">← Volver a Producción</x-secondary-link>
            </div>
        </div>
    </div>
</x-app-layout>
