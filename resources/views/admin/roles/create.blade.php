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
        <h2 class="text-xl font-semibold leading-tight text-neutral-900">Crear rol</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nombre del rol" />
                        <x-text-input id="name" class="mt-1.5" type="text" name="name" :value="old('name')" required autofocus placeholder="ej. supervisor" />
                        <x-input-hint>Letras, numeros, espacios, guiones y guiones bajos.</x-input-hint>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label value="Permisos" />
                        <div class="mt-1.5 space-y-2">
                            @foreach ($permissions as $permission)
                                <label class="flex items-center gap-3 rounded-lg border border-neutral-200 px-3.5 py-2.5 text-sm transition duration-150 hover:bg-neutral-50">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                        @checked(in_array($permission->name, old('permissions', [])))
                                        class="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500/30">
                                    <span class="font-medium text-neutral-700">{{ $labels[$permission->name] ?? $permission->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-4 pt-2">
                        <a href="{{ route('admin.roles.index') }}" class="text-sm font-medium text-neutral-500 hover:text-neutral-800">Cancelar</a>
                        <x-primary-button>Crear rol</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
