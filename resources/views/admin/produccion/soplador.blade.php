<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$soplador->name" subtitle="Historial de asignaciones y producción." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            {{-- Volver a la página anterior (el reporte desde donde se llegó, o la
                 lista de sopladores si se entró directo). history.back evita el
                 salto fijo a sopladores que sacaba al jefe del contexto. --}}
            <div>
                <x-secondary-link href="{{ route('admin.produccion.sopladores') }}"
                                  onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }">
                    ← Volver
                </x-secondary-link>
            </div>

            <x-status-alert :status="session('status')" />

            {{-- Filtro por rango de fechas (default: mes actual). --}}
            <form method="GET" action="{{ route('admin.produccion.soplador', $soplador) }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="desde" value="Desde" />
                    <x-text-input id="desde" name="desde" type="date" class="mt-1.5" :value="$desde" />
                </div>
                <div class="flex-1">
                    <x-input-label for="hasta" value="Hasta" />
                    <x-text-input id="hasta" name="hasta" type="date" class="mt-1.5" :value="$hasta" />
                </div>
                <div>
                    <x-primary-button>Filtrar</x-primary-button>
                </div>
            </form>

            {{-- Totales del período. "Producido" es vendible (1ª+2ª); la merma
                 (malos+dañadas) se muestra aparte para no inflar la productividad. --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @php
                    $cards = [
                        ['Asignadas', number_format($totales['asignadas'], 0, ',', '.')],
                        ['Producido (1ª+2ª)', number_format($totales['producido'], 0, ',', '.')],
                        ['Merma', number_format($totales['merma'], 0, ',', '.')],
                        ['Reportes', $totales['reportes']],
                    ];
                @endphp
                @foreach ($cards as [$label, $valor])
                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ $valor }}</p>
                    </div>
                @endforeach
            </div>

            <x-list-card title="Días" :count="$reportes->count()" :countLabel="\Illuminate\Support\Str::plural('día', $reportes->count())">
                @forelse ($reportes as $reporte)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ \Illuminate\Support\Carbon::parse($reporte->fecha)->format('d') }}</x-avatar>
                        </x-slot>

                        <p class="truncate font-medium text-neutral-900">
                            {{ \Illuminate\Support\Carbon::parse($reporte->fecha)->format('d-m-Y') }}
                        </p>
                        <p class="truncate text-sm text-neutral-500">
                            Turno {{ $reporte->turno }} · asignadas {{ number_format($reporte->asignadas, 0, ',', '.') }}
                        </p>

                        <x-slot name="meta">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
                                <span><span class="text-neutral-400">1ª</span> {{ $reporte->primera }}</span>
                                <span><span class="text-neutral-400">2ª</span> {{ $reporte->segunda }}</span>
                                <span><span class="text-neutral-400">Malos</span> {{ $reporte->malo }}</span>
                                <span><span class="text-neutral-400">Dañadas</span> {{ $reporte->danada }}</span>
                                <span class="font-medium {{ $reporte->diferencia === 0 ? 'text-emerald-600' : 'text-amber-600' }}">
                                    Δ {{ $reporte->diferencia }}
                                </span>
                                <x-produccion.estado-badge :estado="$reporte->estado" />
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <a href="{{ route('admin.produccion.reporte.show', $reporte) }}"
                               class="whitespace-nowrap text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">
                                Ver / editar
                            </a>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-neutral-500">
                        Sin reportes en este rango de fechas.
                    </li>
                @endforelse
            </x-list-card>
        </div>
    </div>
</x-app-layout>
