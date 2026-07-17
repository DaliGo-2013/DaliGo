<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Instalaciones" subtitle="Registro de instalaciones y puestas en marcha del técnico industrial.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('dashboard')" size="lg" variant="secondary" label="Volver al inicio" title="Volver al inicio">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-button-link :href="route('admin.instalaciones.create')">
                        <x-icon.plus class="h-4 w-4" />
                        Registrar instalación
                    </x-button-link>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.instalaciones.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
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
                <div class="sm:w-32">
                    <x-input-label for="anio" value="Año" />
                    <x-select id="anio" name="anio" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($anios as $a)
                            <option value="{{ $a }}" @selected((string) ($filtros['anio'] ?? '') === (string) $a)>{{ $a }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.instalaciones.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

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
    </div>
</x-app-layout>
