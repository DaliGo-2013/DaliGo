{{-- Campanita in-app (M15). v1 sin polling: se refresca al navegar. Recibe
     $dgNoLeidas (colección, 5 últimas) y $dgConteo (total no-leídas) ya
     calculados en navigation.blade.php — así el nav no repite la query. --}}
<x-dropdown align="right" width="w-80">
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
    </x-slot>
</x-dropdown>
