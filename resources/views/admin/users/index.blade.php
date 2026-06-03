<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">Usuarios</h2>
            <a href="{{ route('admin.users.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">
                Crear cuenta
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
                            <th class="px-6 py-3">Nombre</th>
                            <th class="px-6 py-3">Correo</th>
                            <th class="px-6 py-3">Rol</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-6 py-4 font-medium text-neutral-900">{{ $user->name }}</td>
                                <td class="px-6 py-4 text-neutral-600">{{ $user->email }}</td>
                                <td class="px-6 py-4">
                                    @forelse ($user->roles as $role)
                                        <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-xs text-neutral-400">sin rol</span>
                                    @endforelse
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('¿Eliminar la cuenta de {{ $user->email }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">Eliminar</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
