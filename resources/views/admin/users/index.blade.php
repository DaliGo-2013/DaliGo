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
        <x-status-alert :status="session('status')" class="mb-6" />

        {{-- El margen va acá y no en el componente: estas dos pantallas usan
             `py-12` sin `space-y-*`, así que el nav pone el suyo. --}}
        <div class="mb-6">
            @include('admin.users._tabs')
        </div>

        <x-list-card title="Cuentas" :count="$users->count()" :countLabel="\Illuminate\Support\Str::plural('cuenta', $users->count())">
            @foreach ($users as $user)
                {{-- La fila abre la edicion (pedido del dueño 03-08: fuera el lapiz,
                     la cuenta ES el boton) — pero SOLO para quien tiene 'edit users':
                     la ruta de edicion esta gateada aparte del listado ('view users'),
                     y enlazar a alguien sin el permiso es mandarlo a un 403 (la
                     leccion de urlDestinoPara en la campanita). Para el resto, la
                     fila queda como texto, igual que antes. --}}
                <x-list-row>
                    @can('edit users')
                        <a href="{{ route('admin.users.edit', $user) }}" class="block">
                    @endcan
                        <div class="flex items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 @can('edit users') hover:text-brand-600 @endcan">{{ $user->name }}</p>
                            @if ($user->is(auth()->user()))
                                <x-badge variant="neutral">tú</x-badge>
                            @endif
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $user->email }}</p>
                        <p class="truncate text-xs text-neutral-400">{{ $user->sucursal?->nombre ?? 'Sin sucursal' }}</p>
                    @can('edit users')
                        </a>
                    @endcan

                    <x-slot name="meta">
                        <div class="flex flex-wrap items-center gap-1 sm:w-28 sm:shrink-0">
                            @forelse ($user->roles as $role)
                                <x-badge>{{ \Illuminate\Support\Str::headline($role->name) }}</x-badge>
                            @empty
                                <span class="text-xs text-neutral-400">sin rol</span>
                            @endforelse
                        </div>
                    </x-slot>

                    <x-slot name="actions">
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
</x-app-layout>
