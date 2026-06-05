<x-app-layout>
    @php
        $labels = config('permissions.labels');
    @endphp

    <x-slot name="header">
        <x-page-header title="Editar rol" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
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
                        <div class="mt-1.5 space-y-2">
                            @foreach ($permissions as $permission)
                                @php $locked = $role->name === 'admin' && $permission->name === 'manage roles'; @endphp
                                <x-checkbox-item name="permissions[]" :value="$permission->name" :checked="in_array($permission->name, old('permissions', $assigned))" :disabled="$locked">
                                    {{ $labels[$permission->name] ?? $permission->name }}
                                    @if ($locked)
                                        <x-slot name="note">obligatorio para admin</x-slot>
                                    @endif
                                </x-checkbox-item>
                                @if ($locked)
                                    <input type="hidden" name="permissions[]" value="manage roles">
                                @endif
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
                    </div>

                    <x-form-footer :cancel="route('admin.roles.index')">
                        <x-primary-button>Guardar cambios</x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
