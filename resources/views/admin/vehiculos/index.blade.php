<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Vehículos" subtitle="Flota de la empresa: documentos, vencimientos y asignación.">
            <x-slot name="action">
                <div class="flex flex-wrap items-center gap-2">
                    {{-- El Excel se GENERA al momento con los datos de la app, así
                         que la planilla que circula sale siempre al día. Lleva los
                         filtros de la pantalla: se descarga lo que se está viendo,
                         y el archivo escribe adentro qué filtro se aplicó. --}}
                    <x-secondary-button-link
                        :href="route('admin.vehiculos.excel', request()->only(['q', 'doc', 'base', 'estado']))"
                        title="Descarga la flota en Excel, con los filtros aplicados">
                        <x-icon.document-text class="h-4 w-4" />
                        <span class="hidden sm:inline">Descargar Excel</span>
                        <span class="sm:hidden">Excel</span>
                    </x-secondary-button-link>

                    @can('manage vehiculos')
                        <x-button-link :href="route('admin.vehiculos.create')">
                            <x-icon.plus class="h-4 w-4" />
                            <span class="hidden sm:inline">Agregar vehículo</span>
                            <span class="sm:hidden">Agregar</span>
                        </x-button-link>
                    @endcan
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5 py-8">
        <x-status-alert :status="session('status')" />

        {{-- Tablero de la flota ACTIVA. Los números son enlaces al filtro que
             los produce: un conteo que no se puede abrir obliga a buscar a mano
             cuáles son. --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat-card label="Vehículos activos" :valor="$resumen['total']"
                         :href="route('admin.vehiculos.index', ['estado' => \App\Models\Vehiculo::ESTADO_ACTIVO])" />
            <x-stat-card label="Con documento vencido" :valor="$resumen[\App\Models\Vehiculo::DOC_VENCIDO]" alerta
                         :href="route('admin.vehiculos.index', ['doc' => \App\Models\Vehiculo::DOC_VENCIDO])" />
            <x-stat-card label="Por vencer (30 días)" :valor="$resumen[\App\Models\Vehiculo::DOC_POR_VENCER]" alerta
                         :href="route('admin.vehiculos.index', ['doc' => \App\Models\Vehiculo::DOC_POR_VENCER])" />
            <x-stat-card label="Con fechas sin cargar" :valor="$resumen[\App\Models\Vehiculo::DOC_SIN_REGISTRO]"
                         :href="route('admin.vehiculos.index', ['doc' => \App\Models\Vehiculo::DOC_SIN_REGISTRO])" />
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('admin.vehiculos.index') }}"
              class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:flex-row sm:items-end sm:p-4">
            <div class="flex-1">
                <x-input-label for="q" value="Buscar (patente, alias, marca o conductor)" />
                <x-text-input id="q" name="q" class="mt-1.5" type="text" :value="$q" placeholder="ej. PFBS22 o HD35" />
            </div>
            <div class="sm:w-40">
                <x-input-label for="doc" value="Documentos" />
                <x-select id="doc" name="doc" class="mt-1.5">
                    <option value="">Todos</option>
                    <option value="{{ \App\Models\Vehiculo::DOC_VENCIDO }}" @selected($doc === \App\Models\Vehiculo::DOC_VENCIDO)>Vencidos</option>
                    <option value="{{ \App\Models\Vehiculo::DOC_POR_VENCER }}" @selected($doc === \App\Models\Vehiculo::DOC_POR_VENCER)>Por vencer</option>
                    <option value="{{ \App\Models\Vehiculo::DOC_SIN_REGISTRO }}" @selected($doc === \App\Models\Vehiculo::DOC_SIN_REGISTRO)>Sin cargar</option>
                </x-select>
            </div>
            <div class="sm:w-40">
                <x-input-label for="base" value="Base" />
                <x-select id="base" name="base" class="mt-1.5">
                    <option value="">Todas</option>
                    @foreach ($bases as $b)
                        <option value="{{ $b }}" @selected($base === $b)>{{ $b }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="sm:w-36">
                <x-input-label for="estado" value="Estado" />
                <x-select id="estado" name="estado" class="mt-1.5">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Vehiculo::ESTADOS as $valor => $label)
                        <option value="{{ $valor }}" @selected($estado === $valor)>{{ $label }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="flex items-center gap-3">
                <x-primary-button>Filtrar</x-primary-button>
                @if ($q !== '' || $doc !== '' || $base !== '' || $estado !== '')
                    <x-secondary-link :href="route('admin.vehiculos.index')">Limpiar</x-secondary-link>
                @endif
            </div>
        </form>

        <x-list-card title="Flota" :count="$vehiculos->count()"
                     :countLabel="\Illuminate\Support\Str::plural('vehículo', $vehiculos->count())">
            @forelse ($vehiculos as $vehiculo)
                @php $criticos = $vehiculo->documentosCriticos(); @endphp
                <x-list-row>
                    {{-- La fila entera abre la ficha (patrón 03-08: se aprieta la
                         palabra, no un ícono al costado). --}}
                    <a href="{{ route('admin.vehiculos.show', $vehiculo) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-neutral-400">{{ $vehiculo->ppu }}</span>
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">
                                {{ $vehiculo->alias ?: $vehiculo->marca_modelo ?: $vehiculo->tipo_label }}
                            </p>
                            @unless ($vehiculo->es_activo)
                                <x-badge variant="neutral">{{ $vehiculo->estado_label }}</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">
                            {{ $vehiculo->tipo_label }}@if ($vehiculo->marca_modelo) · {{ $vehiculo->marca_modelo }}@endif
                            @if ($vehiculo->anio) · {{ $vehiculo->anio }}@endif
                            @if ($vehiculo->base) · {{ $vehiculo->base }}@endif
                        </p>
                        {{-- Qué documento está mal, en la fila. El semáforo dice
                             que hay un problema; sin el nombre del documento y el
                             plazo, igual hay que abrir la ficha para saber si es
                             el SOAP de mañana o una revisión técnica de 2023. --}}
                        @if ($criticos !== [])
                            <p class="mt-0.5 truncate text-xs {{ $vehiculo->estado_documental === \App\Models\Vehiculo::DOC_VENCIDO ? 'text-red-600' : 'text-brand-700' }}">
                                @foreach ($criticos as $i => $c)
                                    {{ $i > 0 ? ' · ' : '' }}{{ $c['label'] }}: {{ \Illuminate\Support\Str::lcfirst(\App\Models\Vehiculo::plazoLabel($c['dias'])) }}
                                @endforeach
                            </p>
                        @endif
                    </a>

                    <x-slot name="meta">
                        <div class="text-sm text-neutral-500 sm:w-36 sm:shrink-0 sm:text-right">
                            {{ $vehiculo->conductor_nombre ?: '' }}
                            @unless ($vehiculo->conductor_nombre)
                                <span class="text-neutral-400">sin conductor</span>
                            @endunless
                        </div>
                    </x-slot>

                    <x-slot name="actions">
                        <div class="sm:w-28 sm:text-right">
                            <x-badge :variant="\App\Models\Vehiculo::variante($vehiculo->estado_documental)">
                                {{ \App\Models\Vehiculo::estadoDocumentalLabel($vehiculo->estado_documental) }}
                            </x-badge>
                        </div>
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    @if ($q !== '' || $doc !== '' || $base !== '' || $estado !== '')
                        Ningún vehículo coincide con el filtro.
                    @else
                        Todavía no hay vehículos cargados.
                    @endif
                </li>
            @endforelse
        </x-list-card>
    </div>
</x-app-layout>
