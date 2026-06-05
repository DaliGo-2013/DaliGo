<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Configuración" subtitle="Parámetros globales del sistema." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            @forelse ($grupos as $grupo => $configs)
                <x-list-card
                    :title="\Illuminate\Support\Str::headline($grupo)"
                    :count="$configs->count()"
                    :countLabel="\Illuminate\Support\Str::plural('ajuste', $configs->count())"
                >
                    @foreach ($configs as $config)
                        <x-list-row>
                            <p class="truncate font-medium text-neutral-900">
                                {{ \Illuminate\Support\Str::headline($config->clave) }}
                            </p>
                            @if ($config->descripcion)
                                <p class="truncate text-sm text-neutral-500">{{ $config->descripcion }}</p>
                            @endif

                            <x-slot name="meta">
                                <div class="text-sm text-neutral-700 sm:w-48 sm:shrink-0 sm:text-right">
                                    @switch($config->tipo)
                                        @case(\App\Models\Configuracion::TIPO_BOOLEAN)
                                            <x-badge variant="neutral">{{ $config->valor_tipado ? 'Sí' : 'No' }}</x-badge>
                                            @break
                                        @case(\App\Models\Configuracion::TIPO_JSON)
                                            <code class="text-xs text-neutral-600">{{ \Illuminate\Support\Str::limit($config->valor, 40) }}</code>
                                            @break
                                        @default
                                            <span class="truncate">{{ $config->valor }}</span>
                                    @endswitch
                                </div>
                            </x-slot>

                            <x-slot name="actions">
                                <x-icon-button :href="route('admin.configuracion.edit', $config)" label="Editar" title="Editar">
                                    <x-icon.pencil class="h-5 w-5" />
                                </x-icon-button>
                            </x-slot>
                        </x-list-row>
                    @endforeach
                </x-list-card>
            @empty
                <x-list-card title="Configuración">
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">No hay parámetros configurados.</li>
                </x-list-card>
            @endforelse
        </div>
    </div>
</x-app-layout>
