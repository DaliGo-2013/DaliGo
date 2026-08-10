<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$maquina->nombre"
                       :subtitle="'Rendimiento'.($maquina->sucursal ? ' · '.$maquina->sucursal->nombre : '')"
                       :back="route('admin.produccion.index')" />
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        {{-- Rango + presets semana/mes (P-M11-11): rellenan el MISMO desde/
             hasta — un solo estado de filtro para toda la página (tendencia,
             desgloses, OEE y Pareto filtran juntos). --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
            <form method="GET" action="{{ route('admin.produccion.maquina', $maquina) }}"
                  class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1"><x-input-label for="desde" value="Desde" /><x-text-input id="desde" name="desde" type="date" class="mt-1.5" :value="$desde" /></div>
                <div class="flex-1"><x-input-label for="hasta" value="Hasta" /><x-text-input id="hasta" name="hasta" type="date" class="mt-1.5" :value="$hasta" /></div>
                <div><x-primary-button>Filtrar</x-primary-button></div>
            </form>
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                @foreach ($presets as $etiqueta => $rango)
                    <x-secondary-link :href="route('admin.produccion.maquina', ['maquina' => $maquina] + $rango)">{{ $etiqueta }}</x-secondary-link>
                @endforeach
            </div>
        </div>

        @include('admin.produccion.partials._totales', ['chips' => [
            ['label' => 'Producido', 'valor' => number_format($tendencia['totales']['producido'], 0, ',', '.'), 'tono' => 'brand'],
            ['label' => 'Merma', 'valor' => number_format($tendencia['totales']['merma'], 0, ',', '.').' ('.$tendencia['totales']['merma_pct'].'%)', 'tono' => 'muted'],
            ['label' => 'Tasa 1ª', 'valor' => $tendencia['totales']['tasa1'].'%', 'tono' => null],
            ['label' => 'Reportes', 'valor' => $tendencia['totales']['reportes'], 'tono' => null],
        ]])

        {{-- OEE + Pareto del período (P-M11-11). Los factores que faltan se
             DECLARAN («sin ciclo cargado»), jamás se inventa un 100 %. --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">OEE del período</h3>
                    @if ($maquina->oee_target)
                        <span class="text-xs font-medium text-neutral-400">Meta: {{ $maquina->oee_target }}%</span>
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 p-4 sm:p-6 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Disponibilidad</dt>
                        <dd class="mt-1 text-xl font-semibold text-neutral-900">{{ $oee['disponibilidad'] !== null ? $oee['disponibilidad'].'%' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Rendimiento</dt>
                        <dd class="mt-1 text-xl font-semibold text-neutral-900">{{ $oee['rendimiento'] !== null ? $oee['rendimiento'].'%' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Calidad</dt>
                        <dd class="mt-1 text-xl font-semibold text-neutral-900">{{ $oee['calidad'] !== null ? $oee['calidad'].'%' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">OEE</dt>
                        <dd class="mt-1 text-xl font-semibold {{ $oee['oee'] !== null && $maquina->oee_target && $oee['oee'] >= $maquina->oee_target ? 'text-brand-600' : 'text-neutral-900' }}">
                            {{ $oee['oee'] !== null ? $oee['oee'].'%' : '—' }}
                        </dd>
                    </div>
                </dl>
                <div class="space-y-1 border-t border-neutral-100 px-4 py-3 text-xs text-neutral-500 sm:px-6">
                    @if ($oee['cicloSospechoso'])
                        <p class="font-medium text-neutral-700">El rendimiento salió sobre 100 %: revisa el ciclo ideal cargado en la receta (está mal cargado o le faltan cavidades).</p>
                    @endif
                    @if ($oee['sinCiclo'] !== [])
                        <p class="font-medium text-neutral-700">Sin ciclo cargado para: {{ implode(', ', $oee['sinCiclo']) }} — carga el ciclo ideal en su receta para calcular el rendimiento.</p>
                    @endif
                    @if ($oee['slots'] > 0)
                        <p>{{ $oee['slots'] }} {{ \Illuminate\Support\Str::plural('turno', $oee['slots']) }} × {{ number_format($oee['minutosTurno'], 0, ',', '.') }} min · paradas no planificadas {{ number_format($oee['minutosNoPlanificadas'], 0, ',', '.') }} min · planificadas {{ number_format($oee['minutosPlanificadas'], 0, ',', '.') }} min (dentro del plan, no descuentan)</p>
                        <p>Merma {{ number_format($oee['merma'], 0, ',', '.') }}{{ $oee['mermaPct'] !== null ? ' ('.str_replace('.', ',', (string) $oee['mermaPct']).'%)' : '' }}@if ($oee['scrap'] > 0) · de arranque: {{ number_format($oee['scrap'], 0, ',', '.') }}{{ $oee['scrapPct'] !== null ? ' ('.str_replace('.', ',', (string) $oee['scrapPct']).'% de la merma)' : '' }}@endif</p>
                    @else
                        <p>Sin turnos trabajados en el período.</p>
                    @endif
                </div>
            </div>

            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Pareto de paradas</h3>
                    <span class="text-xs font-medium text-neutral-400">{{ number_format($pareto['totalMinutos'], 0, ',', '.') }} min · {{ $pareto['totalEventos'] }} {{ \Illuminate\Support\Str::plural('evento', $pareto['totalEventos']) }}</span>
                </div>
                <ul class="divide-y divide-neutral-100">
                    @forelse ($pareto['motivos'] as $fila)
                        <li class="px-4 py-3 sm:px-6">
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                <div class="flex min-w-0 items-center gap-2">
                                    <p class="truncate text-sm font-medium text-neutral-900">{{ $fila['motivo'] }}</p>
                                    <x-badge :variant="$fila['clase'] === \App\Models\ProduccionParada::CLASE_PLANIFICADA ? 'neutral' : 'brand'">
                                        {{ $fila['clase'] === \App\Models\ProduccionParada::CLASE_PLANIFICADA ? 'planificada' : 'no planificada' }}
                                    </x-badge>
                                </div>
                                <div class="flex items-center gap-x-4 text-sm text-neutral-600">
                                    <x-produccion.metrica label="Min" w="w-24">{{ number_format($fila['minutos'], 0, ',', '.') }}</x-produccion.metrica>
                                    <x-produccion.metrica label="Eventos" w="w-24">{{ $fila['eventos'] }}</x-produccion.metrica>
                                    <x-produccion.metrica label="Acum" w="w-20">{{ str_replace('.', ',', (string) $fila['pctAcum']) }}%</x-produccion.metrica>
                                </div>
                            </div>
                            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-neutral-200">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ $pareto['motivos'][0]['minutos'] > 0 ? round($fila['minutos'] / $pareto['motivos'][0]['minutos'] * 100) : 0 }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-sm text-neutral-500">Sin paradas en el período.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Producción por día {{ $esDefault ? '· últimos 30 días' : '' }}</h3>
            </div>
            @include('admin.produccion.partials._tendencia', ['tendencia' => $tendencia, 'linkDia' => true])
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-4 py-3 sm:px-6"><h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por tipo de botellón</h3></div>
                @include('admin.produccion.partials._desglose', [
                    'items' => $porTipo,
                    'linkRoute' => 'admin.produccion.tipo', 'linkKey' => 'tipoBotellon',
                    'linkExtra' => ['desde' => $desde, 'hasta' => $hasta], 'sinNombre' => 'Sin tipo',
                ])
            </div>
            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-4 py-3 sm:px-6"><h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por soplador</h3></div>
                @include('admin.produccion.partials._desglose', [
                    'items' => $porSoplador,
                    'linkRoute' => 'admin.produccion.soplador', 'linkKey' => 'soplador',
                    'linkExtra' => ['desde' => $desde, 'hasta' => $hasta], 'sinNombre' => 'Sin soplador',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
