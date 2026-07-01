<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$tipo->nombre"
                       subtitle="Producción del tipo de botellón"
                       :back="route('admin.produccion.index')" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            <form method="GET" action="{{ route('admin.produccion.tipo', $tipo) }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1"><x-input-label for="desde" value="Desde" /><x-text-input id="desde" name="desde" type="date" class="mt-1.5" :value="$desde" /></div>
                <div class="flex-1"><x-input-label for="hasta" value="Hasta" /><x-text-input id="hasta" name="hasta" type="date" class="mt-1.5" :value="$hasta" /></div>
                <div><x-primary-button>Filtrar</x-primary-button></div>
            </form>

            @include('admin.produccion.partials._totales', ['chips' => [
                ['label' => 'Producido', 'valor' => number_format($tendencia['totales']['producido'], 0, ',', '.'), 'tono' => 'brand'],
                ['label' => 'Merma', 'valor' => number_format($tendencia['totales']['merma'], 0, ',', '.').' ('.$tendencia['totales']['merma_pct'].'%)', 'tono' => 'muted'],
                ['label' => 'Tasa 1ª', 'valor' => $tendencia['totales']['tasa1'].'%', 'tono' => null],
                ['label' => 'Reportes', 'valor' => $tendencia['totales']['reportes'], 'tono' => null],
            ]])

            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Producción por día {{ $esDefault ? '· últimos 30 días' : '' }}</h3>
                </div>
                @include('admin.produccion.partials._tendencia', ['tendencia' => $tendencia, 'linkDia' => true])
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="border-b border-neutral-100 px-4 py-3 sm:px-6"><h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por máquina</h3></div>
                    @include('admin.produccion.partials._desglose', [
                        'items' => $porMaquina,
                        'linkRoute' => 'admin.produccion.maquina', 'linkKey' => 'maquina',
                        'linkExtra' => ['desde' => $desde, 'hasta' => $hasta], 'sinNombre' => 'Sin máquina',
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
    </div>
</x-app-layout>
