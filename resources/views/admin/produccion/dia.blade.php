<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Producción · '.ucfirst($fecha->translatedFormat('l d \\d\\e F'))"
                       subtitle="Todas las producciones de este día."
                       :back="route('admin.produccion.index')">
            <x-slot name="action">
                <form method="GET" action="{{ route('admin.produccion.dia') }}" class="flex items-end gap-2">
                    <div>
                        <x-input-label for="fecha" value="Ver otro día" />
                        <x-text-input id="fecha" name="fecha" type="date" class="mt-1" :value="$fecha->toDateString()" />
                    </div>
                    <x-secondary-button>Ver</x-secondary-button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            @include('admin.produccion.partials._totales', ['chips' => [
                ['label' => 'Asignado', 'valor' => number_format($resumen['asignadas'], 0, ',', '.'), 'tono' => null],
                ['label' => 'Producido', 'valor' => number_format($resumen['producido'], 0, ',', '.'), 'tono' => 'brand'],
                ['label' => 'Merma', 'valor' => number_format($resumen['merma'], 0, ',', '.').' · '.$resumen['merma_pct'].'%', 'tono' => 'muted'],
                ['label' => 'Tasa 1ª', 'valor' => $resumen['tasa1'].'%', 'tono' => null],
            ]])

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @if ($porMaquina->isNotEmpty())
                    <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                        <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por máquina</h3>
                        </div>
                        <ul class="divide-y divide-neutral-100">
                            @foreach ($porMaquina as $fila)
                                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 sm:px-6">
                                    @if ($fila->maquina_id)
                                        <a href="{{ route('admin.produccion.maquina', $fila->maquina_id) }}" class="text-sm font-medium text-neutral-900 transition duration-150 hover:text-brand-600">{{ $fila->maquina }}@if ($porMaquinaMultiSucursal && $fila->sucursal)<span class="text-neutral-400"> · {{ $fila->sucursal }}</span>@endif</a>
                                    @else
                                        <span class="text-sm font-medium text-neutral-900">Sin máquina</span>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
                                        <x-produccion.metrica label="1ª" w="w-12">{{ number_format($fila->primera, 0, ',', '.') }}</x-produccion.metrica>
                                        <x-produccion.metrica label="2ª" w="w-12">{{ number_format($fila->segunda, 0, ',', '.') }}</x-produccion.metrica>
                                        <x-produccion.metrica label="Malos" w="w-12">{{ number_format($fila->malo, 0, ',', '.') }}</x-produccion.metrica>
                                        <x-produccion.metrica label="Dañadas" w="w-12">{{ number_format($fila->danada, 0, ',', '.') }}</x-produccion.metrica>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por tipo de botellón</h3>
                    </div>
                    @include('admin.produccion.partials._desglose', [
                        'items' => $porTipo,
                        'linkRoute' => 'admin.produccion.tipo',
                        'linkKey' => 'tipoBotellon',
                        'sinNombre' => 'Sin tipo',
                    ])
                </div>
            </div>

            <x-list-card title="Reportes del día" :count="$reportes->count()" :countLabel="\Illuminate\Support\Str::plural('reporte', $reportes->count())">
                @forelse ($reportes as $reporte)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($reporte->soplador->name, 0, 1) }}</x-avatar>
                        </x-slot>

                        <a href="{{ route('admin.produccion.soplador', $reporte->soplador) }}"
                           class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $reporte->soplador->name }}</a>
                        <p class="truncate text-sm text-neutral-500">
                            Turno {{ $reporte->turno }} · asignadas {{ number_format($reporte->asignadas, 0, ',', '.') }}
                        </p>

                        <x-slot name="meta">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
                                <x-produccion.metrica label="1ª" w="w-12">{{ $reporte->primera }}</x-produccion.metrica>
                                <x-produccion.metrica label="2ª" w="w-12">{{ $reporte->segunda }}</x-produccion.metrica>
                                <x-produccion.metrica label="Malos" w="w-12">{{ $reporte->malo }}</x-produccion.metrica>
                                <x-produccion.metrica label="Dañadas" w="w-12">{{ $reporte->danada }}</x-produccion.metrica>
                                <span class="inline-flex items-baseline gap-1 font-medium {{ $reporte->diferencia === 0 ? 'text-neutral-400' : 'text-neutral-900' }}">
                                    Δ <span class="w-12 text-right tabular-nums">{{ $reporte->diferencia }}</span>
                                </span>
                                <x-produccion.estado-badge :estado="$reporte->estado" />
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <a href="{{ route('admin.produccion.reporte.show', $reporte) }}"
                               class="whitespace-nowrap text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">Revisar</a>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-neutral-500">No hubo producciones este día.</li>
                @endforelse
            </x-list-card>
        </div>
    </div>
</x-app-layout>
