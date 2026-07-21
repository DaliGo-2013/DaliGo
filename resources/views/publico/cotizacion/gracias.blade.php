{{--
    Confirmación tras responder la cotización (link firmado). Refleja lo que
    quedó registrado; si llegó aquí sin responder (carrera), lo dice igual.
--}}
<x-guest-layout>
    <div class="text-center">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full
                     {{ $cotizacion->estado === 'rechazada' ? 'bg-neutral-100 text-neutral-500' : 'bg-brand-100 text-brand-600' }}">
            <x-icon.check class="h-8 w-8" />
        </span>

        <h1 class="mt-5 text-xl font-bold tracking-tight text-neutral-900">¡Gracias, {{ \Illuminate\Support\Str::of($cotizacion->orden->cliente_nombre)->before(' ') }}!</h1>

        @if (in_array($cotizacion->estado, ['aceptada', 'rechazada'], true))
            <p class="mt-1 text-sm text-neutral-500">Registramos tu respuesta el {{ $cotizacion->respondida_at?->format('d-m-Y H:i') }}.</p>

            <div class="mt-5 rounded-xl border px-5 py-4
                        {{ $cotizacion->estado === 'aceptada' ? 'border-brand-200 bg-brand-50' : 'border-neutral-200 bg-neutral-50' }}">
                <div class="text-xs font-medium uppercase tracking-wide {{ $cotizacion->estado === 'aceptada' ? 'text-brand-700' : 'text-neutral-500' }}">Tu respuesta</div>
                <div class="text-2xl font-bold tracking-wide {{ $cotizacion->estado === 'aceptada' ? 'text-brand-600' : 'text-neutral-700' }}">
                    {{ $cotizacion->estado === 'aceptada' ? 'ACEPTO' : 'NO ACEPTO' }}
                </div>
                <div class="mt-1 text-sm text-neutral-500">Orden {{ $cotizacion->orden->folio }} · ${{ number_format((int) $cotizacion->costo_total, 0, ',', '.') }}</div>
            </div>

            <p class="mt-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">
                @if ($cotizacion->estado === 'aceptada')
                    Nuestro equipo ya fue avisado y comenzará el trabajo. Te contactaremos cuando el equipo esté listo.
                @else
                    Nuestro equipo ya fue avisado. Te contactaremos para coordinar el retiro del equipo.
                @endif
            </p>
        @else
            <p class="mt-1 text-sm text-neutral-500">Esta cotización ya no estaba disponible para responder.</p>
        @endif
    </div>
</x-guest-layout>
