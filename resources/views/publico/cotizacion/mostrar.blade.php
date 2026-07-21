{{--
    Página PÚBLICA de la cotización (link firmado del correo). Muestra la carta
    desde el SNAPSHOT y, si sigue vigente, los botones ACEPTO / NO ACEPTO — sin
    campo de comentario (decisión del dueño: evitar el ida y vuelta). Si ya se
    respondió / reemplazó / venció, muestra el estado en vez de los botones.
--}}
@php
    $orden = $cotizacion->orden;
    $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
@endphp
<x-guest-layout>
    <div>
        <div class="text-center">
            <h1 class="text-xl font-bold tracking-tight text-neutral-900">Cotización de reparación</h1>
            <p class="mt-1 text-sm text-neutral-500">Orden {{ $orden->folio }} · {{ $cotizacion->created_at->format('d-m-Y') }}</p>
        </div>

        <p class="mt-4 text-sm leading-relaxed text-neutral-600">
            Estimado(a) <span class="font-medium text-neutral-900">{{ $orden->cliente_nombre }}</span>:
            revisamos tu {{ mb_strtolower($orden->tipo_equipo_label) }}@if ($orden->numero_serie) (N° serie {{ $orden->numero_serie }})@endif
            y este es el detalle del trabajo necesario.
        </p>

        {{-- Qué y por qué --}}
        <div class="mt-4 rounded-xl border border-neutral-200 bg-white px-5 py-4 text-left text-sm">
            @if (filled($orden->falla_reportada))
                <div class="border-b border-neutral-100 py-1.5">
                    <div class="text-xs uppercase tracking-wide text-neutral-400">Falla reportada</div>
                    <div class="mt-0.5 text-neutral-900">{{ $orden->falla_reportada }}</div>
                </div>
            @endif
            @if (filled($cotizacion->causa_falla))
                <div class="border-b border-neutral-100 py-1.5">
                    <div class="text-xs uppercase tracking-wide text-neutral-400">Diagnóstico del técnico</div>
                    <div class="mt-0.5 text-neutral-900">{{ $cotizacion->causa_falla }}</div>
                </div>
            @endif
            @if (filled($cotizacion->trabajo_realizado))
                <div class="py-1.5">
                    <div class="text-xs uppercase tracking-wide text-neutral-400">Trabajo a realizar</div>
                    <div class="mt-0.5 text-neutral-900">{{ $cotizacion->trabajo_realizado }}</div>
                </div>
            @endif
        </div>

        {{-- Detalle de valores (del snapshot) --}}
        <div class="mt-4 overflow-hidden rounded-xl border border-neutral-200 bg-white text-sm">
            <div class="bg-neutral-50 px-5 py-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Detalle</div>
            @foreach ($cotizacion->repuestos ?? [] as $r)
                <div class="flex justify-between border-t border-neutral-100 px-5 py-2">
                    <span class="text-neutral-700">{{ $r['cantidad'] }}× {{ $r['nombre'] }}</span>
                    <span class="font-medium text-neutral-900">{{ $clp($r['subtotal']) }}</span>
                </div>
            @endforeach
            @if ($cotizacion->mano_obra > 0)
                <div class="flex justify-between border-t border-neutral-100 px-5 py-2">
                    <span class="text-neutral-700">Mano de obra</span>
                    <span class="font-medium text-neutral-900">{{ $clp($cotizacion->mano_obra) }}</span>
                </div>
            @endif
            @if ($cotizacion->descuento_monto > 0)
                <div class="flex justify-between border-t border-neutral-100 px-5 py-2 text-green-700">
                    <span>Descuento ({{ $cotizacion->descuento_pct }}%)</span>
                    <span class="font-medium">−{{ $clp($cotizacion->descuento_monto) }}</span>
                </div>
            @endif
        </div>

        {{-- Total --}}
        <div class="mt-4 rounded-xl border border-brand-200 bg-brand-50 px-5 py-4 text-center">
            <div class="text-xs font-medium uppercase tracking-wide text-brand-700">Costo total a pagar</div>
            <div class="text-3xl font-bold tracking-wide text-brand-600">{{ $clp($cotizacion->costo_total) }}</div>
        </div>

        @if ($cotizacion->esRespondible())
            {{-- Respuesta: SOLO dos botones (sin comentario). --}}
            <form method="POST" action="{{ $urlRespuesta }}" class="mt-5" data-una-vez>
                @csrf
                {{-- Honeypot anti-bots (oculto; humanos no lo ven ni llenan). --}}
                <input type="text" name="sitio_web" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                <p class="mb-3 text-center text-sm text-neutral-600">¿Autorizas este trabajo por el valor indicado?</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <button type="submit" name="respuesta" value="aceptada"
                            class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-600 px-4 text-base font-semibold text-white transition hover:bg-brand-700">
                        ACEPTO
                    </button>
                    <button type="submit" name="respuesta" value="rechazada"
                            onclick="return confirm('¿Confirmas que NO aceptas la cotización?');"
                            class="inline-flex h-12 items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-base font-semibold text-neutral-700 transition hover:bg-neutral-50">
                        NO ACEPTO
                    </button>
                </div>
                <p class="mt-3 text-center text-xs text-neutral-400">
                    Tu respuesta queda registrada al instante y le avisa a nuestro equipo.
                    @if ($cotizacion->vence_at) Cotización válida hasta el {{ $cotizacion->vence_at->format('d-m-Y') }}. @endif
                </p>
            </form>
        @else
            {{-- No respondible: estado en vez de botones. --}}
            <div class="mt-5 rounded-xl px-5 py-4 text-center text-sm
                        {{ $cotizacion->estado === 'aceptada' ? 'bg-brand-50 text-brand-700' : ($cotizacion->estado === 'rechazada' ? 'bg-red-50 text-red-700' : 'bg-neutral-100 text-neutral-600') }}">
                @if (in_array($cotizacion->estado, ['aceptada', 'rechazada'], true))
                    Ya registramos tu respuesta el {{ $cotizacion->respondida_at?->format('d-m-Y H:i') }}:
                    <span class="font-semibold">{{ $cotizacion->estado === 'aceptada' ? 'ACEPTASTE' : 'NO ACEPTASTE' }}</span> esta cotización.
                @elseif ($cotizacion->estado === 'reemplazada')
                    Hay una cotización más reciente para esta orden: revisa el último correo que te enviamos.
                @else
                    Esta cotización venció el {{ $cotizacion->vence_at?->format('d-m-Y') }}. Contáctanos para renovarla.
                @endif
            </div>
        @endif
    </div>
</x-guest-layout>
