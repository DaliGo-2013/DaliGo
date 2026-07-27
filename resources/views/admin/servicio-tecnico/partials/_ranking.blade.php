{{-- Ranking simple del informe: $items = colección con nombre + cantidad
     (opcional sku). $vacio = texto cuando no hay datos. Cada fila lleva una
     barra proporcional al mayor (patrón de _desglose de Producción).
     $totalPeriodo (opcional): si viene > 0, la cifra se muestra en PORCENTAJE
     del total del período (con la cantidad en gris al lado). --}}
@php
    $max = max(1, (int) ($items->max('cantidad') ?? 0));
    $totalPct = (int) ($totalPeriodo ?? 0);
@endphp
<ul class="divide-y divide-neutral-100">
    @forelse ($items as $it)
        <li class="px-4 py-3 sm:px-6">
            <div class="flex items-center justify-between gap-4">
                <span class="min-w-0 truncate text-sm font-medium text-neutral-900">
                    {{ $it->nombre ?? ($sinNombre ?? 'Sin dato') }}
                    @if (! empty($it->sku))
                        <span class="font-normal text-neutral-400">· {{ $it->sku }}</span>
                    @endif
                </span>
                <span class="shrink-0 text-sm font-semibold text-neutral-700">
                    @if ($totalPct > 0)
                        {{ round($it->cantidad / $totalPct * 100) }}%<span class="ml-1 font-normal text-neutral-400">· {{ number_format($it->cantidad, 0, ',', '.') }}</span>
                    @else
                        {{ number_format($it->cantidad, 0, ',', '.') }}
                    @endif
                </span>
            </div>
            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-neutral-200">
                <div class="h-full rounded-full bg-brand-500" style="width: {{ (int) round($it->cantidad / $max * 100) }}%"></div>
            </div>
        </li>
    @empty
        <li class="px-4 py-6 text-center text-sm text-neutral-500 sm:px-6">{{ $vacio ?? 'Sin datos en el período.' }}</li>
    @endforelse
</ul>
