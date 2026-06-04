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
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">Roles</h2>
                <p class="mt-1 text-sm text-neutral-500">Define qué puede hacer cada perfil.</p>
            </div>
            <a href="{{ route('admin.roles.create') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">
                <x-icon.plus class="h-4 w-4" />
                Crear rol
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-neutral-200 bg-white px-4 py-3 text-sm font-medium text-neutral-700 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Roles</h3>
                    <span class="text-xs font-medium text-neutral-400">{{ $roles->count() }} {{ \Illuminate\Support\Str::plural('rol', $roles->count()) }}</span>
                </div>

                <ul role="list" class="divide-y divide-neutral-100">
                    @foreach ($roles as $role)
                        <li class="flex items-center gap-4 px-6 py-4 transition duration-150 hover:bg-neutral-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-sm font-semibold uppercase text-neutral-600">
                                {{ strtoupper(mb_substr($role->name, 0, 1)) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-medium capitalize text-neutral-900">{{ $role->name }}</p>
                                    @if (in_array($role->name, $baseRoles, true))
                                        <span class="shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500">sistema</span>
                                    @endif
                                </div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @forelse ($role->permissions as $permission)
                                        <span class="inline-flex rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-100">{{ $labels[$permission->name] ?? $permission->name }}</span>
                                    @empty
                                        <span class="text-xs text-neutral-400">sin permisos</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="hidden shrink-0 text-sm text-neutral-500 sm:block">
                                {{ $role->users_count }} {{ \Illuminate\Support\Str::plural('usuario', $role->users_count) }}
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('admin.roles.edit', $role) }}" title="Editar" class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700">
                                    <x-icon.pencil class="h-5 w-5" />
                                    <span class="sr-only">Editar</span>
                                </a>
                                @unless (in_array($role->name, $baseRoles, true))
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('¿Eliminar el rol {{ $role->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Eliminar" class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-red-50 hover:text-red-600">
                                            <x-icon.trash class="h-5 w-5" />
                                            <span class="sr-only">Eliminar</span>
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
