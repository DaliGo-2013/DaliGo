{{--
    Pantalla de "listo" de la solicitud de visita industrial. Link firmado.
--}}
<x-guest-layout>
    <div class="text-center">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-brand-600">
            <x-icon.check class="h-8 w-8" />
        </span>

        <h1 class="mt-5 text-xl font-bold tracking-tight text-neutral-900">¡Listo, {{ \Illuminate\Support\Str::of($trabajo->cliente_nombre)->before(' ') }}!</h1>
        <p class="mt-1 text-sm text-neutral-500">Tu solicitud de visita quedó registrada.</p>

        <div class="mt-5 rounded-xl border border-neutral-200 bg-white px-5 py-4 text-left text-sm">
            <div class="flex justify-between border-b border-neutral-100 py-1.5">
                <span class="text-neutral-500">Tipo</span>
                <span class="font-medium text-neutral-900">{{ $trabajo->tipo_label }}</span>
            </div>
            @if ($trabajo->servicio)
                <div class="flex justify-between border-b border-neutral-100 py-1.5">
                    <span class="text-neutral-500">Servicio</span>
                    <span class="font-medium text-neutral-900">{{ $trabajo->servicio->nombre }}</span>
                </div>
            @endif
            <div class="flex justify-between border-b border-neutral-100 py-1.5">
                <span class="text-neutral-500">Dirección</span>
                <span class="font-medium text-neutral-900">{{ $trabajo->direccion }}@if ($trabajo->ciudad), {{ $trabajo->ciudad }}@endif</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-neutral-500">Fecha preferida</span>
                <span class="font-medium text-neutral-900">{{ $trabajo->fecha_preferida?->format('d-m-Y') ?? 'Por coordinar' }}</span>
            </div>
        </div>

        <p class="mt-5 text-sm text-neutral-500">
            Te llamaremos al <span class="font-medium text-neutral-700">{{ $trabajo->cliente_telefono }}</span> para coordinar el día y la hora de la visita.
        </p>

        @if (! empty($urlInicio))
            <a href="{{ $urlInicio }}"
               class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">
                Volver al inicio
            </a>
        @endif
    </div>
</x-guest-layout>
