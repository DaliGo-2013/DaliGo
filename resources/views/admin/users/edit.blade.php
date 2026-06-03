<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-neutral-900">Editar rol</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <dl class="mb-6 space-y-3 border-b border-neutral-100 pb-6 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-500">Nombre</dt>
                        <dd class="font-medium text-neutral-900">{{ $user->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-500">Correo</dt>
                        <dd class="font-medium text-neutral-900">{{ $user->email }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="role" value="Rol" />
                        <select id="role" name="role" required
                            class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role', $user->roles->first()?->name) === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-4 pt-2">
                        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-neutral-500 hover:text-neutral-800">Cancelar</a>
                        <x-primary-button>Guardar rol</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
