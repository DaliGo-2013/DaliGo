{{--
    Informe de devoluciones por causa y canal (M13 · P-M13-04, el cierre de
    E6). Pantalla HIJA del listado → lleva «Volver» (doctrina P-NAV-08); a
    diferencia del informe de ST no es ítem del menú: Operación ya está
    cargado y este se consulta desde el listado.

    Reutiliza el partial _ranking de ST (contrato genérico: nombre +
    cantidad, $totalPeriodo opcional → muestra % del total): las mini-barras
    van con style="width:%" porque Tailwind purga anchos dinámicos.
--}}
<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header title="Informe de devoluciones"
                       subtitle="Por causa y por canal — lo que A-12 pide para dejar de adivinar de dónde vienen."
                       :back="route('admin.devoluciones.index')" backTitle="Devoluciones" />
    </x-slot>

    <div class="space-y-6 py-6">
        {{-- Filtro de período (idioma del informe ST): mes/año; solo año = año completo. --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Período</p>
                    <p class="mt-0.5 text-lg font-semibold text-neutral-900">{{ $periodoLabel }}</p>
                </div>
                <form method="GET" action="{{ route('admin.devoluciones.informe') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <x-input-label for="anio" value="Año" />
                        <x-select id="anio" name="anio" class="mt-1">
                            @foreach ($anios as $a)
                                <option value="{{ $a }}" @selected($a === $anio)>{{ $a }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <x-input-label for="mes" value="Mes" />
                        <x-select id="mes" name="mes" class="mt-1">
                            <option value="">Todo el año</option>
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected($m === $mes)>{{ ucfirst(\Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F')) }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <x-primary-button type="submit">Ver</x-primary-button>
                </form>
            </div>
        </div>

        {{-- KPIs del período --}}
        @php
            $chips = [
                ['label' => 'Devoluciones', 'valor' => number_format($kpis['total'], 0, ',', '.'), 'tone' => 'brand'],
                ['label' => 'Por recibir', 'valor' => number_format($kpis['por_recibir'], 0, ',', '.'), 'tone' => $kpis['por_recibir'] > 0 ? 'brand' : 'neutral'],
                ['label' => 'Resueltas', 'valor' => number_format($kpis['resueltas'], 0, ',', '.'), 'tone' => 'neutral'],
                ['label' => 'Reembolsado', 'valor' => '$'.number_format($kpis['reembolsado'], 0, ',', '.'), 'tone' => 'neutral'],
            ];
        @endphp
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($chips as $chip)
                <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $chip['label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums {{ $chip['tone'] === 'brand' ? 'text-brand-600' : 'text-neutral-900' }}">{{ $chip['valor'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Desgloses: causa y canal lado a lado; el embudo abajo. --}}
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por causa</h2>
                </div>
                @include('admin.servicio-tecnico.partials._ranking', [
                    'items' => $porCausa,
                    'totalPeriodo' => $kpis['total'],
                    'vacio' => 'Sin devoluciones en el período.',
                ])
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por canal</h2>
                </div>
                @include('admin.servicio-tecnico.partials._ranking', [
                    'items' => $porCanal,
                    'totalPeriodo' => $kpis['total'],
                    'vacio' => 'Sin devoluciones en el período.',
                ])
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm xl:col-span-2">
                <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Embudo por estado</h2>
                </div>
                @include('admin.servicio-tecnico.partials._ranking', [
                    'items' => $porEstado,
                    'totalPeriodo' => $kpis['total'],
                    'vacio' => 'Sin devoluciones en el período.',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
