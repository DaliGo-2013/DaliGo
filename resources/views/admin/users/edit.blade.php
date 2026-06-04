<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar rol" />
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
                        <x-select id="role" name="role" class="mt-1.5" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role', $user->roles->first()?->name) === $role)>{{ $role }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <x-form-footer :cancel="route('admin.users.index')">
                        <x-primary-button>Guardar rol</x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
