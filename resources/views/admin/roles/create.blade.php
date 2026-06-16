<x-app-layout>
    @php
        $labels = config('permissions.labels');
    @endphp

    <x-slot name="header">
        <x-page-header title="Crear rol">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.roles.index')" form="role-form" submitLabel="Crear rol" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="role-form" method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
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
                                <x-checkbox-item name="permissions[]" :value="$permission->name" :checked="in_array($permission->name, old('permissions', []))">
                                    {{ $labels[$permission->name] ?? $permission->name }}
                                </x-checkbox-item>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
