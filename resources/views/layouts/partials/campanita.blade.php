{{-- Campanita in-app (M15). v1 sin polling: se refresca al navegar. Recibe
     $dgNoLeidas (colección, 5 últimas) y $dgConteo (total no-leídas) ya
     calculados por quien la incluye — así no se repite la query. Opcionales:
     $dgArriba (el panel abre hacia arriba — pie de la sidebar) y $dgAlign. --}}
{{-- anchoMovil: en el celular el panel toma casi todo el ancho. Con 320px el
     título y el cuerpo de cada notificación se leían en una columna angosta, y
     esta campanita es un hub (notificaciones + funciones), no un menú corto.
     El alto y la posición los sigue midiendo `x-dg-anclar` (P-NAV-10). --}}
<x-dropdown :align="$dgAlign ?? 'right'" width="w-80" anchoMovil :direction="($dgArriba ?? false) ? 'up' : 'down'">
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
                {{-- ir=1 (lote NOTIF-1): además de marcar leída, la fila NAVEGA
                     al destino del evento; sin destino cae al back() de siempre. --}}
                <input type="hidden" name="ir" value="1">
                {{-- min-h-11 (44px) en móvil: cada notificación es un blanco táctil
                     que además NAVEGA, y con py-2 quedaba en ~36px. --}}
                <button type="submit" class="block min-h-11 w-full border-t border-neutral-100 px-4 py-2.5 text-left transition hover:bg-neutral-50 sm:min-h-0 sm:py-2">
                    <span class="block truncate text-sm font-medium text-neutral-800">{{ $dgN->titulo }}</span>
                    @if (filled($dgN->cuerpo))
                        {{-- Sin 'block': line-clamp-2 define su propio display (-webkit-box) y
                             un display:block posterior en el bundle lo anularía (gate R-31). --}}
                        <span class="mt-0.5 line-clamp-2 whitespace-pre-line text-xs text-neutral-500">{{ $dgN->cuerpo }}</span>
                    @endif
                    <span class="mt-0.5 block text-xs text-neutral-400">{{ $dgN->created_at?->diffForHumans() }}</span>
                </button>
            </form>
        @empty
            <p class="border-t border-neutral-100 px-4 py-3 text-sm text-neutral-500">Sin notificaciones nuevas.</p>
        @endforelse

        <a href="{{ route('notificaciones.index') }}"
           class="flex min-h-11 items-center justify-center border-t border-neutral-100 px-4 py-2 text-center text-xs font-medium text-brand-600 transition hover:text-brand-700 sm:min-h-0">
            Ver todas
        </a>

        {{-- Hub de funciones (pedido del jefe 24-07): todo lo relacionado a
             notificaciones/aprobaciones agrupado en la campanita. Cada link se
             gatea por permiso; $dgBadges llega memoizado desde la sidebar. --}}
        <div class="border-t border-neutral-100 px-4 pb-1 pt-2">
            <span class="text-xs font-medium uppercase tracking-wide text-neutral-500">Funciones</span>
        </div>
        @can('aprobar solicitudes')
            <a href="{{ route('aprobaciones.index') }}" class="flex min-h-11 items-center justify-between gap-2 px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-900 sm:min-h-0">
                Bandeja de aprobaciones
                @if (($dgBadges['aprobaciones_bandeja'] ?? 0) > 0)
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white">{{ $dgBadges['aprobaciones_bandeja'] }}</span>
                @endif
            </a>
        @endcan
        @if (($dgBadges['mis_solicitudes'] ?? 0) > 0)
            <a href="{{ route('aprobaciones.mias') }}" class="flex min-h-11 items-center px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-900 sm:min-h-0">Mis solicitudes</a>
        @endif
        @can('view aprobaciones')
            <a href="{{ route('admin.aprobaciones.index') }}" class="flex min-h-11 items-center px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-900 sm:min-h-0">Historial de aprobaciones</a>
        @endcan
        @can('view notificaciones')
            <a href="{{ route('admin.notificaciones.index') }}" class="flex min-h-11 items-center px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-900 sm:min-h-0">Panel de notificaciones</a>
        @endcan
    </x-slot>
</x-dropdown>
