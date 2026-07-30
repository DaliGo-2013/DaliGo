{{-- Carta Gantt por módulo: la ventana (tinte claro) son las fechas planificadas
     de App\Support\PlanProyecto::MODULOS; el relleno sólido es el % real del
     tracker §10. Posiciones con style inline (left/width en % del span total) —
     Tailwind purga anchos dinámicos, mismo idioma que las mini-barras de
     _tendencia. El scroll horizontal vive DENTRO de este contenedor
     (overflow-x-auto + min-w interno), nunca en la página. --}}
@php
    // Paleta ESTRICTA de 4: los 3 estados se expresan con relleno y tono,
    // sin verde (doctrina de badges: en curso = brand, final = neutral-800).
    $tinte = [
        'no_iniciada' => 'bg-neutral-100 ring-1 ring-inset ring-neutral-200',
        'en_curso' => 'bg-brand-100',
        'finalizada' => 'bg-neutral-300',
    ];
    $relleno = [
        'no_iniciada' => 'bg-neutral-200',
        'en_curso' => 'bg-brand-600',
        'finalizada' => 'bg-neutral-800',
    ];
@endphp

<div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-neutral-100 px-6 py-3">
        <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Carta Gantt · may 2026 → feb 2027</h2>
        <div class="flex items-center gap-4 text-xs text-neutral-600">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-neutral-200 ring-1 ring-inset ring-neutral-300"></span>No iniciada</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-brand-600"></span>En curso</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-neutral-800"></span>Finalizada</span>
        </div>
    </div>

    <div class="overflow-x-auto px-6 py-4">
        <div class="min-w-[640px]">
            {{-- Eje de meses --}}
            <div class="flex items-center">
                <div class="w-44 shrink-0 sm:w-52"></div>
                <div class="relative h-4 flex-1">
                    @foreach ($meses as $mes)
                        <span class="absolute top-0 text-[10px] font-medium uppercase text-neutral-400" style="left: {{ $mes['left'] }}%">{{ $mes['label'] }}</span>
                    @endforeach
                </div>
                <div class="w-12 shrink-0"></div>
            </div>

            <div class="mt-2 space-y-1.5">
                @foreach ($gantt as $fila)
                    <div class="flex items-center">
                        <div class="w-44 shrink-0 pe-3 sm:w-52">
                            <p class="truncate text-sm text-neutral-700"
                               title="{{ $fila['label'] }}{{ $fila['fundamento'] !== '' ? ' — ' . $fila['fundamento'] : '' }}">{{ $fila['label'] }}</p>
                        </div>
                        <div class="relative h-5 flex-1 rounded bg-neutral-50">
                            {{-- Guías de mes --}}
                            @foreach ($meses as $mes)
                                <span class="absolute inset-y-0 border-s border-neutral-100" style="left: {{ $mes['left'] }}%" aria-hidden="true"></span>
                            @endforeach
                            {{-- Marcador de hoy (día de negocio) --}}
                            @if (! is_null($hoyPct))
                                <span class="absolute inset-y-0 z-10 border-s-2 border-brand-600/60" style="left: {{ $hoyPct }}%" aria-hidden="true"></span>
                            @endif
                            {{-- Barra: ventana planificada + relleno de avance --}}
                            <div class="absolute inset-y-0.5 overflow-hidden rounded-full {{ $tinte[$fila['estado']] }}"
                                 style="left: {{ $fila['left'] }}%; width: {{ $fila['width'] }}%">
                                <div class="h-full rounded-full {{ $relleno[$fila['estado']] }}" style="width: {{ $fila['pct'] }}%"></div>
                            </div>
                        </div>
                        <div class="w-12 shrink-0 text-end text-xs font-medium tabular-nums {{ $fila['estado'] === 'no_iniciada' ? 'text-neutral-400' : 'text-neutral-700' }}">{{ $fila['pct'] }}%</div>
                    </div>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-neutral-400">La línea naranja marca hoy. Ventana clara = fechas planificadas · relleno sólido = avance real del tracker. Pasa el cursor por un módulo para ver el fundamento de su %.</p>
        </div>
    </div>
</div>
