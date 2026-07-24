<x-app-layout>
    {{-- Historial propio del operario (solo lectura): sus ultimos 45 dias por
         defecto, con filtro de fechas dentro de un colapsable para que la
         pantalla no crezca. Cada dia abre su reporte (mi.show). --}}
    @php
        $dias = \App\Models\ProduccionReporte::HISTORIAL_DIAS;
        $anioActual = \App\Support\FechaNegocio::ahora()->year;
        // Rango legible para el resumen colapsado del filtro (dd/mm/aaaa: en
        // planta se lee la fecha corrida, no el formato largo).
        $rangoLegible = \Illuminate\Support\Carbon::parse($desde)->format('d-m-Y')
            .' al '.\Illuminate\Support\Carbon::parse($hasta)->format('d-m-Y');
    @endphp

    <div class="py-4 sm:py-8">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <x-secondary-link :href="route('produccion.mi.index')" class="inline-flex min-h-12 items-center gap-1">
                <span aria-hidden="true">&larr;</span> Mis producciones
            </x-secondary-link>

            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-semibold leading-tight text-neutral-900">Mi historial</h2>
                <p class="text-xs text-neutral-500">
                    @if ($esDefault)
                        últimos {{ $dias }} días
                    @else
                        {{ $rangoLegible }}
                    @endif
                </p>
            </div>

            {{-- Filtro de fechas: colapsado por defecto (un tap), abierto cuando
                 el operario ya eligió un rango. --}}
            <div x-data="{ paneles: { fechas: {{ $esDefault ? 'false' : 'true' }} } }" class="mb-4">
                <x-collapsible label="Cambiar fechas" model="paneles.fechas" class="bg-white shadow-sm">
                    <x-slot name="summary">{{ $rangoLegible }}</x-slot>

                    <form method="GET" action="{{ route('produccion.mi.historial') }}" class="space-y-3">
                        {{-- max=hoy: no se puede pedir futuro desde el date picker
                             (el controlador lo recorta igual como respaldo). --}}
                        <div>
                            <x-input-label for="desde" value="Desde" />
                            <x-text-input id="desde" name="desde" type="date" class="mt-1.5 h-12" :value="$desde" :max="$hoy" />
                        </div>
                        <div>
                            <x-input-label for="hasta" value="Hasta" />
                            <x-text-input id="hasta" name="hasta" type="date" class="mt-1.5 h-12" :value="$hasta" :max="$hoy" />
                        </div>
                        <x-primary-button class="h-12 w-full justify-center">Ver estos días</x-primary-button>
                    </form>

                    @unless ($esDefault)
                        <x-secondary-link :href="route('produccion.mi.historial')"
                                          class="mt-2 flex min-h-12 items-center justify-center">
                            Volver a los últimos {{ $dias }} días
                        </x-secondary-link>
                    @endunless
                </x-collapsible>
            </div>

            {{-- Resumen del período: lo que le importa al operario — cuánto sirvió,
                 cuánto se perdió y cuántos turnos. Las tasas y el Δ son lenguaje
                 del jefe (viven en su panel y en el detalle del reporte). --}}
            <div class="dg-enter mb-4 rounded-2xl border border-neutral-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm">
                    <x-produccion.metrica label="Vendibles" w="w-32" tone="brand">{{ number_format($totales['vendibles'], 0, ',', '.') }}</x-produccion.metrica>
                    <x-produccion.metrica label="Merma" w="w-28">{{ number_format($totales['merma'], 0, ',', '.') }}</x-produccion.metrica>
                    <x-produccion.metrica label="Turnos" w="w-20">{{ $totales['turnos'] }}</x-produccion.metrica>
                </div>
            </div>

            @if ($reportes->isEmpty())
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-sm text-neutral-500">No hay producción registrada entre esas fechas.</p>
                    <p class="mt-1 text-xs text-neutral-400">Prueba con otro rango de fechas.</p>
                </div>
            @else
                <div class="dg-enter space-y-3">
                    @foreach ($reportes as $reporte)
                        @php
                            $editable = $reporte->editablePorSoplador();
                            // fecha es cast `date`: translatedFormat directo, JAMAS
                            // enChile() (eso es solo para timestamps con hora).
                            $formato = $reporte->fecha->year === $anioActual
                                ? 'l d \d\e F'
                                : 'l d \d\e F Y';
                        @endphp
                        <a href="{{ route('produccion.mi.show', $reporte) }}"
                           class="block rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm transition duration-150 hover:bg-neutral-50 active:scale-[0.99] sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-neutral-900">
                                        {{ $reporte->fecha->translatedFormat($formato) }}
                                        <span class="font-normal text-neutral-500">· turno {{ $reporte->turno }}</span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <x-produccion.estado-badge :estado="$reporte->estado" />
                                    @if (\App\Support\FechaNegocio::esHoy($reporte->fecha))
                                        <x-badge variant="neutral">hoy</x-badge>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <p class="text-xs text-neutral-500">
                                    <span class="font-medium text-brand-600">{{ number_format($reporte->producido, 0, ',', '.') }} vendibles</span>
                                    @if ($reporte->merma > 0)
                                        · merma {{ number_format($reporte->merma, 0, ',', '.') }}
                                    @endif
                                </p>
                                <span class="shrink-0 text-sm font-semibold {{ $editable ? 'text-brand-700' : 'text-neutral-400' }}">
                                    {{ $editable ? 'Reportar →' : 'Ver →' }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
