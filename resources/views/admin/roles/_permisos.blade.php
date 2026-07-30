{{--
    Permisos del rol en PESTAÑAS por dominio (Servicio técnico, Terreno,
    Producción, …). Compartido por crear/editar rol. Params:
      $permissions : colección de Permission (todos)
      $assigned    : array de nombres ya asignados (default [])
      $lockRole    : nombre del rol para el candado (admin no pierde 'manage
                     roles'); null en crear.

    Las pestañas salen de PermisosAgrupados (config('permissions.grupos')): un
    permiso nuevo cae solo en su grupo, y un dominio nuevo agrega su pestaña sin
    tocar esta vista. El contador por pestaña (marcados/total) y "Seleccionar
    todo" son reactivos con Alpine sobre `sel` (los nombres marcados). Las
    casillas de pestañas ocultas se ENVÍAN igual: x-show solo oculta en el
    cliente, el HTML se renderiza siempre.
--}}
@php
    $labels = config('permissions.labels');
    $assigned = $assigned ?? [];
    $lockRole = $lockRole ?? null;
    $gruposPermisos = \App\Support\PermisosAgrupados::agrupar($permissions);

    $inicial = array_values(old('permissions', $assigned));
    $bloqueados = $lockRole === 'admin' ? ['manage roles'] : [];
    // 'manage roles' es obligatorio para admin: presente aunque no venga en $assigned.
    if ($bloqueados && ! in_array('manage roles', $inicial, true)) {
        $inicial[] = 'manage roles';
    }

    // Mapa grupo => nombres de permiso, para los contadores y "seleccionar todo".
    $mapa = [];
    foreach ($gruposPermisos as $cat => $perms) {
        $mapa[$cat] = $perms->pluck('name')->values()->all();
    }
    $primera = array_key_first($gruposPermisos);
@endphp

<div class="mt-1.5"
     x-data="{
        tab: @js($primera),
        sel: @js($inicial),
        grupos: @js($mapa),
        bloqueados: @js($bloqueados),
        countOf(k) { return (this.grupos[k] || []).filter(n => this.sel.includes(n)).length },
        totalOf(k) { return (this.grupos[k] || []).length },
        todoOn(k) { const g = this.grupos[k] || []; return g.length > 0 && g.every(n => this.sel.includes(n)) },
        toggleTodo(k, on) {
            const g = this.grupos[k] || [];
            if (on) {
                g.forEach(n => { if (! this.sel.includes(n)) this.sel.push(n); });
            } else {
                this.sel = this.sel.filter(n => ! g.includes(n) || this.bloqueados.includes(n));
            }
        },
     }">

    {{-- Pestañas por dominio --}}
    <div class="flex gap-1 overflow-x-auto border-b border-neutral-200" role="tablist">
        @foreach ($gruposPermisos as $categoria => $perms)
            <button type="button" role="tab" @click="tab = @js($categoria)"
                    :aria-selected="tab === @js($categoria)"
                    :class="tab === @js($categoria) ? 'border-brand-600 text-brand-700' : 'border-transparent text-neutral-500 hover:text-neutral-700'"
                    class="flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition">
                {{ $categoria }}
                <span class="text-xs tabular-nums"
                      :class="countOf(@js($categoria)) > 0 ? 'text-brand-600' : 'text-neutral-400'"
                      x-text="countOf(@js($categoria)) + '/' + totalOf(@js($categoria))"></span>
            </button>
        @endforeach
    </div>

    {{-- Un panel por grupo; solo el activo se muestra --}}
    @foreach ($gruposPermisos as $categoria => $perms)
        <div x-show="tab === @js($categoria)" x-cloak class="mt-3 space-y-2">
            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg bg-neutral-50 px-3 py-2">
                <input type="checkbox" :checked="todoOn(@js($categoria))"
                       @change="toggleTodo(@js($categoria), $event.target.checked)"
                       class="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm font-medium text-neutral-600">Seleccionar todo en esta área</span>
            </label>

            @foreach ($perms as $permission)
                @php $locked = $lockRole === 'admin' && $permission->name === 'manage roles'; @endphp
                <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-neutral-200 px-3 py-2.5 transition hover:bg-neutral-50">
                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                           x-model="sel" @disabled($locked)
                           class="h-4 w-4 shrink-0 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm text-neutral-900">{{ $labels[$permission->name] ?? $permission->name }}</span>
                    @if ($locked)
                        <span class="ms-auto shrink-0 text-xs text-neutral-400">obligatorio para admin</span>
                        <input type="hidden" name="permissions[]" value="manage roles">
                    @endif
                </label>
            @endforeach
        </div>
    @endforeach
</div>
