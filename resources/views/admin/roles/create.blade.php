<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Crear rol"
                       :back="route('admin.roles.index')" backTitle="Volver a roles">
            <x-slot name="action">
                <x-form-actions form="role-form" submitLabel="Crear rol" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
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
                    @include('admin.roles._permisos', ['permissions' => $permissions, 'assigned' => []])
                    <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
