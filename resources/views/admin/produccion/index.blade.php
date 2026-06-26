<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Producción" subtitle="Asignaciones y revisión de reportes del día.">
            <x-slot name="action">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <x-secondary-link :href="route('admin.produccion.sopladores')">Sopladores</x-secondary-link>
                    <x-secondary-link :href="route('admin.maquinas.index')">Máquinas</x-secondary-link>
                    <x-secondary-link :href="route('admin.tipos-botellon.index')">Tipos de botellón</x-secondary-link>
                    <x-button-link :href="route('admin.produccion.asignar')">
                        <x-icon.plus class="h-4 w-4" />
                        Asignar
                    </x-button-link>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            {{-- Resumen del día --}}
            <div class="dg-enter mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @php
                    $chips = [
                        ['Sopladores', $resumen['sopladores']],
                        ['Asignadas', number_format($resumen['asignadas'], 0, ',', '.')],
                        ['Pendientes de aprobar', $resumen['pendientes']],
                        ['Aprobados', $resumen['aprobados']],
                    ];
                @endphp
                @foreach ($chips as [$label, $valor])
                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ $valor }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Producción del día por máquina (incluye reportes sin aprobar) --}}
            @if ($porMaquina->isNotEmpty())
                <div class="dg-enter mb-6 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por máquina · hoy</h3>
                        <span class="text-xs font-medium text-neutral-400">incluye reportes sin aprobar</span>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($porMaquina as $fila)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 sm:px-6">
                                <p class="text-sm font-medium text-neutral-900">{{ $fila->maquina ?? 'Sin máquina' }}</p>
                                <div class="flex items-center gap-4 text-sm text-neutral-600">
                                    <span><span class="text-neutral-400">1ª</span> {{ number_format($fila->primera, 0, ',', '.') }}</span>
                                    <span><span class="text-neutral-400">2ª</span> {{ number_format($fila->segunda, 0, ',', '.') }}</span>
                                    <span><span class="text-neutral-400">Malos</span> {{ number_format($fila->malo, 0, ',', '.') }}</span>
                                    <span><span class="text-neutral-400">Dañadas</span> {{ number_format($fila->danada, 0, ',', '.') }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-list-card title="Cola de reportes" :count="$reportes->count()" :countLabel="\Illuminate\Support\Str::plural('reporte', $reportes->count())">
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
                                Revisar
                            </a>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-neutral-500">
                        No hay reportes para hoy. Usa <span class="font-medium text-neutral-700">Asignar</span> para empezar.
                    </li>
                @endforelse
            </x-list-card>
        </div>
    </div>
</x-app-layout>
