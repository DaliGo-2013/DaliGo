<x-guest-layout>
    <div class="text-center">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full
                     {{ $trabajo->cliente_confirmacion === 'no_puede' ? 'bg-neutral-100 text-neutral-500' : 'bg-brand-100 text-brand-600' }}">
            <x-icon.check class="h-8 w-8" />
        </span>

        <h1 class="mt-5 text-xl font-bold tracking-tight text-neutral-900">¡Gracias, {{ \Illuminate\Support\Str::of($trabajo->cliente_nombre)->before(' ') }}!</h1>

        @if ($trabajo->cliente_confirmacion === 'confirmada')
            <p class="mt-1 text-sm text-neutral-500">Confirmaste tu visita del {{ $trabajo->fecha?->translatedFormat('l d \d\e F') }}.</p>
            <p class="mt-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">Nuestro equipo ya fue avisado. ¡Te esperamos ese día!</p>
        @elseif ($trabajo->cliente_confirmacion === 'no_puede')
            <p class="mt-1 text-sm text-neutral-500">Registramos que no puedes ese día.</p>
            <p class="mt-5 rounded-lg bg-neutral-50 px-4 py-3 text-sm text-neutral-600">Nuestro equipo ya fue avisado y te contactará para coordinar una nueva fecha.</p>
        @else
            <p class="mt-1 text-sm text-neutral-500">Tu respuesta ya estaba registrada.</p>
        @endif

        @if (filled($trabajo->cliente_confirmacion_nota))
            <div class="mt-4 rounded-xl border border-neutral-200 bg-white px-5 py-3 text-left text-sm">
                <div class="text-xs uppercase tracking-wide text-neutral-400">Tu comentario</div>
                <div class="mt-0.5 text-neutral-700">{{ $trabajo->cliente_confirmacion_nota }}</div>
            </div>
        @endif
    </div>
</x-guest-layout>
