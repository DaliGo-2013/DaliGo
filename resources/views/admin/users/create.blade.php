<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-neutral-900">Crear cuenta</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="mb-6 text-sm text-neutral-500">
                    Solo se permiten correos del dominio <span class="font-medium text-neutral-700">@impdali.cl</span>.
                    Comparte la contraseña inicial con la persona; podrá cambiarla luego.
                </p>

                <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
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
                        <select id="role" name="role" required
                            class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" value="Contraseña inicial" />
                        <x-text-input id="password" class="mt-1.5" type="password" name="password" required autocomplete="new-password" placeholder="••••••••" />
                        <x-input-hint>Mínimo 8 caracteres.</x-input-hint>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" value="Confirmar contraseña" />
                        <x-text-input id="password_confirmation" class="mt-1.5" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                    </div>

                    <div class="flex items-center justify-end gap-4 pt-2">
                        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-neutral-500 hover:text-neutral-800">Cancelar</a>
                        <x-primary-button>Crear cuenta</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
