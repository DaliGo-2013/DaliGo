{{--
    Pantalla de éxito de la devolución pública (M13). Llega por link FIRMADO
    con binding por token (no se pueden enumerar folios ajenos). Solo lo
    mínimo del PROPIO registro: folio destacado + resumen. Nada de terceros,
    nada de la app.
--}}
<x-guest-layout>
    <div class="text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-50">
            <svg class="h-8 w-8 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </div>

        <h1 class="mt-4 text-xl font-bold tracking-tight text-neutral-900">Devolución registrada</h1>
        <p class="mt-1 text-sm text-neutral-500">Guarda este folio para hacer seguimiento:</p>

        <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-brand-600">{{ $devolucion->folio }}</p>

        <div class="mx-auto mt-6 max-w-sm rounded-2xl border border-neutral-200 bg-white p-4 text-left shadow-sm">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Producto</dt>
                    <dd class="text-right font-medium text-neutral-800">
                        {{ $devolucion->items->first()?->cantidad }}× {{ $devolucion->items->first()?->descripcion }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Canal</dt>
                    <dd class="font-medium text-neutral-800">{{ \App\Models\Devolucion::CANALES[$devolucion->canal] ?? $devolucion->canal }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Sucursal</dt>
                    <dd class="font-medium text-neutral-800">{{ $devolucion->sucursal?->nombre }}</dd>
                </div>
            </dl>
        </div>

        <p class="mx-auto mt-4 max-w-sm text-sm text-neutral-500">
            Cuando tu producto llegue a bodega te avisaremos a
            <span class="font-medium text-neutral-700">{{ $devolucion->cliente_email }}</span>,
            y de nuevo con el resultado.
        </p>

        <a href="{{ $urlInicio }}"
           class="mt-6 inline-flex min-h-12 items-center justify-center rounded-xl border border-neutral-300 bg-white px-5 py-3 text-sm font-semibold text-neutral-700 shadow-sm transition duration-150 hover:bg-neutral-50 active:scale-[0.99]">
            Registrar otra devolución
        </a>
    </div>
</x-guest-layout>
