{{--
    Permisos del rol en TARJETAS por dominio (Servicio técnico, Terreno,
    Producción, …). Compartido por crear/editar rol. Params:
      $permissions : colección de Permission (todos)
      $assigned    : array de nombres ya asignados (default [])
      $lockRole    : nombre del rol para el candado (admin no pierde 'manage
                     roles'); null en crear.

    Por qué tarjetas y no pestañas: la fila de pestañas necesitaba scroll
    HORIZONTAL en cuanto entraba un dominio nuevo, y un scroll lateral esconde
    áreas enteras sin avisar — el dueño no tiene forma de saber que hay algo más
    a la derecha. La rejilla de tarjetas ENVUELVE, así que todas las áreas se ven
    de una vez con su contador, y el patrón es el mismo que el historial por año
    de Servicio Técnico: se ve el mapa completo y se hace clic para entrar.

    Las áreas salen de PermisosAgrupados (config('permissions.grupos')): un
    permiso nuevo cae solo en su grupo, y un dominio nuevo agrega su tarjeta sin
    tocar esta vista. El contador por área (marcados/total) y "Seleccionar todo"
    son reactivos con Alpine sobre `sel` (los nombres marcados).

    ⚠️ Las casillas de las áreas cerradas se ENVÍAN igual: `x-show` solo oculta en
    el cliente, el HTML se renderiza siempre. Si esto se cambiara por `x-if` o
    por un @if de Blade, guardar con un área cerrada BORRARÍA sus permisos.
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
    $totalPermisos = collect($mapa)->flatten()->count();
@endphp

<div class="mt-1.5"
     x-data="{
        {{-- Área abierta. Arranca en null: primero se ve el mapa de áreas, después se entra. --}}
        abierta: null,
        sel: @js($inicial),
        grupos: @js($mapa),
        bloqueados: @js($bloqueados),
        countOf(k) { return (this.grupos[k] || []).filter(n => this.sel.includes(n)).length },
        totalOf(k) { return (this.grupos[k] || []).length },
        pctOf(k) { const t = this.totalOf(k); return t === 0 ? 0 : Math.round(this.countOf(k) / t * 100) },
        todoOn(k) { const g = this.grupos[k] || []; return g.length > 0 && g.every(n => this.sel.includes(n)) },
        alternar(k) { this.abierta = this.abierta === k ? null : k },
        toggleTodo(k, on) {
            const g = this.grupos[k] || [];
            if (on) {
                g.forEach(n => { if (! this.sel.includes(n)) this.sel.push(n); });
            } else {
                this.sel = this.sel.filter(n => ! g.includes(n) || this.bloqueados.includes(n));
            }
        },
     }">

    {{-- Cabecera: cuántos permisos van marcados en total + salida del área abierta --}}
    <div class="mb-2 flex items-baseline justify-between gap-3">
        <h4 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
            Áreas
        </h4>
        <p class="text-xs text-neutral-500">
            <span class="font-semibold tabular-nums"
                  :class="sel.length > 0 ? 'text-brand-600' : 'text-neutral-400'"
                  x-text="sel.length"></span>
            de {{ $totalPermisos }} {{ $totalPermisos === 1 ? 'permiso' : 'permisos' }} marcados
        </p>
    </div>

    {{-- Rejilla de áreas. ENVUELVE en vez de desplazarse al costado. --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($gruposPermisos as $categoria => $perms)
            @php $panelId = 'permisos-'.\Illuminate\Support\Str::slug($categoria); @endphp
            <button type="button"
                    @click="alternar(@js($categoria))"
                    :aria-expanded="abierta === @js($categoria) ? 'true' : 'false'"
                    aria-controls="{{ $panelId }}"
                    :class="abierta === @js($categoria)
                        ? 'border-brand-500 bg-brand-50'
                        : 'border-neutral-200 bg-white hover:border-brand-300 hover:shadow'"
                    class="flex flex-col rounded-2xl border p-3 text-start shadow-sm transition duration-150 sm:p-4">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold"
                       :class="abierta === @js($categoria) ? 'text-brand-700' : 'text-neutral-900'">{{ $categoria }}</p>
                    {{-- El giro y el color van en el span, no en el componente: así el
                         ícono sigue siendo <x-icon.*> y hereda el color por currentColor. --}}
                    <span class="mt-0.5 inline-flex shrink-0 transition duration-150"
                          :class="abierta === @js($categoria) ? 'rotate-180 text-brand-600' : 'text-neutral-400'">
                        <x-icon.chevron-down class="h-4 w-4" />
                    </span>
                </div>
                {{-- mt-auto: el contador y la barra se pegan al fondo, así quedan
                     alineados con las tarjetas de la fila cuyo nombre ocupa una sola línea. --}}
                <p class="mt-auto pt-1 text-xs tabular-nums"
                   :class="countOf(@js($categoria)) > 0 ? 'text-brand-600' : 'text-neutral-400'"
                   x-text="countOf(@js($categoria)) + ' de ' + totalOf(@js($categoria))"></p>
                {{-- Barra de avance: dice de un vistazo si el área está vacía, a medias o completa --}}
                <div class="mt-2 h-1 overflow-hidden rounded-full bg-neutral-200">
                    <div class="h-1 rounded-full bg-brand-600 transition-all duration-150"
                         :style="'width: ' + pctOf(@js($categoria)) + '%'"></div>
                </div>
            </button>
        @endforeach
    </div>

    {{-- Sin área abierta: se dice qué hacer, en vez de dejar un hueco --}}
    <p x-show="abierta === null" class="mt-3 text-sm text-neutral-500">
        Elegí un área para ver y marcar sus permisos.
    </p>

    {{-- Un panel por área; solo el abierto se muestra. NUNCA cambiar x-show por x-if:
         las casillas ocultas tienen que seguir viajando en el POST. --}}
    @foreach ($gruposPermisos as $categoria => $perms)
        @php $panelId = 'permisos-'.\Illuminate\Support\Str::slug($categoria); @endphp
        <div id="{{ $panelId }}" role="region" aria-label="Permisos de {{ $categoria }}"
             x-show="abierta === @js($categoria)" x-cloak class="mt-3 space-y-2">

            <div class="flex items-center justify-between gap-3">
                <h5 class="text-sm font-semibold text-neutral-900">{{ $categoria }}</h5>
                <button type="button" @click="abierta = null"
                        class="text-xs font-medium text-brand-600 transition duration-150 hover:text-brand-700">
                    Cerrar
                </button>
            </div>

            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg bg-neutral-50 px-3 py-2.5">
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
