<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Informe · Dispensadores" subtitle="Estadísticas del taller por período.">
            <x-slot name="action">
                <x-icon-button :href="route('admin.servicio-tecnico.informe')" size="lg" variant="secondary" label="Volver a informes" title="Volver a informes">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Selector de período: un mes puntual o el año completo. --}}
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                    <div>
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Período</h3>
                        <p class="mt-0.5 text-lg font-semibold text-neutral-900">{{ $periodoLabel }}</p>
                        <p class="text-sm text-neutral-500">{{ $tipoLabel }}</p>
                    </div>
                    <form method="GET" action="{{ route('admin.servicio-tecnico.informe.dispensadores') }}" class="flex flex-wrap items-end gap-2">
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
                        <div class="w-44">
                            <x-input-label for="tipo" value="Tipo de equipo" />
                            <x-select id="tipo" name="tipo" class="mt-1">
                                <option value="">Todos los equipos</option>
                                @foreach ($tipos as $t)
                                    <option value="{{ $t }}" @selected($tipo === $t)>{{ \App\Models\OrdenServicio::etiquetaTipo($t) }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <x-primary-button>Ver</x-primary-button>
                    </form>
                </div>
            </div>

            {{-- KPIs del período --}}
            <div class="dg-enter">
                @php
                    $chips = [
                        ['Órdenes ingresadas', number_format($kpis['total'], 0, ',', '.'), null, 'Equipos que ingresaron al taller en el período elegido.'],
                        ['Garantía', number_format($kpis['garantias'], 0, ',', '.'), 'verde', 'Órdenes registradas con condición Garantía (sin cobro).'],
                        ['Reparación', number_format($kpis['reparaciones'], 0, ',', '.'), 'brand', 'Órdenes registradas con condición Reparación (con cobro).'],
                        ['% Garantía', $kpis['pctGarantia'].'%', null, 'Porcentaje de órdenes en garantía sobre el total del período.'],
                    ];
                @endphp
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($chips as [$label, $valor, $tono, $info])
                        <div class="relative rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                            <p class="pr-6 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $label }}</p>
                            <p class="mt-1 text-2xl font-semibold {{ $tono === 'brand' ? 'text-brand-600' : ($tono === 'verde' ? 'text-green-600' : 'text-neutral-900') }}">{{ $valor }}</p>
                            <span class="absolute right-2 top-2"><x-info-tip>{{ $info }}</x-info-tip></span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-neutral-400">Garantía / Reparación según la condición registrada al ingreso de cada orden.</p>
            </div>

            {{-- Desgloses del período --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Equipos que más ingresan</h3>
                        <x-info-tip align="left">Modelos del catálogo con más ingresos al taller en el período. Los ingresos por QR sin código del catálogo se agrupan como "Sin código".</x-info-tip>
                    </div>
                    @include('admin.servicio-tecnico.partials._ranking', [
                        'items' => $topEquipos,
                        'sinNombre' => 'Sin código',
                        'totalPeriodo' => $kpis['total'],
                        'vacio' => 'Sin ingresos en el período.',
                    ])
                </div>
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Clientes que más traen equipos</h3>
                        <x-info-tip align="left">Clientes con más órdenes en el período (agrupados por RUT).</x-info-tip>
                    </div>
                    @include('admin.servicio-tecnico.partials._ranking', [
                        'items' => $topClientes->map(fn ($c) => (object) [
                            'nombre' => trim(($c->nombre ?: 'Sin nombre').($c->cliente_rut ? ' · '.$c->cliente_rut : '')),
                            'cantidad' => $c->cantidad,
                        ]),
                        'vacio' => 'Sin ingresos en el período.',
                    ])
                </div>
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por tipo de equipo</h3>
                        <x-info-tip align="left">Cuántos equipos de cada tipo (dispensador, lavadora, herramienta…) ingresaron en el período.</x-info-tip>
                    </div>
                    @include('admin.servicio-tecnico.partials._ranking', [
                        'items' => $porTipo->map(fn ($t) => (object) ['nombre' => \App\Models\OrdenServicio::etiquetaTipo($t->nombre), 'cantidad' => $t->cantidad]),
                        'totalPeriodo' => $kpis['total'],
                        'vacio' => 'Sin ingresos en el período.',
                    ])
                </div>
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por estado</h3>
                        <x-info-tip align="left">En qué estado están hoy las órdenes que ingresaron en el período.</x-info-tip>
                    </div>
                    @include('admin.servicio-tecnico.partials._ranking', [
                        'items' => $porEstado->map(fn ($e) => (object) ['nombre' => \Illuminate\Support\Str::headline($e->nombre), 'cantidad' => $e->cantidad]),
                        'totalPeriodo' => $kpis['total'],
                        'vacio' => 'Sin ingresos en el período.',
                    ])
                </div>
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm lg:col-span-2">
                    <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por causa de la falla</h3>
                        <x-info-tip align="left">Diagnóstico del técnico al reparar. Sirve para reforzar la capacitación al cliente: si muchas fallas son por mal uso, conviene enseñar mejor el uso del producto. "Sin determinar" = el técnico aún no la registró.</x-info-tip>
                    </div>
                    @include('admin.servicio-tecnico.partials._ranking', [
                        'items' => $porCausa->map(fn ($c) => (object) [
                            'nombre' => \App\Models\OrdenServicio::CAUSA_FALLA_ETIQUETAS[$c->causa] ?? 'Sin determinar',
                            'cantidad' => $c->cantidad,
                        ]),
                        'totalPeriodo' => $kpis['total'],
                        'vacio' => 'Sin ingresos en el período.',
                    ])
                </div>
            </div>

            {{-- Repuestos usados (apoyo al inventario del taller) --}}
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Repuestos usados en el período</h3>
                    <x-info-tip align="left">Suma de los repuestos registrados en las reparaciones de las órdenes que ingresaron en el período. Sirve de apoyo para el control y la reposición del inventario del taller.</x-info-tip>
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
                                    <th class="px-4 py-2 text-right font-medium sm:pr-6">Órdenes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @foreach ($repuestos as $r)
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium text-neutral-900 sm:px-6">{{ $r->nombre }}</td>
                                        <td class="px-4 py-2.5 text-right text-neutral-700">{{ number_format($r->unidades, 0, ',', '.') }}</td>
                                        <td class="px-4 py-2.5 text-right text-neutral-500 sm:pr-6">{{ $r->ordenes }}</td>
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
    </div>
</x-app-layout>
