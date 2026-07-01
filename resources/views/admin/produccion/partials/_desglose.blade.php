{{-- Lista "por X": $items = colección de objetos con id/nombre/producido/merma/merma_pct/tasa1.
     Opcionales: $linkRoute + $linkKey (+ $linkExtra query) para enlazar cada fila a su detalle;
     $sinNombre = etiqueta para id nulo (ej. "Sin máquina"). --}}
@php $max = max(1, $items->max('producido') ?? 0); @endphp
<ul class="divide-y divide-neutral-100">
    @forelse ($items as $it)
        @php
            $url = (($linkRoute ?? null) && $it->id) ? route($linkRoute, array_merge([$linkKey => $it->id], $linkExtra ?? [])) : null;
            $pct = (int) round($it->producido / $max * 100);
            $nombre = $it->nombre ?? ($sinNombre ?? 'Sin asignar');
        @endphp
        <li class="px-4 py-3 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                @if ($url)
                    <a href="{{ $url }}" class="text-sm font-medium text-neutral-900 transition duration-150 hover:text-brand-600">{{ $nombre }}</a>
                @else
                    <span class="text-sm font-medium text-neutral-900">{{ $nombre }}</span>
                @endif
                <div class="flex items-center gap-4 text-sm text-neutral-600">
                    <x-produccion.metrica label="Producido" w="w-14" tone="brand">{{ number_format($it->producido, 0, ',', '.') }}</x-produccion.metrica>
                    <x-produccion.metrica label="Merma" w="w-20" tone="muted">{{ $it->merma }} · {{ $it->merma_pct }}%</x-produccion.metrica>
                    <x-produccion.metrica label="1ª" w="w-10">{{ $it->tasa1 }}%</x-produccion.metrica>
                </div>
            </div>
            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
                <div class="h-full rounded-full bg-brand-500" style="width: {{ $pct }}%"></div>
            </div>
        </li>
    @empty
        <li class="px-4 py-6 text-center text-sm text-neutral-500 sm:px-6">Sin datos en el periodo.</li>
    @endforelse
</ul>
