<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Producción" subtitle="Asignaciones y revisión de reportes del día.">
            <x-slot name="action">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <x-secondary-link :href="route('admin.produccion.sopladores')">Sopladores</x-secondary-link>
                    <x-secondary-link :href="route('admin.produccion.movimientos')">Kardex</x-secondary-link>
                    <x-secondary-link :href="route('admin.maquinas.index')">Máquinas</x-secondary-link>
                    <x-secondary-link :href="route('admin.tipos-botellon.index')">Tipos de botellón</x-secondary-link>
                    <x-button-link :href="route('admin.produccion.asignar')">
                        <x-icon.plus class="h-4 w-4" />
                        Asignar
                    </x-button-link>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            {{-- Requiere tu atención: lo accionable primero --}}
            @php $hayAlertas = ($alertas['porAprobar'] + $alertas['devueltos'] + $alertas['atrasados']) > 0; @endphp
            <div class="dg-enter mb-6">
                <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Requiere tu atención</h3>
                @if ($hayAlertas)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        {{-- Naranjo = requiere tu acción. Es un enlace a la cola; la ⓘ va como hermano superpuesto (no anidar botón en <a>). --}}
                        <div class="relative">
                            <a href="#cola" class="block rounded-2xl border p-4 shadow-sm transition duration-150 active:scale-[0.98] {{ $alertas['porAprobar'] > 0 ? 'border-brand-200 bg-brand-50 hover:border-brand-300' : 'border-neutral-200 bg-white hover:border-neutral-300' }}">
                                <p class="text-2xl font-semibold {{ $alertas['porAprobar'] > 0 ? 'text-brand-700' : 'text-neutral-900' }}">{{ $alertas['porAprobar'] }}</p>
                                <p class="mt-1 text-sm {{ $alertas['porAprobar'] > 0 ? 'text-brand-700' : 'text-neutral-500' }}">Por aprobar</p>
                            </a>
                            <span class="absolute right-2 top-2 z-10"><x-info-tip>Reportes que el soplador ya envió y esperan tu aprobación. Al aprobar se registran en el kardex.</x-info-tip></span>
                        </div>
                        {{-- Rojo = problema (devuelto). --}}
                        <div class="relative rounded-2xl border p-4 shadow-sm {{ $alertas['devueltos'] > 0 ? 'border-red-200 bg-red-50' : 'border-neutral-200 bg-white' }}">
                            <p class="text-2xl font-semibold {{ $alertas['devueltos'] > 0 ? 'text-red-700' : 'text-neutral-900' }}">{{ $alertas['devueltos'] }}</p>
                            <p class="mt-1 text-sm {{ $alertas['devueltos'] > 0 ? 'text-red-700' : 'text-neutral-500' }}">Devueltos sin corregir</p>
                            <span class="absolute right-2 top-2"><x-info-tip>Reportes que devolviste al soplador; siguen pendientes hasta que los corrija y reenvíe.</x-info-tip></span>
                        </div>
                        <div class="relative rounded-2xl border p-4 shadow-sm {{ $alertas['atrasados'] > 0 ? 'border-brand-200 bg-brand-50' : 'border-neutral-200 bg-white' }}">
                            <p class="text-2xl font-semibold {{ $alertas['atrasados'] > 0 ? 'text-brand-700' : 'text-neutral-900' }}">{{ $alertas['atrasados'] }}</p>
                            <p class="mt-1 text-sm {{ $alertas['atrasados'] > 0 ? 'text-brand-700' : 'text-neutral-500' }}">Atrasados hoy · sin enviar</p>
                            <span class="absolute right-2 top-2"><x-info-tip>Sopladores con producción asignada para hoy que todavía no envían su reporte.</x-info-tip></span>
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm font-medium text-neutral-600">
                        Todo al día — nada por aprobar, devuelto ni atrasado.
                    </div>
                @endif
            </div>

            {{-- Hoy (ampliado) --}}
            <div class="dg-enter mb-6">
                <div class="mb-2 flex items-baseline justify-between gap-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Hoy</h3>
                    <span class="text-xs text-neutral-400">{{ now()->translatedFormat('l d \\d\\e F') }}</span>
                </div>
                @php
                    $chipsHoy = [
                        ['Asignado', number_format($hoy['asignadas'], 0, ',', '.'), null, 'Preformas asignadas hoy a los sopladores para producir.'],
                        ['Producido', number_format($hoy['producido'], 0, ',', '.'), 'brand', 'Botellones vendibles hechos hoy: primera + segunda calidad (no incluye merma).'],
                        ['% de avance', $hoy['avance'].'%', null, 'Producido respecto a lo asignado hoy (producido ÷ asignado).'],
                        ['Merma', number_format($hoy['merma'], 0, ',', '.').' ('.$hoy['merma_pct'].'%)', 'muted', 'Unidades perdidas hoy (malos + preformas dañadas) y, entre paréntesis, su % sobre el total.'],
                        ['Tasa 1ª', $hoy['tasa1'].'%', null, 'Porcentaje de botellones de primera calidad sobre el total producido hoy.'],
                    ];
                @endphp
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    @foreach ($chipsHoy as [$label, $valor, $tono, $info])
                        <div class="relative rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                            <p class="pr-6 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $label }}</p>
                            <p class="mt-1 text-2xl font-semibold {{ $tono === 'brand' ? 'text-brand-600' : ($tono === 'muted' ? 'text-neutral-500' : 'text-neutral-900') }}">{{ $valor }}</p>
                            <span class="absolute right-2 top-2"><x-info-tip>{{ $info }}</x-info-tip></span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Producción por periodo (rango; default últimos 7 días) --}}
            @php
                $tp = $periodo['totales'];
                $rangoLabel = $periodo['esDefault']
                    ? 'Últimos 7 días'
                    : \Illuminate\Support\Carbon::parse($periodo['desde'])->translatedFormat('d M') . ' – ' . \Illuminate\Support\Carbon::parse($periodo['hasta'])->translatedFormat('d M');
            @endphp
            <div class="dg-enter mb-6 rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-neutral-100 px-4 py-3 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Producción por periodo</h3>
                            <x-info-tip align="left">Producción de los días del rango elegido (por defecto, últimos 7): asignado, producido (vendible 1ª+2ª) y merma del periodo, con el detalle por día. Toca un día para ver su detalle.</x-info-tip>
                        </div>
                        <p class="mt-0.5 text-sm text-neutral-500">{{ $rangoLabel }}</p>
                    </div>
                    <form method="GET" action="{{ route('admin.produccion.index') }}" class="flex flex-wrap items-end gap-2">
                        <div>
                            <x-input-label for="desde" value="Desde" />
                            <x-text-input id="desde" name="desde" type="date" class="mt-1" :value="$periodo['desde']" />
                        </div>
                        <div>
                            <x-input-label for="hasta" value="Hasta" />
                            <x-text-input id="hasta" name="hasta" type="date" class="mt-1" :value="$periodo['hasta']" />
                        </div>
                        <x-secondary-button>Filtrar</x-secondary-button>
                    </form>
                </div>

                {{-- Totales del rango --}}
                <div class="grid grid-cols-2 gap-x-6 gap-y-3 border-b border-neutral-100 px-4 py-4 sm:grid-cols-4 sm:px-6">
                    <div><p class="text-xs uppercase tracking-wide text-neutral-400">Asignado</p><p class="mt-0.5 text-xl font-semibold text-neutral-900">{{ number_format($tp['asignadas'], 0, ',', '.') }}</p></div>
                    <div><p class="text-xs uppercase tracking-wide text-neutral-400">Producido</p><p class="mt-0.5 text-xl font-semibold text-brand-600">{{ number_format($tp['producido'], 0, ',', '.') }}</p></div>
                    <div><p class="text-xs uppercase tracking-wide text-neutral-400">Merma</p><p class="mt-0.5 text-xl font-semibold text-neutral-500">{{ number_format($tp['merma'], 0, ',', '.') }} ({{ $tp['merma_pct'] }}%)</p></div>
                    <div><p class="text-xs uppercase tracking-wide text-neutral-400">Reportes</p><p class="mt-0.5 text-xl font-semibold text-neutral-900">{{ $tp['reportes'] }}</p></div>
                </div>

                {{-- Tabla por día con mini-barras (cada día enlaza a su detalle) --}}
                @include('admin.produccion.partials._tendencia', ['tendencia' => $periodo, 'linkDia' => true])
            </div>

            {{-- Desgloses del periodo (clickeables a su detalle) --}}
            @php $linkRango = ['desde' => $periodo['desde'], 'hasta' => $periodo['hasta']]; @endphp
            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ranking de sopladores · periodo</h3>
                        <x-info-tip align="left">Producción de cada soplador en el rango, de mayor a menor. Toca un soplador para ver su historial.</x-info-tip>
                    </div>
                    @include('admin.produccion.partials._desglose', [
                        'items' => $rankingSopladores,
                        'linkRoute' => 'admin.produccion.soplador', 'linkKey' => 'soplador', 'linkExtra' => $linkRango,
                    ])
                </div>
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center gap-1.5 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por tipo de botellón · periodo</h3>
                        <x-info-tip align="left">Cuánto se produjo de cada tipo de botellón en el rango. Toca un tipo para ver su detalle.</x-info-tip>
                    </div>
                    @include('admin.produccion.partials._desglose', [
                        'items' => $porTipoPeriodo,
                        'linkRoute' => 'admin.produccion.tipo', 'linkKey' => 'tipoBotellon', 'linkExtra' => $linkRango,
                        'sinNombre' => 'Sin tipo',
                    ])
                </div>
            </div>

            {{-- Producción del día por máquina (incluye reportes sin aprobar) --}}
            @if ($porMaquina->isNotEmpty())
                <div class="dg-enter mb-6 rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <div class="flex items-center gap-1.5">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por máquina · hoy</h3>
                            <x-info-tip align="left">Producción de hoy por máquina, según las tandas reportadas (puede diferir de totales editados). Toca una máquina para ver su rendimiento.</x-info-tip>
                        </div>
                        <span class="text-right text-xs font-medium text-neutral-400">según tandas reportadas · puede diferir de totales editados</span>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($porMaquina as $fila)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 sm:px-6">
                                @if ($fila->maquina_id)
                                    <a href="{{ route('admin.produccion.maquina', $fila->maquina_id) }}" class="text-sm font-medium text-neutral-900 transition duration-150 hover:text-brand-600">{{ $fila->maquina }}@if ($porMaquinaMultiSucursal && $fila->sucursal)<span class="text-neutral-400"> · {{ $fila->sucursal }}</span>@endif</a>
                                @else
                                    <p class="text-sm font-medium text-neutral-900">Sin máquina</p>
                                @endif
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
                                    <x-produccion.metrica label="1ª" w="w-16">{{ number_format($fila->primera, 0, ',', '.') }}</x-produccion.metrica>
                                    <x-produccion.metrica label="2ª" w="w-16">{{ number_format($fila->segunda, 0, ',', '.') }}</x-produccion.metrica>
                                    <x-produccion.metrica label="Malos" w="w-24">{{ number_format($fila->malo, 0, ',', '.') }}</x-produccion.metrica>
                                    <x-produccion.metrica label="Dañadas" w="w-28">{{ number_format($fila->danada, 0, ',', '.') }}</x-produccion.metrica>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-list-card id="cola" title="Cola de reportes · hoy" :count="$reportes->count()" :countLabel="\Illuminate\Support\Str::plural('reporte', $reportes->count())">
                @forelse ($reportes as $reporte)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($reporte->soplador->name, 0, 1) }}</x-avatar>
                        </x-slot>

                        <a href="{{ route('admin.produccion.soplador', $reporte->soplador) }}"
                           class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $reporte->soplador->name }}</a>
                        <p class="truncate text-sm text-neutral-500">
                            Turno {{ $reporte->turno }} · asignadas {{ number_format($reporte->asignadas, 0, ',', '.') }}
                        </p>

                        <x-slot name="meta">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
                                <x-produccion.metrica label="1ª" w="w-16">{{ $reporte->primera }}</x-produccion.metrica>
                                <x-produccion.metrica label="2ª" w="w-16">{{ $reporte->segunda }}</x-produccion.metrica>
                                <x-produccion.metrica label="Malos" w="w-24">{{ $reporte->malo }}</x-produccion.metrica>
                                <x-produccion.metrica label="Dañadas" w="w-28">{{ $reporte->danada }}</x-produccion.metrica>
                                <span class="inline-flex w-16 items-baseline gap-1 font-medium tabular-nums {{ $reporte->diferencia === 0 ? 'text-neutral-400' : 'text-neutral-900' }}">
                                    Δ <span>{{ $reporte->diferencia }}</span>
                                </span>
                                <x-produccion.estado-badge :estado="$reporte->estado" />
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('admin.produccion.reporte.show', $reporte) }}"
                                   class="whitespace-nowrap text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">
                                    Revisar
                                </a>
                                {{-- Eliminar una produccion asignada por error: solo borradores sin avances. --}}
                                @if ($reporte->estado === \App\Models\ProduccionReporte::BORRADOR && $reporte->registros_count === 0)
                                    <form method="POST" action="{{ route('admin.produccion.reporte.destroy', $reporte) }}"
                                          onsubmit="return confirm('¿Eliminar esta producción asignada? Aún no tiene avances.');">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button type="submit" variant="danger" label="Eliminar producción" title="Eliminar producción">
                                            <x-icon.trash class="h-5 w-5" />
                                        </x-icon-button>
                                    </form>
                                @endif
                            </div>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-neutral-500">
                        No hay reportes para hoy. Usa <span class="font-medium text-neutral-700">Asignar</span> para empezar.
                    </li>
                @endforelse
            </x-list-card>
        </div>
    </div>
</x-app-layout>
