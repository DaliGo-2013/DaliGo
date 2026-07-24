{{-- Campanita in-app (M15). v1 sin polling: se refresca al navegar. Recibe
     $dgNoLeidas (colección, 5 últimas) y $dgConteo (total no-leídas) ya
     calculados por quien la incluye — así no se repite la query. Opcionales:
     $dgArriba (el panel abre hacia arriba — pie de la sidebar) y $dgAlign. --}}
<x-dropdown :align="$dgAlign ?? 'right'" width="w-80" :direction="($dgArriba ?? false) ? 'up' : 'down'">
    <x-slot name="trigger">
        <button type="button" title="Notificaciones"
                class="relative inline-flex items-center rounded-md border border-transparent p-2 text-neutral-600 transition duration-150 hover:text-neutral-900 focus:outline-none">
            <x-icon.bell class="h-6 w-6" />
            @if ($dgConteo > 0)
                <span class="absolute right-0.5 top-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-brand-600 px-1 text-[10px] font-semibold leading-none text-white">{{ $dgConteo > 9 ? '9+' : $dgConteo }}</span>
            @endif
            <span class="sr-only">Notificaciones ({{ $dgConteo }} sin leer)</span>
        </button>
    </x-slot>

    <x-slot name="content">
        <div class="flex items-center justify-between px-4 py-2">
            <span class="text-xs font-medium uppercase tracking-wide text-neutral-500">Notificaciones</span>
            @if ($dgConteo > 0)
                <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-brand-600 transition hover:text-brand-700">Marcar todas</button>
                </form>
            @endif
        </div>

        @forelse ($dgNoLeidas as $dgN)
            <form method="POST" action="{{ route('notificaciones.leer', $dgN) }}">
                @csrf
                <button type="submit" class="block w-full border-t border-neutral-100 px-4 py-2 text-left transition hover:bg-neutral-50">
                    <span class="block truncate text-sm font-medium text-neutral-800">{{ $dgN->titulo }}</span>
                    <span class="block text-xs text-neutral-400">{{ $dgN->created_at?->diffForHumans() }}</span>
                </button>
            </form>
        @empty
            <p class="border-t border-neutral-100 px-4 py-3 text-sm text-neutral-500">Sin notificaciones nuevas.</p>
        @endforelse

        <a href="{{ route('notificaciones.index') }}"
           class="block border-t border-neutral-100 px-4 py-2 text-center text-xs font-medium text-brand-600 transition hover:text-brand-700">
            Ver todas
        </a>

        {{-- Hub de funciones (pedido del jefe 24-07): todo lo relacionado a
             notificaciones/aprobaciones agrupado en la campanita. Cada link se
             gatea por permiso; $dgBadges llega memoizado desde la sidebar. --}}
        <div class="border-t border-neutral-100 px-4 pb-1 pt-2">
            <span class="text-xs font-medium uppercase tracking-wide text-neutral-500">Funciones</span>
        </div>
        @can('aprobar solicitudes')
            <a href="{{ route('aprobaciones.index') }}" class="flex items-center justify-between gap-2 px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-900">
                Bandeja de aprobaciones
                @if (($dgBadges['aprobaciones_bandeja'] ?? 0) > 0)
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white">{{ $dgBadges['aprobaciones_bandeja'] }}</span>
                @endif
            </a>
        @endcan
        @if (($dgBadges['mis_solicitudes'] ?? 0) > 0)
            <a href="{{ route('aprobaciones.mias') }}" class="block px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-900">Mis solicitudes</a>
        @endif
        @can('view aprobaciones')
            <a href="{{ route('admin.aprobaciones.index') }}" class="block px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-900">Historial de aprobaciones</a>
        @endcan
        @can('view notificaciones')
            <a href="{{ route('admin.notificaciones.index') }}" class="block px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-900">Panel de notificaciones</a>
        @endcan
    </x-slot>
</x-dropdown>
