<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Aprobaciones" subtitle="Historial completo del motor: qué se pidió, quién resolvió y con qué resultado." />
    </x-slot>

    <div class="space-y-6 py-12">

        {{-- Filtros (responsive: apilan en móvil, grilla en pantallas anchas) --}}
        <form method="GET" action="{{ route('admin.aprobaciones.index') }}"
              class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <x-input-label for="estado" value="Estado" />
                    <x-select id="estado" name="estado" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($estados as $e)
                            <option value="{{ $e }}" @selected(($filtros['estado'] ?? null) === $e)>{{ ucfirst(str_replace('_', ' ', $e)) }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-input-label for="tipo_accion" value="Tipo" />
                    <x-select id="tipo_accion" name="tipo_accion" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($tipos as $clave => $label)
                            <option value="{{ $clave }}" @selected(($filtros['tipo_accion'] ?? null) === $clave)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-input-label for="solicitante_id" value="Solicitante" />
                    <x-select id="solicitante_id" name="solicitante_id" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $u)
                            <option value="{{ $u->id }}" @selected(($filtros['solicitante_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-input-label for="resuelto_por" value="Aprobador" />
                    <x-select id="resuelto_por" name="resuelto_por" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $u)
                            <option value="{{ $u->id }}" @selected(($filtros['resuelto_por'] ?? null) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-input-label for="desde" value="Desde" />
                    <x-text-input id="desde" name="desde" type="date" class="mt-1.5 block w-full" :value="$filtros['desde'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="hasta" value="Hasta" />
                    <x-text-input id="hasta" name="hasta" type="date" class="mt-1.5 block w-full" :value="$filtros['hasta'] ?? ''" />
                </div>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <x-primary-button>Filtrar</x-primary-button>
                @if (array_filter($filtros))
                    <x-secondary-link :href="route('admin.aprobaciones.index')">Limpiar</x-secondary-link>
                @endif
            </div>
        </form>

        {{-- Resumen (respeta los filtros): por estado + por tipo + por aprobador/solicitante --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por estado</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($estados as $e)
                        @if (($porEstado[$e] ?? 0) > 0)
                            <span class="inline-flex items-center gap-1.5">
                                <x-aprobaciones.estado-badge :estado="$e" />
                                <span class="text-sm font-semibold tabular-nums text-neutral-700">{{ $porEstado[$e] }}</span>
                            </span>
                        @endif
                    @empty
                    @endforelse
                    @if ($porEstado->sum() === 0)
                        <span class="text-sm text-neutral-500">Sin datos.</span>
                    @endif
                </div>
            </div>

            {{-- Por tipo (categoría de la solicitud). Future-proof: hoy un solo
                 tipo; al integrarse M04/M05/M07/M13 aparecen sus categorías. --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por tipo</p>
                <ul class="mt-3 space-y-1.5">
                    @forelse ($porTipo as $tipoAccion => $c)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate text-neutral-700">{{ $tipos[$tipoAccion] ?? $tipoAccion }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-neutral-900">{{ $c }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-neutral-500">Sin datos.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por solicitante</p>
                <ul class="mt-3 space-y-1.5">
                    @forelse ($porSolicitante->take(5) as $fila)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate text-neutral-700">{{ $fila['nombre'] }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-neutral-900">{{ $fila['c'] }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-neutral-500">Sin datos.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Por aprobador</p>
                <ul class="mt-3 space-y-1.5">
                    @forelse ($porAprobador->take(5) as $fila)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate text-neutral-700">{{ $fila['nombre'] }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-neutral-900">{{ $fila['c'] }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-neutral-500">Sin datos.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Lista --}}
        <x-list-card title="Solicitudes" :count="$aprobaciones->total()" :countLabel="\Illuminate\Support\Str::plural('solicitud', $aprobaciones->total())">
            @forelse ($aprobaciones as $aprobacion)
                <x-list-row>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate font-medium text-neutral-900">{{ $aprobacion->descripcion }}</p>
                        <x-aprobaciones.estado-badge :estado="$aprobacion->estado" />
                        @if ($aprobacion->nivel_escalamiento > 0)
                            <x-badge variant="brand">Escalada</x-badge>
                        @endif
                    </div>
                    <p class="mt-1 truncate text-xs text-neutral-500">
                        {{ $aprobacion->etiquetaTipo() }}
                        · pidió {{ $aprobacion->solicitante?->name ?? '—' }}
                        @if ($aprobacion->resuelto_por)
                            · resolvió {{ $aprobacion->resueltoPor?->name ?? '—' }}
                        @endif
                        @if ($aprobacion->monto !== null)
                            · magnitud {{ number_format($aprobacion->monto, 0, ',', '.') }}
                        @endif
                    </p>
                    @if ($aprobacion->estado === \App\Models\Aprobacion::ESTADO_RECHAZADA && $aprobacion->resultado_motivo)
                        <p class="mt-0.5 truncate text-xs text-red-700">{{ $aprobacion->resultado_motivo }}</p>
                    @endif

                    <x-slot name="meta">
                        <div class="text-sm text-neutral-500 sm:w-44 sm:shrink-0 sm:text-right">
                            {{ $aprobacion->created_at?->enChile()->format('d-m-Y H:i') }}
                            @if ($aprobacion->resuelta_at)
                                <span class="block text-xs text-neutral-400">resuelta {{ $aprobacion->resuelta_at->enChile()->format('d-m-Y H:i') }}</span>
                            @endif
                        </div>
                    </x-slot>
                </x-list-row>
            @empty
                {{-- Antes decía siempre "con esos filtros", que era el error espejo: sin
                     filtros afirmaba que había un filtro aplicado. --}}
                <x-lista-vacia :filtros="$filtros"
                               vacio="No hay solicitudes de aprobación todavía. Se crean solas cuando alguien pide autorizar algo que la supera."
                               sinResultados="Ninguna solicitud coincide con esos filtros. Hay solicitudes registradas, pero ninguna en este corte."
                               :limpiarHref="route('admin.aprobaciones.index')" />
            @endforelse
        </x-list-card>

        @if ($aprobaciones->hasPages())
            <div>{{ $aprobaciones->links() }}</div>
        @endif
    </div>
</x-app-layout>
