<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Configuración" subtitle="Parámetros globales del sistema." />
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        {{-- Entradas destacadas a las pantallas propias (sus grupos de claves
             están ocultos de este listado: se editan allá, no como JSON). --}}
        <x-list-card title="Pantallas de ajuste" :count="2" countLabel="pantallas">
            <x-list-row>
                <a href="{{ route('admin.configuracion.avisos.edit') }}" class="block">
                    <p class="truncate font-medium text-neutral-900 hover:text-brand-600">Avisos y destinatarios</p>
                    <p class="truncate text-sm text-neutral-500">Quién recibe cada aviso del sistema, por rol y con checkboxes.</p>
                </a>
                <x-slot name="actions">
                    <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                </x-slot>
            </x-list-row>
            <x-list-row>
                <a href="{{ route('admin.configuracion.sesiones.edit') }}" class="block">
                    <p class="truncate font-medium text-neutral-900 hover:text-brand-600">Sesiones por usuario</p>
                    <p class="truncate text-sm text-neutral-500">Cuántas sesiones abiertas a la vez permite cada cuenta (default, por rol o por persona).</p>
                </a>
                <x-slot name="actions">
                    <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                </x-slot>
            </x-list-row>
        </x-list-card>

        @forelse ($grupos as $grupo => $configs)
            <x-list-card
                :title="\Illuminate\Support\Str::headline($grupo)"
                :count="$configs->count()"
                :countLabel="\Illuminate\Support\Str::plural('ajuste', $configs->count())"
            >
                @foreach ($configs as $config)
                    {{-- La fila abre la edicion (patron 03-08: fuera el lapiz).
                         Resource entero detras de 'manage settings'. --}}
                    <x-list-row>
                        <a href="{{ route('admin.configuracion.edit', $config) }}" class="block">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">
                                {{ \Illuminate\Support\Str::headline($config->clave) }}
                            </p>
                            @if ($config->descripcion)
                                <p class="truncate text-sm text-neutral-500">{{ $config->descripcion }}</p>
                            @endif
                        </a>

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
                            <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
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
</x-app-layout>
