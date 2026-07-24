{{--
    Checkboxes de permisos AGRUPADOS por dominio (Servicio técnico, Producción,
    etc.). Compartido por crear/editar rol. Params:
      $permissions : colección de Permission (todos)
      $assigned    : array de nombres ya asignados (default [])
      $lockRole    : nombre del rol para el candado (admin no pierde 'manage
                     roles'); null en crear.
--}}
@php
    $labels = config('permissions.labels');
    $assigned = $assigned ?? [];
    $lockRole = $lockRole ?? null;
    $gruposPermisos = \App\Support\PermisosAgrupados::agrupar($permissions);
@endphp

<div class="mt-1.5 space-y-5">
    @foreach ($gruposPermisos as $categoria => $perms)
        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $categoria }}</h4>
            <div class="space-y-2">
                @foreach ($perms as $permission)
                    @php $locked = $lockRole === 'admin' && $permission->name === 'manage roles'; @endphp
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
        </div>
    @endforeach
</div>
