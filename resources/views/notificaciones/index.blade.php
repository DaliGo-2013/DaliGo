<x-app-layout>
    {{-- Bandeja personal in-app. Mobile-first (el operario la abre en el celular):
         pocos elementos, tocar para marcar leída, español. --}}
    <div class="py-4 sm:py-8">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-semibold leading-tight text-neutral-900">Mis notificaciones</h2>
                @if ($notificaciones->where('estado', \App\Models\Notificacion::ENVIADA)->isNotEmpty())
                    <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-brand-600 transition hover:text-brand-700">Marcar todas</button>
                    </form>
                @endif
            </div>

            <x-status-alert :status="session('status')" class="mb-4" />

            <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <ul class="divide-y divide-neutral-100">
                    @forelse ($notificaciones as $n)
                        @php
                            $noLeida = $n->estado === \App\Models\Notificacion::ENVIADA;
                            // Fila accionable (hallazgo #5 del QA 15-07): el contenido ES el
                            // link al destino del evento. Un <a> sin href no es clickeable.
                            $destino = $n->urlDestino();
                        @endphp
                        <li class="flex items-start gap-3 px-4 py-3 sm:px-6 {{ $noLeida ? 'bg-brand-50/40' : '' }}">
                            <a @if ($destino) href="{{ $destino }}" @endif
                                class="flex min-w-0 flex-1 items-start gap-3 {{ $destino ? '-mx-2 rounded-lg px-2 py-1 -my-1 transition duration-150 hover:bg-neutral-50 active:scale-[0.98]' : '' }}">
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2 truncate text-sm font-medium text-neutral-900">
                                        @if ($noLeida)
                                            <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-brand-600" aria-hidden="true"></span>
                                        @endif
                                        {{ $n->titulo }}
                                    </span>
                                    <span class="mt-0.5 block whitespace-pre-line text-sm text-neutral-500">{{ $n->cuerpo }}</span>
                                    <span class="mt-1 block text-xs text-neutral-400">{{ $n->created_at?->diffForHumans() }}</span>
                                </span>
                                @if ($destino)
                                    <x-icon.chevron-down class="mt-1 h-4 w-4 shrink-0 -rotate-90 text-neutral-400" />
                                @endif
                            </a>
                            @if ($noLeida)
                                <form method="POST" action="{{ route('notificaciones.leer', $n) }}" class="shrink-0">
                                    @csrf
                                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-brand-600 transition hover:bg-neutral-100" title="Marcar como leída">
                                        Leída
                                    </button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-6 py-10 text-center text-sm text-neutral-500">No tienes notificaciones.</li>
                    @endforelse
                </ul>
            </div>

            @if ($notificaciones->hasPages())
                <div class="mt-4">{{ $notificaciones->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
