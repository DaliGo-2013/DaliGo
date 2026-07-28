<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Editar cuenta"
                       :back="route('admin.users.index')" backTitle="Volver a cuentas">
            <x-slot name="action">
                <x-form-actions form="user-form" submitLabel="Guardar cambios" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form id="user-form" method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="name" value="Nombre" />
                    <x-text-input id="name" name="name" type="text" class="mt-1.5 w-full" required
                        :value="old('name', $user->name)" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" value="Correo" />
                    <x-text-input id="email" name="email" type="email" class="mt-1.5 w-full" required
                        :value="old('email', $user->email)" />
                    <x-input-hint>Debe ser un correo corporativo @impdali.cl.</x-input-hint>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="role" value="Rol" />
                    <x-select id="role" name="role" class="mt-1.5" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(old('role', $user->roles->first()?->name) === $role)>{{ \Illuminate\Support\Str::headline($role) }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('role')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="sucursal_id" value="Sucursal" />
                    <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5">
                        <option value="">— Sin sucursal —</option>
                        @foreach ($sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}" @selected((int) old('sucursal_id', $user->sucursal_id) === $sucursal->id)>{{ $sucursal->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="jefe_id" value="Jefe directo (opcional)" />
                    <x-select id="jefe_id" name="jefe_id" class="mt-1.5">
                        <option value="">— Sin jefe —</option>
                        @foreach ($jefes as $j)
                            <option value="{{ $j->id }}" @selected((int) old('jefe_id', $user->jefe_id) === $j->id)>{{ $j->name }}</option>
                        @endforeach
                    </x-select>
                    <x-input-hint>El jefe que elijas verá en Servicio Técnico la cartera de esta persona (además de la suya).</x-input-hint>
                    <x-input-error :messages="$errors->get('jefe_id')" class="mt-2" />
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
