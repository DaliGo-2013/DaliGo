{{--
    Pantalla de "listo" que el cliente le muestra al encargado tras enviar el
    formulario por QR. Link firmado: no se pueden ver folios de otras ordenes.
--}}
<x-guest-layout>
    <div class="text-center">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-brand-600">
            <x-icon.check class="h-8 w-8" />
        </span>

        <h1 class="mt-5 text-xl font-bold tracking-tight text-neutral-900">¡Listo, {{ \Illuminate\Support\Str::of($orden->cliente_nombre)->before(' ') }}!</h1>
        <p class="mt-1 text-sm text-neutral-500">Tu ingreso quedó registrado.</p>

        {{-- Folio destacado --}}
        <div class="mt-5 rounded-xl border border-brand-200 bg-brand-50 px-5 py-4">
            <div class="text-xs font-medium uppercase tracking-wide text-brand-700">Tu folio</div>
            <div class="text-3xl font-bold tracking-wide text-brand-600">{{ $orden->folio }}</div>
        </div>

        <div class="mt-5 rounded-xl border border-neutral-200 bg-white px-5 py-4 text-left text-sm">
            <div class="flex justify-between border-b border-neutral-100 py-1.5">
                <span class="text-neutral-500">Equipo</span>
                <span class="font-medium text-neutral-900">{{ ucfirst($orden->tipo_equipo) }}</span>
            </div>
            <div class="flex justify-between border-b border-neutral-100 py-1.5">
                <span class="text-neutral-500">Sucursal</span>
                <span class="font-medium text-neutral-900">{{ $orden->sucursal?->nombre }}</span>
            </div>
            {{-- EL PLAZO, NO UNA FECHA (dueño, 14-08-2026): «no quiero que la app lo calcule,
                 solo diga 15 días hábiles o 10 días, después el cliente lo calcula por sí
                 solo». Una fecha escrita es un compromiso que el taller no siempre puede
                 cumplir (técnico enfermo o de vacaciones) y termina en reclamo. Cada sucursal
                 tiene su plazo: Mirador repara (10); Coquimbo y Abate Molina mandan el equipo
                 a Mirador (15). --}}
            @if ($orden->sucursal)
                <div class="flex justify-between py-1.5">
                    <span class="text-neutral-500">Plazo de reparación</span>
                    <span class="font-medium text-neutral-900">hasta {{ $orden->sucursal->dias_reparacion }} días hábiles</span>
                </div>
            @endif
        </div>

        <p class="mt-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">
            Muéstrale esta pantalla al encargado del mostrador para que reciba tu equipo.
            Te enviaremos el detalle a <span class="font-medium">{{ $orden->cliente_email }}</span>.
        </p>

        @if (! empty($urlInicio))
            <a href="{{ $urlInicio }}"
               class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">
                Volver al inicio
            </a>
        @endif
    </div>
</x-guest-layout>
