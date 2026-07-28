{{-- Informe del servicio INDUSTRIAL (agenda de terreno) por período: uso de
     repuestos en números, % por tipo de trabajo y servicios más usados. --}}
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Informe · Industrial" subtitle="Estadísticas del servicio en terreno por período."
                       :back="route('admin.servicio-tecnico.informe')" backTitle="Volver a informes" />
    </x-slot>

    <div class="space-y-6 py-12">

        {{-- Selector de período --}}
        <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                <div>
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Período</h3>
                    <p class="mt-0.5 text-lg font-semibold text-neutral-900">{{ $periodoLabel }}</p>
                    <p class="text-sm text-neutral-500">Servicio industrial (terreno)</p>
                </div>
                <form method="GET" action="{{ route('admin.servicio-tecnico.informe.industrial') }}" class="flex flex-wrap items-end gap-2">
                    <div class="w-28">
                        <x-input-label for="anio" value="Año" />
                        <x-select id="anio" name="anio" class="mt-1">
                            @foreach ($anios as $a)
                                <option value="{{ $a }}" @selected($anio === $a)>{{ $a }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="w-40">
                        <x-input-label for="mes" value="Mes" />
                        <x-select id="mes" name="mes" class="mt-1">
                            <option value="">Todo el año</option>
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected($mes === $m)>{{ ucfirst(\Illuminate\Support\Carbon::create($anio, $m, 1)->translatedFormat('F')) }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <x-primary-button>Ver</x-primary-button>
                </form>
            </div>
        </div>

        {{-- KPIs del período: trabajos (total/realizados/pendientes/visitas) + repuestos.
             Realizados y Pendientes son cliqueables: despliegan la lista de trabajos que
             hay detrás del número (lo hecho / lo por hacer) sin salir del informe. --}}
        <div x-data="{ abierto: null }" class="dg-enter space-y-4">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div class="relative rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="pr-6 text-xs font-medium uppercase tracking-wide text-neutral-500">Trabajos en el período</p>
                    <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ number_format($total, 0, ',', '.') }}</p>
                    <span class="absolute right-2 top-2"><x-info-tip>Trabajos con fecha en el período (agendados o realizados; no cuenta cancelados ni solicitudes por coordinar).</x-info-tip></span>
                </div>
                <div class="relative rounded-2xl border bg-white p-3 shadow-sm sm:p-4 transition"
                     :class="abierto === 'realizados' ? 'border-green-400 ring-1 ring-green-200' : 'border-neutral-200'">
                    <button type="button" class="block w-full text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 rounded-lg"
                            @click="abierto = abierto === 'realizados' ? null : 'realizados'"
                            :aria-expanded="abierto === 'realizados' ? 'true' : 'false'">
                        <p class="pr-6 text-xs font-medium uppercase tracking-wide text-neutral-500">Realizados</p>
                        <p class="mt-1 text-2xl font-semibold text-green-600">{{ number_format($realizados, 0, ',', '.') }}</p>
                        <p class="text-xs text-neutral-400">{{ $pctCumplimiento }}% del período</p>
                        <span class="mt-1 inline-flex items-center gap-0.5 text-xs font-medium text-green-600">
                            <span x-text="abierto === 'realizados' ? 'Ocultar detalle' : 'Ver detalle'">Ver detalle</span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="abierto === 'realizados' && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                        </span>
                    </button>
                    <span class="absolute right-2 top-2"><x-info-tip>Trabajos ya realizados y su porcentaje sobre el total del período (cumplimiento). Clic en el número para ver la lista.</x-info-tip></span>
                </div>
                <div class="relative rounded-2xl border bg-white p-3 shadow-sm sm:p-4 transition"
                     :class="abierto === 'pendientes' ? 'border-brand-400 ring-1 ring-brand-200' : 'border-neutral-200'">
                    <button type="button" class="block w-full text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300 focus-visible:ring-offset-2 rounded-lg"
                            @click="abierto = abierto === 'pendientes' ? null : 'pendientes'"
                            :aria-expanded="abierto === 'pendientes' ? 'true' : 'false'">
                        <p class="pr-6 text-xs font-medium uppercase tracking-wide text-neutral-500">Pendientes</p>
                        <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ number_format($pendientes, 0, ',', '.') }}</p>
                        <p class="text-xs text-neutral-400">{{ $pctPendientes }}% del período · agendados sin realizar</p>
                        <span class="mt-1 inline-flex items-center gap-0.5 text-xs font-medium text-brand-600">
                            <span x-text="abierto === 'pendientes' ? 'Ocultar detalle' : 'Ver detalle'">Ver detalle</span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="abierto === 'pendientes' && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                        </span>
                    </button>
                    <span class="absolute right-2 top-2"><x-info-tip>Trabajos agendados en el período que aún no se marcan como realizados, y su % del total. Clic en el número para ver la lista.</x-info-tip></span>
                </div>
                <div class="relative rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="pr-6 text-xs font-medium uppercase tracking-wide text-neutral-500">Visitas técnicas</p>
                    <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ number_format($visitas, 0, ',', '.') }}</p>
                    <p class="text-xs text-neutral-400">{{ $pctVisitas }}% del período · {{ $visitasRealizadas }} realizadas</p>
                    <span class="absolute right-2 top-2"><x-info-tip>Visitas técnicas (diagnóstico + cotización) del período y su % del total. La conversión visita → trabajo derivado se medirá cuando enlacemos ambos.</x-info-tip></span>
                </div>
                <div class="relative rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="pr-6 text-xs font-medium uppercase tracking-wide text-neutral-500">Repuestos usados</p>
                    <p class="mt-1 text-2xl font-semibold text-brand-600">{{ number_format($totalUnidadesRepuestos, 0, ',', '.') }}</p>
                    <p class="text-xs text-neutral-400">unidades</p>
                    <span class="absolute right-2 top-2"><x-info-tip>Unidades totales de repuestos que el técnico registró al cerrar los trabajos del período.</x-info-tip></span>
                </div>
                <div class="relative rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="pr-6 text-xs font-medium uppercase tracking-wide text-neutral-500">Repuestos distintos</p>
                    <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ number_format($totalNombresRepuestos, 0, ',', '.') }}</p>
                    <span class="absolute right-2 top-2"><x-info-tip>Cantidad de repuestos distintos usados en el período.</x-info-tip></span>
                </div>
            </div>

            {{-- Paneles desplegables del detalle (uno a la vez). --}}
            <div x-show="abierto === 'realizados'" x-transition x-cloak
                 class="overflow-hidden rounded-2xl border border-green-200 bg-white shadow-sm">
                <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Realizados — {{ $periodoLabel }}</h3>
                    <span class="text-xs text-neutral-400">· {{ number_format($realizados, 0, ',', '.') }} {{ $realizados === 1 ? 'trabajo' : 'trabajos' }}</span>
                </div>
                @include('admin.servicio-tecnico.partials._trabajos-detalle', ['trabajos' => $realizadosLista, 'modo' => 'realizado'])
            </div>
            <div x-show="abierto === 'pendientes'" x-transition x-cloak
                 class="overflow-hidden rounded-2xl border border-brand-200 bg-white shadow-sm">
                <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Pendientes — {{ $periodoLabel }}</h3>
                    <span class="text-xs text-neutral-400">· {{ number_format($pendientes, 0, ',', '.') }} {{ $pendientes === 1 ? 'trabajo' : 'trabajos' }}</span>
                </div>
                @include('admin.servicio-tecnico.partials._trabajos-detalle', ['trabajos' => $pendientesLista, 'modo' => 'pendiente'])
            </div>
        </div>

        {{-- Desgloses: % por tipo de trabajo + servicios más usados --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por tipo de trabajo</h3>
                    <x-info-tip>Porcentaje de trabajos por tipo (reparación, instalación, mantención, visita técnica) sobre el total del período.</x-info-tip>
                </div>
                @include('admin.servicio-tecnico.partials._ranking', [
                    'items' => $porTipo->map(fn ($t) => (object) ['nombre' => \App\Models\AgendaTrabajo::TIPO_ETIQUETAS[$t->nombre] ?? $t->nombre, 'cantidad' => $t->cantidad]),
                    'totalPeriodo' => $total,
                    'vacio' => 'Sin trabajos en el período.',
                ])
            </div>
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Servicios que más se usan</h3>
                    <x-info-tip>Servicios del catálogo con más trabajos en el período. Los trabajos "fuera de tarifa" (solo descripción) se agrupan aparte.</x-info-tip>
                </div>
                @include('admin.servicio-tecnico.partials._ranking', [
                    'items' => $topServicios,
                    'sinNombre' => 'Fuera de tarifa / detalle libre',
                    'totalPeriodo' => $total,
                    'vacio' => 'Sin trabajos en el período.',
                ])
            </div>
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Clientes que más solicitan</h3>
                    <x-info-tip>Clientes con más trabajos de terreno en el período (agrupados por RUT).</x-info-tip>
                </div>
                @include('admin.servicio-tecnico.partials._ranking', [
                    'items' => $topClientes->map(fn ($c) => (object) [
                        'nombre' => trim(($c->nombre ?: 'Sin nombre').($c->cliente_rut ? ' · '.$c->cliente_rut : '')),
                        'cantidad' => $c->cantidad,
                    ]),
                    'vacio' => 'Sin trabajos en el período.',
                ])
            </div>
        </div>

        {{-- Uso de repuestos en números (detalle) --}}
        <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Uso de repuestos en el período</h3>
                <x-info-tip>Suma de los repuestos que el técnico industrial registró al cerrar cada trabajo. Sirve de apoyo para reponer stock (membranas, filtros, etc.).</x-info-tip>
            </div>
            @if ($repuestos->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-neutral-500 sm:px-6">Sin repuestos registrados en el período.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-neutral-500">
                                <th class="px-4 py-2 font-medium sm:px-6">Repuesto</th>
                                <th class="px-4 py-2 text-right font-medium">Unidades</th>
                                <th class="px-4 py-2 text-right font-medium sm:pr-6">Trabajos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($repuestos as $r)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-neutral-900 sm:px-6">{{ $r->nombre }}</td>
                                    <td class="px-4 py-2.5 text-right text-neutral-700">{{ number_format($r->unidades, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2.5 text-right text-neutral-500 sm:pr-6">{{ $r->trabajos }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-neutral-100 px-4 py-3 text-sm text-neutral-600 sm:px-6">
                    Total: <span class="font-semibold text-neutral-900">{{ number_format($totalUnidadesRepuestos, 0, ',', '.') }}</span> unidades
                    ({{ $totalNombresRepuestos }} {{ $totalNombresRepuestos === 1 ? 'repuesto distinto' : 'repuestos distintos' }})
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
