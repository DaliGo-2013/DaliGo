{{-- Semáforo de decisiones (parseado de docs/DECISIONES.md §2): las abiertas
     y aplazadas al frente — son las que bloquean trabajo. Abierta = brand
     (requiere acción), aplazada = neutral (en reposo), doctrina de la paleta. --}}
@php
    $abiertas = array_values(array_filter($decisiones, fn ($d) => in_array($d['estado'], ['abierta', 'aplazada'], true)));
    $resueltas = count($decisiones) - count($abiertas);
@endphp

<div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-neutral-100 px-6 py-3">
        <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semáforo de decisiones</h2>
        <span class="text-xs text-neutral-400">{{ $resueltas }} resueltas · detalle en
            <a href="https://github.com/DaliGo-2013/DaliGo/blob/main/docs/DECISIONES.md" target="_blank" rel="noopener"
               class="font-medium text-brand-700 transition duration-150 hover:text-brand-600">DECISIONES.md</a>
        </span>
    </div>
    <ul class="divide-y divide-neutral-100">
        @forelse ($abiertas as $decision)
            <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-6 py-3">
                <span class="w-12 shrink-0 text-xs font-semibold tabular-nums text-neutral-500">{{ $decision['id'] }}</span>
                <p class="min-w-0 flex-1 text-sm text-neutral-800" title="{{ $decision['detalle'] }}">{{ $decision['titulo'] }}</p>
                <x-badge :variant="$decision['estado'] === 'abierta' ? 'brand' : 'neutral'">{{ ucfirst($decision['estado']) }}</x-badge>
                <span class="text-xs text-neutral-500">{{ $decision['decisor'] }}</span>
                @if ($decision['limite'] !== '')
                    <span class="text-xs text-neutral-400">límite: {{ $decision['limite'] }}</span>
                @endif
            </li>
        @empty
            <li class="px-6 py-8 text-center text-sm text-neutral-500">Todas las decisiones están resueltas.</li>
        @endforelse
    </ul>
</div>
