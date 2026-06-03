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
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">Roles</h2>
            <a href="{{ route('admin.roles.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">
                Crear rol
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg bg-neutral-100 px-4 py-3 text-sm font-medium text-neutral-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-medium uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-6 py-3">Rol</th>
                            <th class="px-6 py-3">Permisos</th>
                            <th class="px-6 py-3">Usuarios</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($roles as $role)
                            <tr>
                                <td class="px-6 py-4 font-medium text-neutral-900">
                                    {{ $role->name }}
                                    @if (in_array($role->name, $baseRoles, true))
                                        <span class="ml-1.5 inline-flex rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500">sistema</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse ($role->permissions as $permission)
                                            <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700">{{ $labels[$permission->name] ?? $permission->name }}</span>
                                        @empty
                                            <span class="text-xs text-neutral-400">sin permisos</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-neutral-600">{{ $role->users_count }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Editar</a>
                                        @unless (in_array($role->name, $baseRoles, true))
                                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('¿Eliminar el rol {{ $role->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">Eliminar</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
