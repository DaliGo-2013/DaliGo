<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header title="Hoy en vivo"
                       subtitle="Avance del turno por soplador, con proyección y paradas abiertas"
                       :back="route('admin.produccion.index')" backTitle="Volver a Producción" />
    </x-slot>

    {{-- Monitor del jefe (P-M11-21): se refresca solo por POLLING de la firma
         (patrón de la cola de bodega): un JSON chico cada 20 s y solo si el
         contenido CAMBIÓ se recarga. Con una parada abierta la firma incluye
         los minutos corriendo, así que el «lleva X min» server-side se
         mantiene honesto recargando ≤60 s. --}}
    <div class="space-y-6 py-12">
        @if (! $hayTurnoActivo)
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                <p class="text-sm text-neutral-500">Ningún turno activo a esta hora.</p>
                <p class="mt-1 text-xs text-neutral-400">Los horarios de turno se editan en Configuración (produccion_turnos).</p>
            </div>
        @elseif (empty($filas))
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                <p class="text-sm text-neutral-500">Sin producciones asignadas en el turno activo.</p>
                <p class="mt-1 text-xs text-neutral-400">Cuando el jefe asigne preformas, la fila aparece sola (el panel se refresca cada 20 segundos).</p>
            </div>
        @else
            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-4 py-3 sm:px-6">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Turno en curso</h3>
                    <span class="text-xs font-medium text-neutral-400">proyección lineal vs meta · umbral {{ $umbral }}%</span>
                </div>
                <ul class="divide-y divide-neutral-100">
                    @foreach ($filas as $fila)
                        <li class="px-4 py-3 sm:px-6 {{ $fila['abierto'] ? '' : 'opacity-60' }}">
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.produccion.reporte.show', $fila['id']) }}"
                                       class="text-sm font-medium text-neutral-900 transition duration-150 hover:text-brand-600">
                                        {{ $fila['soplador'] }}
                                    </a>
                                    <span class="text-xs text-neutral-400">· turno {{ $fila['turno'] }}{{ $fila['maquinas'] !== '' ? ' · '.$fila['maquinas'] : '' }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($fila['abierto'])
                                        @if ($fila['proyeccion'] !== null)
                                            <span class="text-xs tabular-nums text-neutral-500">proyección {{ $fila['proyeccion'] }}%</span>
                                        @endif
                                        <x-badge :variant="$fila['variante']">
                                            {{ [\App\Services\Produccion\CorteSic::SEMAFORO_AL_DIA => 'Al día', \App\Services\Produccion\CorteSic::SEMAFORO_EN_RIESGO => 'En riesgo', \App\Services\Produccion\CorteSic::SEMAFORO_CRITICO => 'Crítico'][$fila['semaforo']] }}
                                        </x-badge>
                                    @else
                                        <x-produccion.estado-badge :estado="$fila['estado']" />
                                    @endif
                                </div>
                            </div>

                            {{-- Barra de avance vs meta (ancho por style: Tailwind purga los dinámicos). --}}
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-neutral-200">
                                <div class="h-full rounded-full bg-brand-600" style="width: {{ min(100, $fila['avance']) }}%"></div>
                            </div>
                            <p class="mt-1 text-xs tabular-nums text-neutral-500">
                                {{ number_format($fila['producido'], 0, ',', '.') }} de {{ number_format($fila['meta'], 0, ',', '.') }} vendibles ({{ $fila['avance'] }}%)
                                @if ($fila['ultima_tanda'])
                                    · última tanda {{ $fila['ultima_tanda'] }}
                                @endif
                            </p>

                            @if ($fila['paradas'] !== [])
                                <ul class="mt-1.5 space-y-0.5">
                                    @foreach ($fila['paradas'] as $parada)
                                        <li class="text-xs text-neutral-500">
                                            · Parada abierta: {{ $parada['motivo'] }}{{ $parada['maquina'] ? ' ('.$parada['maquina'].')' : '' }}
                                            — desde las {{ $parada['inicio'] }}, lleva {{ $parada['lleva'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (! empty($porMaquina))
                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por máquina · turno en curso</h3>
                        <span class="text-xs font-medium text-neutral-400">según tandas reportadas</span>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($porMaquina as $maquina)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 sm:px-6">
                                <p class="text-sm font-medium text-neutral-900">{{ $maquina['maquina'] }}</p>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
                                    <x-produccion.metrica label="Vendible" w="w-28" tone="brand">{{ number_format($maquina['producido'], 0, ',', '.') }}</x-produccion.metrica>
                                    <x-produccion.metrica label="Merma" w="w-24" tone="muted">{{ number_format($maquina['merma'], 0, ',', '.') }}</x-produccion.metrica>
                                    @if ($maquina['paradas_abiertas'] > 0)
                                        <x-badge variant="brand">{{ $maquina['paradas_abiertas'] }} {{ \Illuminate\Support\Str::plural('parada', $maquina['paradas_abiertas']) }} abierta{{ $maquina['paradas_abiertas'] > 1 ? 's' : '' }}</x-badge>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>

    {{-- Se compara la FIRMA del contenido, no el total: los minutos de una
         parada abierta cambian sin que cambie el total de filas. Migrado a
         <x-poll-recarga> en MSG-3 (4º uso del molde), cero cambio de conducta. --}}
    <x-poll-recarga :url="route('admin.produccion.vivo.conteo')" :firma="$firma" />
</x-app-layout>
