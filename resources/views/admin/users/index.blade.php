<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">Usuarios</h2>
                <p class="mt-1 text-sm text-neutral-500">Cuentas internas del equipo.</p>
            </div>
            @can('create users')
                <a href="{{ route('admin.users.create') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">
                    <x-icon.plus class="h-4 w-4" />
                    Crear cuenta
                </a>
            @endcan
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
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Cuentas</h3>
                    <span class="text-xs font-medium text-neutral-400">{{ $users->count() }} {{ \Illuminate\Support\Str::plural('cuenta', $users->count()) }}</span>
                </div>

                <ul role="list" class="divide-y divide-neutral-100">
                    @foreach ($users as $user)
                        <li class="flex items-center gap-4 px-6 py-4 transition duration-150 hover:bg-neutral-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-sm font-semibold text-neutral-600">
                                {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-medium text-neutral-900">{{ $user->name }}</p>
                                    @if ($user->is(auth()->user()))
                                        <span class="shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500">tú</span>
                                    @endif
                                </div>
                                <p class="truncate text-sm text-neutral-500">{{ $user->email }}</p>
                            </div>

                            <div class="shrink-0">
                                @forelse ($user->roles as $role)
                                    <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-100">{{ $role->name }}</span>
                                @empty
                                    <span class="text-xs text-neutral-400">sin rol</span>
                                @endforelse
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                @can('edit users')
                                    <a href="{{ route('admin.users.edit', $user) }}" title="Editar" class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700">
                                        <x-icon.pencil class="h-5 w-5" />
                                        <span class="sr-only">Editar</span>
                                    </a>
                                @endcan
                                @can('delete users')
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('¿Eliminar la cuenta de {{ $user->email }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Eliminar" class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-red-50 hover:text-red-600">
                                                <x-icon.trash class="h-5 w-5" />
                                                <span class="sr-only">Eliminar</span>
                                            </button>
                                        </form>
                                    @endunless
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
