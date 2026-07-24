<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar rol">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.roles.index')" form="role-form" submitLabel="Guardar cambios" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="role-form" method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Nombre del rol" />
                        @if ($isBase)
                            <x-text-input id="name" class="mt-1.5 bg-neutral-50 text-neutral-500" type="text" :value="$role->name" disabled />
                            <x-input-hint>Es un rol del sistema: su nombre no puede cambiarse.</x-input-hint>
                        @else
                            <x-text-input id="name" class="mt-1.5" type="text" name="name" :value="old('name', $role->name)" required autofocus placeholder="ej. supervisor" />
                            <x-input-hint>Letras, numeros, espacios, guiones y guiones bajos.</x-input-hint>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        @endif
                    </div>

                    <div>
                        <x-input-label value="Permisos" />
                        @include('admin.roles._permisos', ['permissions' => $permissions, 'assigned' => $assigned, 'lockRole' => $role->name])
                        <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
