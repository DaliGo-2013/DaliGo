<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Instalaciones" subtitle="Registro de instalaciones y puestas en marcha del técnico industrial.">
            <x-slot name="action">
                <x-button-link :href="route('admin.instalaciones.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Registrar instalación
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5 py-8 sm:py-12">
        <x-status-alert :status="session('status')" />

        {{-- Filtros --}}
        <form method="GET" action="{{ route('admin.instalaciones.index') }}"
              class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <x-input-label for="q" value="Buscar (cliente, RUT, producto, factura, vendedor)" />
                <x-text-input id="q" name="q" class="mt-1.5" type="text" :value="$filtros['q'] ?? ''" placeholder="ej. Agua purificada, 76.543.210-9, LAVADORA…" />
            </div>
            <div class="sm:w-44">
                <x-input-label for="categoria" value="Categoría" />
                <x-select id="categoria" name="categoria" class="mt-1.5">
                    <option value="">Todas</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat }}" @selected(($filtros['categoria'] ?? '') === $cat)>{{ \App\Models\Instalacion::CATEGORIA_ETIQUETAS[$cat] }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="flex items-center gap-3">
                {{-- El período elegido en las cards de historial se conserva al filtrar. --}}
                @foreach (['anio', 'mes'] as $oculto)
                    @if (($filtros[$oculto] ?? '') !== '')
                        <input type="hidden" name="{{ $oculto }}" value="{{ $filtros[$oculto] }}">
                    @endif
                @endforeach
                <x-primary-button>Filtrar</x-primary-button>
                @if (array_filter($filtros))
                    <x-secondary-link :href="route('admin.instalaciones.index')">Limpiar</x-secondary-link>
                @endif
            </div>
        </form>

        {{-- Historial por período: cards de años y, dentro de un año, cards de sus
             12 meses. Mismo patrón que el listado de Servicio Técnico: el registro
             es histórico y crece sin cota, así que se entra por período en vez de
             scrollear una lista interminable. Navegan con anio/mes del mismo
             listado (la lista de abajo obedece el período elegido). --}}
        @php
            $anioActivo = ($filtros['anio'] ?? '') !== '' ? (int) $filtros['anio'] : null;
            $mesActivo = ($filtros['mes'] ?? '') !== '' ? (int) $filtros['mes'] : null;
            // Conservar el resto de los filtros al navegar por período.
            $qsBase = array_filter(
                collect($filtros)->except(['anio', 'mes'])->all(),
                fn ($v) => $v !== null && $v !== ''
            );
        @endphp
        @if ($historial['anios']->isNotEmpty())
            <div>
                <div class="mb-2 flex items-baseline justify-between gap-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                        Historial{{ $anioActivo ? ' · '.$anioActivo : '' }}
                    </h3>
                    @if ($anioActivo)
                        <a href="{{ route('admin.instalaciones.index', $qsBase) }}" class="text-xs font-medium text-brand-600 transition duration-150 hover:text-brand-700">&larr; Todos los años</a>
                    @endif
                </div>
                @if (! $anioActivo)
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($historial['anios'] as $a => $r)
                            <a href="{{ route('admin.instalaciones.index', array_merge($qsBase, ['anio' => $a])) }}"
                               class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4 transition duration-150 hover:border-brand-300 hover:shadow">
                                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Año</p>
                                <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ $a }}</p>
                                <p class="mt-1 text-sm text-neutral-600">{{ $r['total'] }} {{ $r['total'] === 1 ? 'instalación' : 'instalaciones' }}</p>
                                <p class="text-xs text-neutral-400">
                                    {{ collect($r['categorias'])
                                        ->map(fn ($n, $cat) => $n.' '.mb_strtolower(\App\Models\Instalacion::CATEGORIA_ETIQUETAS[$cat] ?? $cat))
                                        ->implode(' · ') }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                        @foreach ($historial['meses'] as $m => $conteo)
                            @php $nombreMes = ucfirst(\Illuminate\Support\Carbon::create($anioActivo, $m, 1)->translatedFormat('F')); @endphp
                            @if ($conteo > 0)
                                <a href="{{ route('admin.instalaciones.index', array_merge($qsBase, ['anio' => $anioActivo, 'mes' => $m])) }}"
                                   class="rounded-2xl border p-3 shadow-sm transition duration-150 {{ $mesActivo === $m ? 'border-brand-500 bg-brand-50' : 'border-neutral-200 bg-white hover:border-brand-300 hover:shadow' }}">
                                    <p class="text-sm font-semibold {{ $mesActivo === $m ? 'text-brand-700' : 'text-neutral-900' }}">{{ $nombreMes }}</p>
                                    <p class="text-xs {{ $mesActivo === $m ? 'text-brand-600' : 'text-neutral-500' }}">{{ $conteo }} {{ $conteo === 1 ? 'instalación' : 'instalaciones' }}</p>
                                </a>
                            @else
                                <div class="rounded-2xl border border-dashed border-neutral-200 bg-neutral-50 p-3">
                                    <p class="text-sm font-medium text-neutral-400">{{ $nombreMes }}</p>
                                    <p class="text-xs text-neutral-300">Sin instalaciones</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <x-list-card title="Instalaciones" :count="$instalaciones->total()" :countLabel="$instalaciones->total() === 1 ? 'instalación' : 'instalaciones'">
            @php $mesSep = null; @endphp
            @forelse ($instalaciones as $ins)
                @php $mesActual = $ins->fecha ? ucfirst($ins->fecha->translatedFormat('F Y')) : 'Sin fecha'; @endphp
                @if ($mesActual !== $mesSep)
                    @php $mesSep = $mesActual; @endphp
                    <li class="bg-neutral-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-neutral-500 sm:px-6">{{ $mesActual }}</li>
                @endif
                <li class="px-4 py-3 sm:px-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm text-neutral-400">{{ $ins->fecha?->format('d-m-Y') }}</span>
                                <x-badge variant="neutral">{{ $ins->categoria_label }}</x-badge>
                                <p class="truncate font-medium text-neutral-900">{{ $ins->cliente_nombre }}</p>
                                @if ($ins->instalacion)<x-badge variant="brand">Instalado</x-badge>@endif
                                @if ($ins->puesta_en_marcha)<x-badge variant="brand">Puesta en marcha</x-badge>@endif
                            </div>
                            <p class="mt-0.5 truncate text-sm text-neutral-600">
                                {{ collect([$ins->producto, $ins->comuna_region, $ins->cliente_rut])->filter()->implode(' · ') }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-neutral-400">
                                {{ collect([
                                    $ins->vendedor ? 'Vendedor: '.$ins->vendedor : null,
                                    $ins->dias ? $ins->dias.' '.($ins->dias === 1 ? 'día' : 'días') : null,
                                    $ins->n_factura ? 'Factura '.$ins->n_factura : null,
                                    $ins->forma_pago_label,
                                ])->filter()->implode(' · ') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-secondary-link :href="route('admin.instalaciones.edit', $ins)">Editar</x-secondary-link>
                            <form method="POST" action="{{ route('admin.instalaciones.destroy', $ins) }}"
                                  onsubmit="return confirm('¿Eliminar esta instalación del registro?');">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                    <x-icon.trash class="h-5 w-5" />
                                </x-icon-button>
                            </form>
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    Aún no hay instalaciones registradas. Usa «Registrar instalación» para la primera.
                </li>
            @endforelse
        </x-list-card>

        @if ($instalaciones->hasPages())
            <div>{{ $instalaciones->links() }}</div>
        @endif
    </div>
</x-app-layout>
