<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Crear cuenta">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.users.index')" form="user-form" submitLabel="Crear cuenta" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="mb-6 text-sm text-neutral-500">
                    Solo se permiten correos del dominio <span class="font-medium text-neutral-700">@impdali.cl</span>.
                    Comparte la contraseña inicial con la persona; podrá cambiarla luego.
                </p>

                <form id="user-form" method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" class="mt-1.5" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Nombre y apellido" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" value="Correo electrónico" />
                        <x-text-input id="email" class="mt-1.5" type="email" name="email" :value="old('email')" required autocomplete="off" placeholder="persona@impdali.cl" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role" value="Rol" />
                        <x-select id="role" name="role" class="mt-1.5" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ \Illuminate\Support\Str::headline($role) }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="sucursal_id" value="Sucursal" />
                        <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5">
                            <option value="">— Sin sucursal —</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}" @selected((int) old('sucursal_id') === $sucursal->id)>{{ $sucursal->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" value="Contraseña inicial" />
                        <x-password-input id="password" class="mt-1.5" name="password" required autocomplete="new-password" placeholder="••••••••" />
                        <x-input-hint>Mínimo 8 caracteres.</x-input-hint>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" value="Confirmar contraseña" />
                        <x-password-input id="password_confirmation" class="mt-1.5" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
