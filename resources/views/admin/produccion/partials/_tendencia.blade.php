{{-- Tabla por día con mini-barras. $tendencia = ['dias','totales','maxProducido'].
     $linkDia (bool, opcional) enlaza cada día a su detalle (produccion.dia). --}}
<ul class="divide-y divide-neutral-100">
    @foreach ($tendencia['dias'] as $d)
        @php
            $pct = (int) round($d['producido'] / $tendencia['maxProducido'] * 100);
            $etiqueta = ucfirst($d['fecha']->translatedFormat('D d/m'));
        @endphp
        <li class="px-4 py-3 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                @if ($linkDia ?? false)
                    <a href="{{ route('admin.produccion.dia', ['fecha' => $d['fecha']->toDateString()]) }}"
                       class="text-sm font-medium text-neutral-900 transition duration-150 hover:text-brand-600">{{ $etiqueta }}</a>
                @else
                    <span class="text-sm font-medium text-neutral-900">{{ $etiqueta }}</span>
                @endif
                <div class="flex items-center gap-4 text-sm text-neutral-600">
                    <x-produccion.metrica label="Producido" w="w-28" tone="brand">{{ number_format($d['producido'], 0, ',', '.') }}</x-produccion.metrica>
                    <x-produccion.metrica label="Merma" w="w-28" tone="muted">{{ $d['merma'] }} ({{ $d['merma_pct'] }}%)</x-produccion.metrica>
                    <x-produccion.metrica label="1ª" w="w-16">{{ $d['tasa1'] }}%</x-produccion.metrica>
                </div>
            </div>
            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-neutral-200">
                <div class="h-full rounded-full bg-brand-500" style="width: {{ $pct }}%"></div>
            </div>
        </li>
    @endforeach
</ul>
