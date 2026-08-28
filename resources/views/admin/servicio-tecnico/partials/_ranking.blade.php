{{-- Ranking simple del informe: $items = colección con nombre + cantidad
     (opcional sku). $vacio = texto cuando no hay datos. Cada fila lleva una
     barra proporcional al mayor (patrón de _desglose de Producción).
     $totalPeriodo (opcional): si viene > 0, la cifra se muestra en PORCENTAJE
     del total del período (con la cantidad en gris al lado).

     $detalles (opcional): mapa {clave => colección de AgendaTrabajo}. Si viene, y
     el ítem trae `clave`, la fila se vuelve DESPLEGABLE y muestra el historial de
     ese ítem. Es opcional a propósito: los otros dos rankings de la página (por
     tipo, servicios más usados) comparten este partial y siguen siendo filas de
     solo lectura, sin tocarlos. --}}
@php
    $max = max(1, (int) ($items->max('cantidad') ?? 0));
    $totalPct = (int) ($totalPeriodo ?? 0);
    $detalles = $detalles ?? null;
@endphp
<ul class="divide-y divide-neutral-100" @if ($detalles) x-data="{ abierto: null }" @endif>
    @forelse ($items as $it)
        @php
            // Desplegable solo si hay detalle PARA ESTE ítem: una fila que se abre
            // en un panel vacío es peor que una fila que no se abre.
            $clave = $it->clave ?? null;
            $historial = $detalles && $clave !== null ? ($detalles[$clave] ?? null) : null;
            $desplegable = $historial !== null && $historial->isNotEmpty();
            // Tag calculado: `button` cuando se despliega (foco y teclado gratis),
            // `div` cuando no. Un `<div>` con click no lo alcanza el teclado, y un
            // `<button disabled>` mentiría sobre una fila que sí es informativa.
            $tag = $desplegable ? 'button' : 'div';
        @endphp
        <li @class(['transition', 'hover:bg-neutral-50' => $desplegable])>
            {{-- La fila entera es el disparador cuando hay historial: en el
                 celular, un objetivo del ancho de la fila se toca sin apuntar. --}}
            <{{ $tag }}
                @if ($desplegable)
                    type="button"
                    x-on:click="abierto = (abierto === @js($clave) ? null : @js($clave))"
                    x-bind:aria-expanded="abierto === @js($clave) ? 'true' : 'false'"
                @endif
                class="block w-full px-4 py-3 text-left sm:px-6">
                <div class="flex items-center justify-between gap-4">
                    <span class="flex min-w-0 items-center gap-1.5">
                        @if ($desplegable)
                            {{-- El x-bind va en un <span>, NO en el `<x-icon.*>`: dentro
                                 de un atributo de COMPONENTE Blade, `@js()` no se compila
                                 (llega el texto literal «@js($clave)») porque Blade solo
                                 evalúa PHP en los atributos con prefijo `:`. El chevron
                                 quedaba sin rotar y ningún test lo veía — se cazó midiendo
                                 `rotate` en el navegador. --}}
                            <span class="shrink-0 text-neutral-400 transition-transform duration-150"
                                  x-bind:class="abierto === @js($clave) ? 'rotate-90' : ''">
                                <x-icon.chevron-right class="h-4 w-4" />
                            </span>
                        @endif
                        <span class="min-w-0 truncate text-sm font-medium text-neutral-900">
                            {{ $it->nombre ?? ($sinNombre ?? 'Sin dato') }}
                            @if (! empty($it->sku))
                                <span class="font-normal text-neutral-400">· {{ $it->sku }}</span>
                            @endif
                        </span>
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
            </{{ $tag }}>

            @if ($desplegable)
                {{-- Sin x-transition: en este proyecto un `x-show` con transición ya
                     dejó paneles pegados a medio camino (bitácora 2026-07-22). El
                     `x-cloak` evita el flash antes de que Alpine arranque. --}}
                <div x-show="abierto === @js($clave)" x-cloak class="border-t border-neutral-100 bg-neutral-50">
                    @include('admin.servicio-tecnico.partials._trabajos-detalle', [
                        'trabajos' => $historial,
                        'modo' => 'historial',
                    ])
                </div>
            @endif
        </li>
    @empty
        <li class="px-4 py-6 text-center text-sm text-neutral-500 sm:px-6">{{ $vacio ?? 'Sin datos en el período.' }}</li>
    @endforelse
</ul>
