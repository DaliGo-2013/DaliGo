{{-- Dos columnas «Completado» / «Por completar» de un panel de detalle del
     plan (las usan los paneles del Gantt y los bloques extra del repo).
     Espera: $hecho = string[] · $falta = string[]. Punto lleno neutral-800
     para lo hecho, brand-600 para lo pendiente (paleta de 4, por relleno). --}}
<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <div>
        <h4 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Completado</h4>
        @if ($hecho === [])
            <p class="mt-2 text-sm text-neutral-400">— nada aún</p>
        @else
            <ul class="mt-2 space-y-1.5">
                @foreach ($hecho as $item)
                    <li class="flex gap-2 text-sm text-neutral-700">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-neutral-800" aria-hidden="true"></span>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <div>
        <h4 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por completar</h4>
        @if ($falta === [])
            <p class="mt-2 text-sm text-neutral-400">— nada pendiente</p>
        @else
            <ul class="mt-2 space-y-1.5">
                @foreach ($falta as $item)
                    <li class="flex gap-2 text-sm text-neutral-700">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600" aria-hidden="true"></span>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
