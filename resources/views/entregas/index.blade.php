{{--
    Hoja de ruta del conductor (P-DSP-05, M08-MVP). Pantalla de OPERARIO
    (doctrina CLAUDE.md): sin banda de cabecera, título compacto, mínimos
    elementos, objetivos táctiles grandes — se usa en el celular, en la calle.

    Los despachos viajan inline: con la pestaña ABIERTA la hoja sigue operable
    sin señal (las confirmaciones se encolan en IndexedDB y drenan al volver la
    señal). Recargar sin señal cae a /offline del SW — límite aceptado del MVP:
    cachear HTML autenticado está prohibido (SPIKE-PWA §3).
--}}
<x-app-layout ancho="formulario">
    <div class="space-y-5 py-6">

        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">Mis entregas</h2>
                <p class="mt-0.5 text-sm text-neutral-500">{{ \App\Support\FechaNegocio::ahora()->translatedFormat('l j \d\e F') }}</p>
            </div>
            {{-- Indicador propio: acá SÍ se puede trabajar sin señal (hay cola).
                 El <x-produccion.indicador-red> dice "espera la señal" — falso aquí. --}}
            <span x-data x-cloak x-show="! $store.red.online" role="status"
                  class="inline-flex shrink-0 items-center gap-2 rounded-full bg-neutral-800 px-3 py-1.5 text-xs font-medium text-white">
                <span class="h-2 w-2 rounded-full bg-white/60"></span>
                Sin señal — tus entregas se guardan y se envían solas
            </span>
        </div>

        <x-status-alert :status="session('status')" />

        @if ($despachos->isEmpty())
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 text-center shadow-sm sm:p-6">
                <p class="text-lg font-semibold text-neutral-900">Sin entregas pendientes</p>
                <p class="mt-1 text-sm text-neutral-500">Cuando bodega te asigne un despacho retirado, aparece aquí.</p>
            </div>
        @endif

        @foreach ($porZona as $zona => $grupo)
            <div class="space-y-3">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                    {{ $zona }} · {{ $grupo->count() }}
                </h3>

                @foreach ($grupo as $despacho)
                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-lg font-semibold tracking-tight text-neutral-900">{{ $despacho->codigo }}</p>
                                <p class="mt-0.5 truncate text-sm text-neutral-700">
                                    {{ $despacho->documento?->cliente?->razon_social ?? 'Sin cliente' }}
                                </p>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    Folio {{ $despacho->documento?->folio ?? '—' }}
                                    · retirado {{ $despacho->retirado_at?->enChile()->format('H:i') ?? 's/h' }}
                                </p>
                            </div>
                            <x-despacho.estado-badge :estado="$despacho->estado" class="shrink-0" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

    </div>
</x-app-layout>
