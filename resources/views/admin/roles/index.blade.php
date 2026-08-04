<x-app-layout>
    @php
        $labels = config('permissions.labels');
    @endphp

    <x-slot name="header">
        <x-page-header title="Roles" subtitle="Define qué puede hacer cada perfil.">
            <x-slot name="action">
                <x-button-link :href="route('admin.roles.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Crear rol
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <x-status-alert :status="session('status')" class="mb-6" />

        <x-list-card title="Roles" :count="$roles->count()" :countLabel="\Illuminate\Support\Str::plural('rol', $roles->count())">
            @foreach ($roles as $role)
                {{-- La fila abre la edicion del rol (patron 03-08: fuera el lapiz).
                     Resource entero detras de 'manage roles'. --}}
                <x-list-row>
                    <a href="{{ route('admin.roles.edit', $role) }}" class="block">
                        <div class="flex items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ \Illuminate\Support\Str::headline($role->name) }}</p>
                            @if (in_array($role->name, $baseRoles, true))
                                <x-badge variant="neutral">sistema</x-badge>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @forelse ($role->permissions as $permission)
                                <x-badge>{{ $labels[$permission->name] ?? $permission->name }}</x-badge>
                            @empty
                                <span class="text-xs text-neutral-400">sin permisos</span>
                            @endforelse
                        </div>
                    </a>

                    <x-slot name="meta">
                        <div class="hidden text-sm text-neutral-500 sm:block sm:w-24 sm:shrink-0 sm:text-right">
                            {{ $role->users_count }} {{ \Illuminate\Support\Str::plural('usuario', $role->users_count) }}
                        </div>
                    </x-slot>

                    <x-slot name="actions">
                        @unless (in_array($role->name, $baseRoles, true))
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('¿Eliminar el rol {{ $role->name }}?');">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                    <x-icon.trash class="h-5 w-5" />
                                </x-icon-button>
                            </form>
                        @endunless
                    </x-slot>
                </x-list-row>
            @endforeach
        </x-list-card>
    </div>
</x-app-layout>
