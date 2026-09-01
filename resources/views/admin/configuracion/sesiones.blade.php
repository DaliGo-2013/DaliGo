<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Sesiones por usuario"
                       subtitle="Cuántas sesiones abiertas a la vez permite cada cuenta"
                       :back="route('admin.configuracion.index')" backTitle="Volver a configuración">
            <x-slot name="action">
                <x-form-actions form="form-sesiones" submitLabel="Guardar límites" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 py-8 sm:py-12">
        <x-status-alert :status="session('status')" />

        <form id="form-sesiones" method="POST" action="{{ route('admin.configuracion.sesiones.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- ── Default global ─────────────────────────────────────── --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <x-input-label for="limite_default" value="Límite por defecto">
                    <x-slot:ayuda>Rige para todos los que no tengan un límite propio ni uno de su rol. Al topar, el ingreso nuevo siempre entra y se cierra la sesión más antigua de esa cuenta. El recorte ocurre cuando la persona vuelve a iniciar sesión.</x-slot:ayuda>
                </x-input-label>
                {{-- El ancho va en un contenedor: x-text-input mergea `w-full`
                     y un `w-32` en class quedaría inerte (gotcha [2026-07-24]). --}}
                <div class="mt-1.5 w-32">
                    <x-text-input id="limite_default" name="limite_default" type="number"
                        min="{{ \App\Support\LimiteSesiones::MIN }}" max="{{ \App\Support\LimiteSesiones::MAX }}" required
                        :value="old('limite_default', $limiteDefault)" />
                </div>
                <x-input-hint>0 = sin límite.</x-input-hint>
                <x-input-error :messages="$errors->get('limite_default')" class="mt-2" />
            </div>

            {{-- ── Por rol ────────────────────────────────────────────── --}}
            <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-6 py-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por rol</h3>
                </div>
                <ul class="divide-y divide-neutral-100">
                    @foreach ($roles as $rol => $etiqueta)
                        <li class="flex items-center gap-4 px-6 py-3">
                            <span class="min-w-0 flex-1 text-sm text-neutral-900">{{ $etiqueta }}</span>
                            <div class="w-32 shrink-0">
                                <x-text-input name="roles[{{ $rol }}]" type="number"
                                    min="{{ \App\Support\LimiteSesiones::MIN }}" max="{{ \App\Support\LimiteSesiones::MAX }}"
                                    placeholder="hereda ({{ $limiteDefault }})"
                                    :value="old('roles.'.$rol, $overridesRoles[$rol] ?? '')" />
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="border-t border-neutral-100 px-6 py-3 text-xs text-neutral-500">Vacío = hereda el default · 0 = sin límite para ese rol.</p>
                <x-input-error :messages="$errors->get('roles')" class="px-6 pb-3" />
            </div>

            {{-- ── Por usuario puntual (gana sobre el rol y el default) ── --}}
            <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-6 py-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por usuario puntual</h3>
                </div>

                @if ($usuariosConOverride->isNotEmpty())
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($usuariosConOverride as $u)
                            <li class="flex items-center gap-4 px-6 py-3">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-neutral-900">{{ $u->name }}</span>
                                    <span class="block truncate text-xs text-neutral-500">{{ $u->email }}</span>
                                </span>
                                <div class="w-32 shrink-0">
                                    <x-text-input name="usuarios[{{ $u->id }}]" type="number"
                                        min="{{ \App\Support\LimiteSesiones::MIN }}" max="{{ \App\Support\LimiteSesiones::MAX }}"
                                        :value="old('usuarios.'.$u->id, $overridesUsuarios[$u->id] ?? '')" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="border-t border-neutral-100 px-6 py-3 text-xs text-neutral-500">Vaciar el número quita el límite propio (vuelve a heredar).</p>
                @else
                    <p class="px-6 py-4 text-sm text-neutral-500">Nadie tiene un límite propio: todos heredan su rol o el default.</p>
                @endif
                <x-input-error :messages="$errors->get('usuarios')" class="px-6 pb-3" />

                {{-- Agregar uno (una alta por guardado, sin JS de filas) --}}
                <div class="border-t border-neutral-100 px-6 py-4">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Agregar un usuario</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-0 flex-1">
                            <x-select id="nuevo_usuario_id" name="nuevo_usuario_id" class="w-full">
                                <option value="">— Elegir usuario —</option>
                                @foreach ($usuarios as $u)
                                    <option value="{{ $u->id }}" @selected((int) old('nuevo_usuario_id') === $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="w-32 shrink-0">
                            <x-text-input name="nuevo_limite" type="number"
                                min="{{ \App\Support\LimiteSesiones::MIN }}" max="{{ \App\Support\LimiteSesiones::MAX }}"
                                placeholder="límite"
                                :value="old('nuevo_limite')" />
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('nuevo_usuario_id')" class="mt-2" />
                    <x-input-error :messages="$errors->get('nuevo_limite')" class="mt-2" />
                </div>
            </div>

            <x-form-footer>
                <x-primary-button form="form-sesiones">Guardar límites</x-primary-button>
            </x-form-footer>
        </form>
    </div>
</x-app-layout>
