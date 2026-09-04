{{-- Tabla por día con mini-barras. $tendencia = ['dias','totales','maxProducido','modoBarra'].
     $linkDia (bool, opcional) enlaza cada día a su detalle (produccion.dia).

     La barra tiene DOS significados y la vista dice cuál (dueño 03-09: «no me
     queda claro qué significan esas barras»):
       · modoBarra = avance   → avance sobre lo asignado del día (100 % = cumplió;
         el «+» marca que se pasó). Es el panel del jefe, que sí tiene metas.
       · modoBarra = relativo → producido respecto del mejor día del período (los
         desgloses por máquina/tipo no tienen meta diaria). --}}
@php $modoAvance = ($tendencia['modoBarra'] ?? 'relativo') === 'avance'; @endphp
<ul class="divide-y divide-neutral-100">
    @foreach ($tendencia['dias'] as $d)
        @php
            $etiqueta = ucfirst($d['fecha']->translatedFormat('D d/m'));
            if ($modoAvance) {
                $sinMeta = ($d['asignadas'] ?? 0) === 0;
                $pct = $sinMeta ? 0 : min(100, $d['avance']);
            } else {
                $sinMeta = false;
                $pct = (int) round($d['producido'] / $tendencia['maxProducido'] * 100);
            }
        @endphp
        <li class="px-4 py-3 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                @if ($linkDia ?? false)
                    <a href="{{ route('admin.produccion.dia', ['fecha' => $d['fecha']->toDateString()]) }}"
                       class="text-sm font-medium text-neutral-900 transition duration-150 hover:text-brand-600">{{ $etiqueta }}</a>
                @else
                    <span class="text-sm font-medium text-neutral-900">{{ $etiqueta }}</span>
                @endif
                <div class="flex flex-wrap items-center gap-4 text-sm text-neutral-600">
                    @if ($modoAvance)
                        <x-produccion.metrica label="Avance" w="w-28" tone="brand">{{ $sinMeta ? '—' : ($d['avance'] > 100 ? '+' : '').$d['avance'].'%' }}</x-produccion.metrica>
                    @endif
                    <x-produccion.metrica label="Producido" w="w-28" :tone="$modoAvance ? null : 'brand'">{{ number_format($d['producido'], 0, ',', '.') }}</x-produccion.metrica>
                    <x-produccion.metrica label="Merma" w="w-28" tone="muted">{{ $d['merma'] }} ({{ $d['merma_pct'] }}%)</x-produccion.metrica>
                    <x-produccion.metrica label="1ª" w="w-16">{{ $d['tasa1'] }}%</x-produccion.metrica>
                </div>
            </div>
            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-neutral-200">
                <div class="h-full rounded-full {{ $sinMeta ? 'bg-neutral-300' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div>
            </div>
        </li>
    @endforeach
</ul>
{{-- El rótulo deriva del MISMO modo que dibuja la barra (los textos gemelos driftean). --}}
<p class="px-4 py-2 text-xs text-neutral-400 sm:px-6">
    @if ($modoAvance)
        Barra: avance del día sobre lo asignado (llena = cumplió; «+» = se pasó; gris = sin asignación).
    @else
        Barra: producido respecto del mejor día del período.
    @endif
</p>
