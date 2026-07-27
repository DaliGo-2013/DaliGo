{{--
    Pantalla de "listo" del ingreso por cantidad: lista los folios (uno por
    máquina) para que el cliente se la muestre al encargado. Link firmado.
--}}
<x-guest-layout>
    <div class="text-center">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-brand-600">
            <x-icon.check class="h-8 w-8" />
        </span>

        <h1 class="mt-5 text-xl font-bold tracking-tight text-neutral-900">¡Listo, {{ \Illuminate\Support\Str::of($lote->cliente_nombre)->before(' ') }}!</h1>
        <p class="mt-1 text-sm text-neutral-500">
            Quedaron registradas {{ $lote->total_ordenes }} máquina(s), cada una con su folio.
        </p>

        {{-- Folios: uno por máquina --}}
        <div class="mt-5 rounded-xl border border-brand-200 bg-brand-50 px-5 py-4 text-left">
            <div class="mb-2 text-center text-xs font-medium uppercase tracking-wide text-brand-700">Tus folios</div>
            <ul class="divide-y divide-brand-100">
                @foreach ($lote->ordenes as $orden)
                    <li class="flex items-center justify-between py-2">
                        <span class="text-sm text-neutral-600">{{ $orden->tipo_equipo_label }}@if ($orden->numero_serie) · N° {{ $orden->numero_serie }}@endif</span>
                        <span class="font-mono text-sm font-bold text-brand-600">{{ $orden->folio }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-5 rounded-xl border border-neutral-200 bg-white px-5 py-4 text-left text-sm">
            <div class="flex justify-between border-b border-neutral-100 py-1.5">
                <span class="text-neutral-500">Sucursal</span>
                <span class="font-medium text-neutral-900">{{ $lote->sucursal?->nombre }}</span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-neutral-500">Entrega estimada</span>
                <span class="font-medium text-neutral-900">{{ $lote->ordenes->first()?->fecha_entrega?->format('d-m-Y') }}</span>
            </div>
        </div>

        <p class="mt-5 text-sm text-neutral-500">
            Muéstrale esta pantalla al encargado del mostrador. Te llegará un correo con el detalle cuando confirmen la recepción de cada equipo.
        </p>

        @if (! empty($urlInicio))
            <a href="{{ $urlInicio }}"
               class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">
                Volver al inicio
            </a>
        @endif
    </div>
</x-guest-layout>
