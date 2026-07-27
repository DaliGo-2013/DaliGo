{{--
    Línea de tiempo vertical de las etapas de una reparación (estilo Blue Express).
    Reutilizable: hoy la usa el boceto de seguimiento; a futuro, la vista real del
    cliente le pasará el índice actual desde el estado de la orden.

    Props:
      - pasos:  array ordenado de ['key','label','desc','tono'] (tono: brand|danger).
      - curVar: nombre de la variable Alpine (del scope padre) con el índice de la
                etapa ACTUAL. El componente compara el índice de cada paso contra
                ella para pintar completado / actual / pendiente.
--}}
@props(['pasos', 'curVar' => 'i'])

@php
    // Iconos por etapa (heroicons outline inline; no dependemos del set x-icon).
    $svg = fn (string $d) => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'" /></svg>';
    $iconos = [
        'recibido' => $svg('M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z'),
        'en_revision' => $svg('m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z'),
        'cotizacion' => $svg('M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'),
        'esperando_repuesto' => $svg('M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'),
        'reparado' => $svg('M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z'),
        'entregado' => $svg('M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'),
        'sin_solucion' => $svg('m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'),
    ];
@endphp

<ol class="relative">
    @foreach ($pasos as $n => $paso)
        @php
            $danger = ($paso['tono'] ?? 'brand') === 'danger';
            // Clases del círculo según estado (actual / completado / pendiente).
            $curCircle = $danger ? 'bg-red-600 text-white ring-4 ring-red-100' : 'bg-brand-600 text-white ring-4 ring-brand-100';
            $doneCircle = $danger ? 'bg-red-100 text-red-600' : 'bg-brand-100 text-brand-600';
            $pendCircle = 'bg-neutral-100 text-neutral-400';
        @endphp
        <li class="relative flex gap-4 pb-8 last:pb-0">
            {{-- Riel vertical (relleno hasta la etapa actual). No en el último. --}}
            @unless ($loop->last)
                <span class="absolute left-5 top-11 -ml-px h-[calc(100%-1.75rem)] w-0.5"
                      :class="{{ $n }} < {{ $curVar }} ? 'bg-brand-500' : 'bg-neutral-200'"></span>
            @endunless

            {{-- Nodo con icono --}}
            <span class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition-colors duration-200"
                  :class="{{ $n }} === {{ $curVar }} ? '{{ $curCircle }}' : ({{ $n }} < {{ $curVar }} ? '{{ $doneCircle }}' : '{{ $pendCircle }}')">
                {!! $iconos[$paso['key']] ?? $iconos['recibido'] !!}
            </span>

            {{-- Texto de la etapa --}}
            <div class="min-w-0 pt-1.5">
                <p class="text-sm transition-colors"
                   :class="{{ $n }} === {{ $curVar }} ? 'font-semibold text-neutral-900' : ({{ $n }} < {{ $curVar }} ? 'font-medium text-neutral-700' : 'text-neutral-400')">
                    {{ $paso['label'] }}
                </p>
                @if (! empty($paso['desc']))
                    <p class="mt-0.5 text-xs leading-snug text-neutral-500" x-show="{{ $n }} === {{ $curVar }}" x-cloak>{{ $paso['desc'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
