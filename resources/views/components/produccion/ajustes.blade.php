@props(['reporte', 'compacto' => false])

{{-- Cambios del jefe sobre el reporte (dueño 09-09): quién, cuándo, qué ítem
     y cuánto (antes → ahora), con el motivo. Requiere $reporte->ajustes con
     `responsable` (y `autorizadoPor`) cargados. Un ajuste de varios campos es
     UNA entrada (se agrupa por aprobación). `compacto` = una línea por ajuste,
     para la fila del historial; sin motivo, que va en el detalle. --}}
@php
    $grupos = $reporte->ajustes
        ->sortByDesc('id')
        ->groupBy(fn ($a) => $a->aprobacion_id ?? 'i'.$a->id.$a->created_at?->timestamp);
@endphp

@if ($grupos->isNotEmpty())
    @if ($compacto)
        <ul class="mt-1 space-y-0.5 text-xs text-neutral-600" data-ajustes>
            @foreach ($grupos as $grupo)
                @php $primero = $grupo->first(); @endphp
                <li>
                    <span class="font-medium text-neutral-700">{{ $primero->responsable?->name ?? 'El jefe' }}</span> cambió
                    {!! $grupo->sortBy('id')->map(fn ($a) => e($a->etiqueta).' <span class="tabular-nums">'.number_format($a->antes, 0, ',', '.').' → <span class="font-semibold text-neutral-900">'.number_format($a->despues, 0, ',', '.').'</span></span>')->implode(' · ') !!}
                </li>
            @endforeach
        </ul>
    @else
        <ul class="divide-y divide-neutral-100" data-ajustes>
            @foreach ($grupos as $grupo)
                @php $primero = $grupo->first(); @endphp
                <li class="px-4 py-3 sm:px-6">
                    <p class="text-sm text-neutral-900">
                        <span class="font-medium">{{ $primero->responsable?->name ?? 'El jefe' }}</span>
                        <span class="text-neutral-500">· {{ $primero->created_at?->enChile()->format('d-m H:i') }}</span>
                        @if ($primero->autorizadoPor)
                            <span class="text-neutral-500">· autorizó {{ $primero->autorizadoPor->name }}</span>
                        @endif
                    </p>
                    <p class="mt-1 text-sm tabular-nums text-neutral-700">
                        {!! $grupo->sortBy('id')->map(fn ($a) => e($a->etiqueta).': '.number_format($a->antes, 0, ',', '.').' → <span class="font-semibold text-neutral-900">'.number_format($a->despues, 0, ',', '.').'</span>')->implode(' · ') !!}
                    </p>
                    @if ($primero->motivo)
                        <p class="mt-1 text-xs text-neutral-500">Motivo: {{ $primero->motivo }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endif
