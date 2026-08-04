{{-- Countdown de hitos re-baselinados (PlanProyecto::HITOS). Rojo SOLO para
     lo negativo (atrasado), doctrina de la paleta. --}}
<div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
    <div class="border-b border-neutral-100 px-6 py-3">
        <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Hitos</h2>
    </div>
    <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 sm:p-6 lg:grid-cols-4">
        @foreach ($hitos as $hito)
            <div class="rounded-lg border border-neutral-200 p-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-neutral-500">{{ $hito['key'] }}</span>
                    @if ($hito['estado'] === 'cumplido')
                        <span class="inline-flex items-center rounded-full bg-neutral-800 px-2.5 py-0.5 text-xs font-medium text-white">Cumplido</span>
                    @elseif ($hito['estado'] === 'atrasado')
                        <x-badge variant="danger">Atrasado {{ abs($hito['dias']) }} d</x-badge>
                    @else
                        <x-badge variant="brand">Faltan {{ $hito['dias'] }} d</x-badge>
                    @endif
                </div>
                <p class="mt-2 text-sm font-medium text-neutral-900">{{ $hito['label'] }}</p>
                <p class="mt-0.5 text-xs tabular-nums text-neutral-500">{{ $hito['carbon']->format('d-m-Y') }}</p>
            </div>
        @endforeach
    </div>
</div>
