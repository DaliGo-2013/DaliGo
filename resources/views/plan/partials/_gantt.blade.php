{{-- Carta Gantt por módulo: la ventana (tinte claro) son las fechas planificadas
     de App\Support\PlanProyecto::MODULOS; el relleno sólido es el % real del
     tracker §10. Posiciones con style inline (left/width en % del span total) —
     Tailwind purga anchos dinámicos, mismo idioma que las mini-barras de
     _tendencia. El scroll horizontal vive DENTRO de este contenedor
     (overflow-x-auto), nunca en la página.

     Cada FILA es un botón: al tocarla se abre su panel de detalle (hecho /
     por completar) DEBAJO del dibujo — fuera del overflow-x-auto a propósito,
     para que en móvil el panel use el ancho completo del card y no los 640px
     del dibujo. `sel` único = single-open gratis; sin x-transition (gotcha
     bitácora [2026-07-22]: un x-show con transición puede quedar pegado). --}}
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
    $badgeEstado = [
        'no_iniciada' => 'bg-neutral-100 text-neutral-500 ring-1 ring-inset ring-neutral-200',
        'en_curso' => 'bg-brand-600 text-white',
        'finalizada' => 'bg-neutral-800 text-white',
    ];
    $labelEstado = ['no_iniciada' => 'No iniciada', 'en_curso' => 'En curso', 'finalizada' => 'Finalizada'];
@endphp

<div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm" x-data="{ sel: null }">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-neutral-100 px-6 py-3">
        <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Carta Gantt · may 2026 → feb 2027 · <span class="text-brand-700">Hoy: {{ $hoyFecha['completa'] }}</span></h2>
        <div class="flex items-center gap-4 text-xs text-neutral-600">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-neutral-200 ring-1 ring-inset ring-neutral-300"></span>No iniciada</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-brand-600"></span>En curso</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-neutral-800"></span>Finalizada</span>
        </div>
    </div>

    <div class="overflow-x-auto px-3 py-4 sm:px-6">
        <div class="min-w-[640px]">
            {{-- Eje de meses + chip de HOY sobre la línea. El chip se clampa
                 (4–96%) para no recortarse en los bordes; la LÍNEA de las
                 filas queda en el % exacto. El -translate-x-1/2 es una clase
                 estática sin Alpine — el gotcha [2026-07-26] del translate
                 aplica a estilos inline puestos por JS, no a esto. --}}
            <div class="flex items-center">
                <div class="w-44 shrink-0 sm:w-52"></div>
                {{-- h-9: el chip (arriba) y los meses (abajo) no se pisan. --}}
                <div class="relative h-9 flex-1">
                    @foreach ($meses as $mes)
                        <span class="absolute bottom-0 text-[10px] font-medium uppercase text-neutral-400" style="left: {{ $mes['left'] }}%">{{ $mes['label'] }}</span>
                    @endforeach
                    @if (! is_null($hoyPct))
                        <span class="absolute top-0 z-10 inline-flex -translate-x-1/2 items-center whitespace-nowrap rounded-full bg-brand-600 px-1.5 text-[10px] font-semibold leading-4 text-white"
                              style="left: {{ min(max($hoyPct, 4), 96) }}%">Hoy · {{ $hoyFecha['corta'] }}</span>
                    @endif
                </div>
                <div class="w-12 shrink-0"></div>
            </div>

            <div class="mt-2 space-y-0.5">
                @foreach ($gantt as $fila)
                    {{-- Toda la fila es un botón: abre/cierra su panel de
                         detalle bajo el dibujo. aria-controls apunta al panel. --}}
                    <button type="button"
                            @click="sel = sel === '{{ $fila['key'] }}' ? null : '{{ $fila['key'] }}'"
                            :aria-expanded="sel === '{{ $fila['key'] }}' ? 'true' : 'false'"
                            aria-controls="plan-detalle-{{ $fila['key'] }}"
                            :class="sel === '{{ $fila['key'] }}' ? 'bg-brand-50' : 'hover:bg-neutral-50'"
                            class="flex w-full items-center rounded-lg py-1 text-left transition duration-150 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                        <span class="w-44 shrink-0 pe-3 sm:w-52">
                            <span class="block truncate text-sm text-neutral-700">{{ $fila['label'] }}</span>
                        </span>
                        <span class="relative block h-5 flex-1 rounded bg-neutral-50">
                            {{-- Guías de mes --}}
                            @foreach ($meses as $mes)
                                <span class="absolute inset-y-0 border-s border-neutral-100" style="left: {{ $mes['left'] }}%" aria-hidden="true"></span>
                            @endforeach
                            {{-- Marcador de hoy (día de negocio) --}}
                            @if (! is_null($hoyPct))
                                <span class="absolute inset-y-0 z-10 border-s-2 border-brand-600" style="left: {{ $hoyPct }}%" aria-hidden="true"></span>
                            @endif
                            {{-- Barra: ventana planificada + relleno de avance --}}
                            <span class="absolute inset-y-0.5 block overflow-hidden rounded-full {{ $tinte[$fila['estado']] }}"
                                  style="left: {{ $fila['left'] }}%; width: {{ $fila['width'] }}%">
                                <span class="block h-full rounded-full {{ $relleno[$fila['estado']] }}" style="width: {{ $fila['pct'] }}%"></span>
                            </span>
                        </span>
                        <span class="w-12 shrink-0 text-end text-xs font-medium tabular-nums {{ $fila['estado'] === 'no_iniciada' ? 'text-neutral-400' : 'text-neutral-700' }}">{{ $fila['pct'] }}%</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Paneles de detalle (uno por módulo, server-rendered; abre el del
         módulo tocado). Fuera del overflow-x-auto: en móvil usan el ancho
         completo del card. --}}
    @foreach ($gantt as $fila)
        <div id="plan-detalle-{{ $fila['key'] }}" x-show="sel === '{{ $fila['key'] }}'" x-cloak
             class="border-t border-neutral-100 px-4 py-4 sm:px-6">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <h3 class="text-sm font-semibold text-neutral-900">{{ $fila['label'] }}</h3>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeEstado[$fila['estado']] }}">{{ $labelEstado[$fila['estado']] }}</span>
                <span class="text-xs tabular-nums text-neutral-500">
                    {{ $fila['pct'] }}% · peso {{ $fila['peso'] }} pts · {{ $fila['desde']->format('d-m-Y') }} → {{ $fila['hasta']->format('d-m-Y') }}
                </span>
            </div>

            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-neutral-100">
                <div class="h-full rounded-full {{ $relleno[$fila['estado']] }}" style="width: {{ $fila['pct'] }}%"></div>
            </div>

            @if ($fila['fundamento'] !== '')
                <p class="mt-3 text-xs text-neutral-500"><span class="font-medium text-neutral-600">Fundamento del %:</span> {{ $fila['fundamento'] }}</p>
            @endif

            @include('plan.partials._columnas-detalle', ['hecho' => $fila['hecho'], 'falta' => $fila['falta']])

            <p class="mt-4 text-xs">
                <a href="https://github.com/DaliGo-2013/DaliGo/blob/main/docs/RUTA-MAESTRA.md" target="_blank" rel="noopener"
                   class="font-medium text-brand-700 transition duration-150 hover:text-brand-600">Ver la ficha completa en RUTA-MAESTRA →</a>
            </p>
        </div>
    @endforeach

    <p class="border-t border-neutral-100 px-4 py-3 text-xs text-neutral-400 sm:px-6">Toca un módulo para ver su detalle (completado / por completar). La línea naranja marca hoy; ventana clara = fechas planificadas, relleno sólido = avance real del tracker.</p>
</div>
