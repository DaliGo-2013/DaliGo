{{--
    Cola de bodega ("McDonald's", P-DSP-04): pantalla para el monitor colgado en
    bodega. Muestra las cargas PREPARADAS esperando retiro, la que espera hace
    más rato primero.

    Se refresca sola por POLLING del conteo (patrón porConfirmarConteo de ST): un
    monitor encendido todo el día no puede recargar el HTML completo cada pocos
    segundos, así que se pide un JSON de 20 bytes y solo si el número CAMBIÓ se
    recarga la página. `visibilityState` evita gastar red con la pestaña oculta.

    Tipografía grande a propósito: se lee de lejos, de pie y con apuro. Estados
    por relleno (paleta de 4): la carga que espera va en naranjo sólido.
--}}
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cola de bodega"
                       subtitle="Cargas preparadas esperando retiro. La pantalla se actualiza sola.">
            <x-slot name="action">
                <x-button-link :href="route('admin.despachos.index')">Ver todos los despachos</x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 py-8 sm:py-12">
        <div class="flex items-baseline gap-3">
            <span class="text-5xl font-semibold tabular-nums text-neutral-900">{{ $total }}</span>
            <span class="text-lg text-neutral-500">{{ $total === 1 ? 'carga esperando' : 'cargas esperando' }}</span>
        </div>

        @if ($despachos->isEmpty())
            <div class="rounded-2xl border border-neutral-200 bg-white p-10 text-center shadow-sm">
                <p class="text-2xl font-semibold text-neutral-900">Bodega al día</p>
                <p class="mt-2 text-sm text-neutral-500">No hay cargas esperando retiro.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($despachos as $despacho)
                    <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-3xl font-semibold tracking-tight text-neutral-900">{{ $despacho->codigo }}</p>
                                <p class="mt-1 truncate text-base text-neutral-700">
                                    {{ $despacho->documento?->cliente?->razon_social ?? 'Sin cliente' }}
                                </p>
                                <p class="mt-0.5 text-sm text-neutral-500">
                                    Folio {{ $despacho->documento?->folio ?? '—' }}
                                    · {{ $despacho->zona?->nombre ?? 'Sin zona' }}
                                </p>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-full bg-brand-600 px-3 py-1 text-sm font-semibold text-white">
                                Esperando
                            </span>
                        </div>

                        <p class="mt-4 text-xs text-neutral-400">
                            Preparado {{ $despacho->created_at?->enChile()->diffForHumans() }}
                        </p>
                    </div>
                @endforeach
            </div>

            @if ($total > $despachos->count())
                <p class="text-sm text-neutral-500">
                    Se muestran las {{ $despachos->count() }} más antiguas de {{ $total }}.
                </p>
            @endif
        @endif
    </div>

    {{-- Script inline: el layout no expone un @stack('scripts') (mismo idioma
         que el poll del listado de Servicio Técnico). --}}
    <script>
        (function () {
            var base = {{ $total }};
            var url = '{{ route('admin.despachos.cola.conteo') }}';
            setInterval(function () {
                if (document.visibilityState !== 'visible') return;
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) {
                        // Solo recarga si el número cambió (entró o salió una
                        // carga): así el monitor no parpadea sin motivo.
                        if (d && d.total !== base) window.location.reload();
                    })
                    .catch(function () {});
            }, 20000);
        })();
    </script>
</x-app-layout>
