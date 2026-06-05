<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Usuarios" subtitle="Cuentas internas del equipo.">
            @can('create users')
                <x-slot name="action">
                    <x-button-link :href="route('admin.users.create')">
                        <x-icon.plus class="h-4 w-4" />
                        Crear cuenta
                    </x-button-link>
                </x-slot>
            @endcan
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            <x-list-card title="Cuentas" :count="$users->count()" :countLabel="\Illuminate\Support\Str::plural('cuenta', $users->count())">
                @foreach ($users as $user)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($user->name, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $user->name }}</p>
                            @if ($user->is(auth()->user()))
                                <x-badge variant="neutral">tú</x-badge>
                            @endif
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $user->email }}</p>
                        <p class="truncate text-xs text-neutral-400">{{ $user->sucursal?->nombre ?? 'Sin sucursal' }}</p>

                        <x-slot name="meta">
                            <div class="flex flex-wrap items-center gap-1 sm:w-28 sm:shrink-0">
                                @forelse ($user->roles as $role)
                                    <x-badge>{{ $role->name }}</x-badge>
                                @empty
                                    <span class="text-xs text-neutral-400">sin rol</span>
                                @endforelse
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            @can('edit users')
                                <x-icon-button :href="route('admin.users.edit', $user)" label="Editar" title="Editar">
                                    <x-icon.pencil class="h-5 w-5" />
                                </x-icon-button>
                            @endcan
                            @can('delete users')
                                @unless ($user->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('¿Eliminar la cuenta de {{ $user->email }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                            <x-icon.trash class="h-5 w-5" />
                                        </x-icon-button>
                                    </form>
                                @endunless
                            @endcan
                        </x-slot>
                    </x-list-row>
                @endforeach
            </x-list-card>
        </div>
    </div>
</x-app-layout>
