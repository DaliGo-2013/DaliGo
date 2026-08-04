<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header title="Plan del proyecto"
            subtitle="Carta Gantt del avance real: se alimenta sola del tracker del repo en cada deploy" />
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="space-y-6">
            <x-status-alert :status="session('status')" />

            {{-- Medidor de avance global + procedencia de los datos --}}
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Avance global</p>
                        <p class="mt-1 text-4xl font-semibold tabular-nums text-neutral-900">{{ $avanceGlobal }}<span class="text-xl font-medium text-neutral-500">%</span></p>
                        @if ($totalPeso)
                            <p class="mt-1 text-xs text-neutral-500">Sobre una base de {{ $totalPeso }} puntos ponderados por esfuerzo (RUTA-MAESTRA §10)</p>
                        @endif
                    </div>
                    <div class="text-xs text-neutral-500 sm:text-end">
                        <p>Plan actualizado: <span class="font-medium tabular-nums text-neutral-700">{{ $planActualizado->enChile()->format('d-m-Y H:i') }}</span></p>
                        <p class="mt-1 flex gap-4 sm:justify-end">
                            <a href="https://github.com/DaliGo-2013/DaliGo/blob/main/docs/RUTA-MAESTRA.md" target="_blank" rel="noopener"
                               class="font-medium text-brand-700 transition duration-150 hover:text-brand-600">Ver RUTA-MAESTRA</a>
                            <a href="https://github.com/DaliGo-2013/DaliGo/actions" target="_blank" rel="noopener"
                               class="font-medium text-brand-700 transition duration-150 hover:text-brand-600">Estado de la CI</a>
                        </p>
                        {{-- El Excel se GENERA al momento desde la misma fuente que
                             esta página: la carta que circula en las reuniones sale
                             siempre al día, sin mantener el archivo a mano. --}}
                        <a href="{{ route('plan.excel') }}"
                           class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.99]">
                            <x-icon.document-text class="h-4 w-4" />
                            Descargar Excel (al día)
                        </a>
                    </div>
                </div>
                <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-neutral-100">
                    <div class="h-full rounded-full bg-brand-600" style="width: {{ $avanceGlobal }}%"></div>
                </div>
            </div>

            @include('plan.partials._gantt')
            @include('plan.partials._hitos')
            @include('plan.partials._extras')
            @include('plan.partials._decisiones')
        </div>
    </div>
</x-app-layout>
