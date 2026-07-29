{{--
    Facturación · Documentos (M05).

    Espeja la idea del menú «Documentos» de Bsale (un lugar donde están los
    documentos y desde donde se crean), pero con una regla propia: cada origen dice
    su estado REAL. El que no está disponible explica qué le falta, en vez de
    ofrecer un botón que no funciona.
--}}
<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header title="Documentos" subtitle="Boletas, facturas, guías y notas de crédito emitidas desde DaliGo." />
    </x-slot>

    <div class="space-y-5 py-8 sm:py-12">
        <x-status-alert :status="session('status')" />

        {{-- Estado del módulo. Arriba, porque explica todo lo demás — y redactado
             como AVANCE y no como carencia: el módulo está en marcha, y mientras lo
             esté, Bsale sigue funcionando igual que siempre. --}}
        @if ($bloqueo)
            <div class="rounded-2xl border border-brand-200 bg-brand-50 p-3 sm:p-4">
                <div class="flex items-start gap-3">
                    <x-icon.information-circle class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-brand-900">Módulo en marcha · la emisión se habilita más adelante</p>
                        <p class="mt-1 text-brand-800">
                            El documento ya se arma completo y se puede revisar; lo que todavía no está habilitado es
                            emitirlo de verdad, que es un paso que se autoriza a propósito. Mientras tanto la
                            facturación sigue funcionando en Bsale igual que siempre.
                        </p>
                        <p class="mt-2 text-xs">
                            <a href="{{ route('admin.dte.estado') }}" class="font-medium text-brand-700 underline hover:text-brand-800">
                                Ver el avance de la preparación →
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ───────────────── Nuevo documento: los orígenes ───────────────── --}}
        <div>
            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Nuevo documento</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($origenes as $origen)
                    <div class="rounded-2xl border p-3 sm:p-4 {{ $origen['disponible'] ? 'border-neutral-200 bg-white shadow-sm' : 'border-dashed border-neutral-200 bg-neutral-50' }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold {{ $origen['disponible'] ? 'text-neutral-900' : 'text-neutral-500' }}">
                                {{ $origen['titulo'] }}
                            </p>
                            @if (! $origen['disponible'])
                                {{-- «Próximamente» en naranjo de marca (en curso), no en
                                     neutro apagado: lo que viene no está roto. --}}
                                <x-badge variant="brand">Próximamente</x-badge>
                            @endif
                        </div>
                        <p class="mt-1 text-sm {{ $origen['disponible'] ? 'text-neutral-600' : 'text-neutral-500' }}">
                            {{ $origen['detalle'] }}
                        </p>
                        @if (! $origen['disponible'])
                            <p class="mt-2 text-xs text-neutral-500"><span class="font-medium">Cuándo:</span> {{ $origen['motivo'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ───────── Órdenes que se podrían facturar (el origen que SÍ existe) ───────── --}}
        <x-list-card title="Listas para facturar" :count="$ordenesListas->count()"
                     :countLabel="$ordenesListas->count() === 1 ? 'orden' : 'órdenes'">
            @forelse ($ordenesListas as $orden)
                <x-list-row>
                    <x-slot name="leading">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">
                            {{ $orden->fecha_ingreso?->format('d') ?: '—' }}
                        </div>
                    </x-slot>
                    <p class="truncate font-medium text-neutral-900">{{ $orden->cliente_nombre }}</p>
                    <p class="truncate text-sm text-neutral-500">
                        {{ $orden->folio }} · {{ ucfirst($orden->tipo_equipo) }}
                        @if ($orden->modelo) · {{ $orden->modelo }} @endif
                    </p>
                    <x-slot name="meta">
                        <span class="tabular-nums font-medium text-neutral-900">${{ number_format((int) $orden->costo_total, 0, ',', '.') }}</span>
                    </x-slot>
                    <x-slot name="actions">
                        <x-secondary-link :href="route('admin.servicio-tecnico.documento', $orden)">Ver documento</x-secondary-link>
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    No hay órdenes cobrables pendientes de facturar. Las órdenes en garantía no se facturan.
                </li>
            @endforelse
        </x-list-card>

        {{-- ───────────────── Lo emitido ───────────────── --}}
        <form method="GET" action="{{ route('admin.dte.index') }}"
              class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:flex-row sm:items-end sm:p-4">
            <div class="sm:w-56">
                <x-input-label for="tipo_dte" value="Tipo de documento" />
                <x-select id="tipo_dte" name="tipo_dte" class="mt-1.5">
                    <option value="">Todos</option>
                    @foreach (\App\Models\DteEmitido::TIPO_ETIQUETAS as $codigo => $etiqueta)
                        <option value="{{ $codigo }}" @selected((string) ($filtros['tipo_dte'] ?? '') === (string) $codigo)>{{ $etiqueta }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="sm:w-56">
                <x-input-label for="estado_sii" value="Estado ante el SII" />
                <x-select id="estado_sii" name="estado_sii" class="mt-1.5">
                    <option value="">Todos</option>
                    @foreach (\App\Services\Dte\EstadoSii::TODOS as $estado)
                        <option value="{{ $estado }}" @selected(($filtros['estado_sii'] ?? '') === $estado)>{{ \App\Services\Dte\EstadoSii::etiqueta($estado) }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="flex items-center gap-3">
                <x-primary-button>Filtrar</x-primary-button>
                @if (array_filter($filtros))
                    <x-secondary-link :href="route('admin.dte.index')">Limpiar</x-secondary-link>
                @endif
            </div>
        </form>

        <x-list-card title="Emitidos" :count="$documentos->total()"
                     :countLabel="$documentos->total() === 1 ? 'documento' : 'documentos'">
            @forelse ($documentos as $dte)
                <x-list-row>
                    <p class="truncate font-medium text-neutral-900">
                        {{ $dte->tipo_label }} {{ $dte->folio_label }}
                    </p>
                    <p class="truncate text-sm text-neutral-500">
                        {{ collect([
                            $dte->receptor_nombre,
                            $dte->receptor_rut,
                            $dte->ordenServicio?->codigo,
                            $dte->sucursal?->nombre,
                        ])->filter()->implode(' · ') }}
                    </p>
                    @if ($dte->mensaje_sii)
                        <p class="truncate text-xs text-neutral-400">{{ $dte->mensaje_sii }}</p>
                    @endif
                    <x-slot name="meta">
                        <span class="tabular-nums font-medium text-neutral-900">${{ number_format((int) $dte->total, 0, ',', '.') }}</span>
                        <x-badge :variant="$dte->estado_variante">{{ $dte->estado_label }}</x-badge>
                    </x-slot>
                    <x-slot name="actions">
                        @if ($dte->url_pdf)
                            <x-secondary-link :href="$dte->url_pdf">PDF</x-secondary-link>
                        @endif
                        @if ($dte->url_xml)
                            <x-secondary-link :href="$dte->url_xml">XML</x-secondary-link>
                        @endif
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-neutral-900">Acá van a aparecer los documentos emitidos</p>
                    <p class="mx-auto mt-1 max-w-md text-sm text-neutral-500">
                        Cada uno con su folio, su estado ante el SII y los enlaces al PDF y al XML. El XML es el
                        documento legal y hay que conservarlo 6 años: por eso queda respaldado acá y no solo en Bsale.
                    </p>
                    <p class="mt-3 text-xs">
                        <a href="{{ route('admin.dte.estado') }}" class="font-medium text-brand-600 hover:text-brand-700">
                            Ver el avance de la preparación →
                        </a>
                    </p>
                </li>
            @endforelse
        </x-list-card>

        @if ($documentos->hasPages())
            <div>{{ $documentos->links() }}</div>
        @endif
    </div>
</x-app-layout>
