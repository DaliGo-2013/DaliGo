<x-app-layout ancho="formulario">
    <div class="py-8">
        {{-- Sin Volver: desde MSG-4 «Mensajes» es ítem del menú (el menú ES el
             camino) — la huérfana temporal de MSG-2 dejó de serlo. --}}
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold leading-tight text-neutral-900">Mensajes</h2>
            <x-button-link :href="route('mensajes.create')">
                <x-icon.plus class="h-4 w-4" />
                Nuevo mensaje
            </x-button-link>
        </div>

        <x-status-alert :status="session('status')" class="mb-4" />

        <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <ul class="divide-y divide-neutral-100">
                @forelse ($conversaciones as $conversacion)
                    @php
                        $otro = $conversacion->otroLado($usuario);
                        $noLeidos = $conversacion->noLeidosDe($usuario);
                        $ultimo = $conversacion->ultimoMensaje;
                    @endphp
                    <li class="{{ $noLeidos > 0 ? 'bg-brand-50/40' : '' }}">
                        <a href="{{ route('mensajes.show', $conversacion) }}"
                           class="flex items-center gap-3 px-4 py-3 transition duration-150 hover:bg-neutral-50 active:scale-[0.98] sm:px-6">
                            <x-avatar>{{ mb_strtoupper(mb_substr($otro?->name ?? '—', 0, 1)) }}</x-avatar>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2 truncate text-sm font-medium text-neutral-900">
                                    @if ($noLeidos > 0)
                                        <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-brand-600" aria-hidden="true"></span>
                                    @endif
                                    {{ $otro?->name ?? '—' }}
                                </span>
                                @if ($ultimo)
                                    <span class="mt-0.5 block truncate text-sm text-neutral-500">{{ $ultimo->texto }}</span>
                                    <span class="mt-0.5 block text-xs text-neutral-400">{{ $ultimo->created_at?->diffForHumans() }}</span>
                                @endif
                            </span>
                            @if ($noLeidos > 0)
                                {{-- Candado por marcador ACCESIBLE (doctrina CampanitaTest), no por clase. --}}
                                {{-- Marcador «mensajes sin leer» a proposito: distinto del
                                     «(N sin leer)» de la campanita del shell, para que el
                                     candado no pase (ni falle) por la razon equivocada. --}}
                                <x-badge variant="brand" class="shrink-0">
                                    {{ $noLeidos }}<span class="sr-only"> mensajes sin leer</span>
                                </x-badge>
                            @endif
                            <x-icon.chevron-down class="h-4 w-4 shrink-0 -rotate-90 text-neutral-400" />
                        </a>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-neutral-500">
                        No tienes conversaciones. Usa <span class="font-medium text-neutral-700">Nuevo mensaje</span> para escribirle a alguien.
                    </li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Refresco automático (MSG-3; a 4 s desde MSG-5 — QA del dueño): firma
         global del chat — mensaje nuevo, leído en otra pestaña o hilo nuevo
         recargan la lista sola. Aquí la recarga completa está bien: no hay
         composer que perder (el hilo, que sí lo tiene, appendea sin reload). --}}
    <x-poll-recarga :url="route('mensajes.conteo')" :firma="$firmaChat" :intervalo="4000" />
</x-app-layout>
