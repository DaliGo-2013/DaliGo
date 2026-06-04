<x-app-layout>
    @php
        $labels = [
            'view users' => 'Ver usuarios',
            'create users' => 'Crear usuarios',
            'edit users' => 'Editar usuarios',
            'delete users' => 'Eliminar usuarios',
            'manage roles' => 'Gestionar roles',
        ];
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
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            <x-list-card title="Roles" :count="$roles->count()" :countLabel="\Illuminate\Support\Str::plural('rol', $roles->count())">
                @foreach ($roles as $role)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($role->name, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex items-center gap-2">
                            <p class="truncate font-medium capitalize text-neutral-900">{{ $role->name }}</p>
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

                        <x-slot name="meta">
                            <div class="hidden text-sm text-neutral-500 sm:block sm:w-24 sm:shrink-0 sm:text-right">
                                {{ $role->users_count }} {{ \Illuminate\Support\Str::plural('usuario', $role->users_count) }}
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.roles.edit', $role)" label="Editar" title="Editar">
                                <x-icon.pencil class="h-5 w-5" />
                            </x-icon-button>
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
    </div>
</x-app-layout>
