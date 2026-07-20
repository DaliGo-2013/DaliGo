<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Servicio Técnico" subtitle="Ingreso de máquinas y lavadoras al taller.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    {{-- Volver al inicio (visible para todos los que ven el listado). --}}
                    <x-icon-button :href="route('dashboard')" size="lg" variant="secondary" label="Volver al inicio" title="Volver al inicio">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>

                    {{-- Acciones secundarias agrupadas en un menú «Más» para no
                         amontonar el header (todas están también en el nav de ST).
                         Queda solo el CTA primario «Registrar ingreso» a la vista. --}}
                    <x-dropdown align="right" width="w-56">
                        <x-slot name="trigger">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                                Más
                                <x-icon.chevron-down class="h-4 w-4" />
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('admin.servicio-tecnico.informe')">Informes</x-dropdown-link>
                            @can('crear lote servicio')
                                <x-dropdown-link :href="route('admin.servicio-tecnico.lote.create')">Ingreso por lote</x-dropdown-link>
                            @endcan
                            @can('manage servicio tecnico')
                                <x-dropdown-link :href="route('admin.servicio-tecnico.qr')">Códigos QR</x-dropdown-link>
                            @endcan
                            <x-dropdown-link :href="route('admin.servicio-tecnico.seguimiento-demo')">Seguimiento (boceto)</x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    @can('manage servicio tecnico')
                        <x-button-link :href="route('admin.servicio-tecnico.create')">
                            <x-icon.plus class="h-4 w-4" />
                            Registrar ingreso
                        </x-button-link>
                    @endcan
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Por confirmar: maquinas que llegaron por QR (celular del cliente) y
                 esperan que quien autoriza (jefe de bodega / tecnico) revise los
                 datos y confirme la recepcion. --}}
            @can('confirmar servicio tecnico')
                @if ($porConfirmar->isNotEmpty())
                    <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 shadow-sm sm:p-5">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-brand-600 px-1.5 text-xs font-semibold text-white">{{ $porConfirmar->count() }}</span>
                            <h3 class="text-sm font-semibold text-brand-700">Por confirmar (llegaron por QR)</h3>
                        </div>
                        <ul class="space-y-2">
                            @foreach ($porConfirmar as $p)
                                <li class="flex flex-col gap-3 rounded-xl border border-brand-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-mono text-xs text-neutral-400">{{ $p->folio }}</span>
                                            <span class="truncate font-medium text-neutral-900">{{ $p->cliente_nombre }}</span>
                                        </div>
                                        <p class="truncate text-sm text-neutral-500">
                                            {{ collect([$p->tipo_equipo_label, $p->numero_serie ? 'N° '.$p->numero_serie : null, $p->sucursal?->nombre])->filter()->implode(' · ') }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <x-secondary-link :href="route('admin.servicio-tecnico.show', $p)">Revisar</x-secondary-link>
                                        <form method="POST" action="{{ route('admin.servicio-tecnico.confirmar', $p) }}"
                                              onsubmit="return confirm('¿Confirmar la recepción de la orden {{ $p->folio }}? Se le enviará el detalle al cliente por correo.');">
                                            @csrf
                                            <x-primary-button>Confirmar recepción</x-primary-button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endcan

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.servicio-tecnico.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="q" value="Buscar (folio, cliente, modelo o serie)" />
                    <x-text-input id="q" name="q" class="mt-1.5" type="text" :value="$filtros['q'] ?? ''" placeholder="ej. 000009, 12.345.678-9, SN-12345" />
                </div>
                <div class="sm:w-44">
                    <x-input-label for="sucursal_id" value="Sucursal (recepción)" />
                    <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5">
                        <option value="">Todas</option>
                        @foreach ($sucursales as $s)
                            <option value="{{ $s->id }}" @selected((string) ($filtros['sucursal_id'] ?? '') === (string) $s->id)>{{ $s->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                {{-- El período elegido en las cards de historial se conserva al filtrar. --}}
                @if (($filtros['anio'] ?? '') !== '')
                    <input type="hidden" name="anio" value="{{ $filtros['anio'] }}">
                @endif
                @if (($filtros['mes'] ?? '') !== '')
                    <input type="hidden" name="mes" value="{{ $filtros['mes'] }}">
                @endif
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.servicio-tecnico.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            {{-- Historial por período: cards de años y, dentro de un año, cards de
                 sus 12 meses. Navegan con los parámetros anio/mes del mismo listado
                 (la lista de abajo obedece el período elegido). --}}
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
                            <a href="{{ route('admin.servicio-tecnico.index', $qsBase) }}" class="text-xs font-medium text-brand-600 transition duration-150 hover:text-brand-700">&larr; Todos los años</a>
                        @endif
                    </div>
                    @if (! $anioActivo)
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($historial['anios'] as $a => $r)
                                <a href="{{ route('admin.servicio-tecnico.index', array_merge($qsBase, ['anio' => $a])) }}"
                                   class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm transition duration-150 hover:border-brand-300 hover:shadow">
                                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Año</p>
                                    <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ $a }}</p>
                                    <p class="mt-1 text-sm text-neutral-600">{{ $r['total'] }} {{ $r['total'] === 1 ? 'orden' : 'órdenes' }}</p>
                                    <p class="text-xs text-neutral-400">{{ $r['garantia'] }} garantía · {{ $r['reparacion'] }} reparación</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                            @foreach ($historial['meses'] as $m => $conteo)
                                @php $nombreMes = ucfirst(\Illuminate\Support\Carbon::create($anioActivo, $m, 1)->translatedFormat('F')); @endphp
                                @if ($conteo > 0)
                                    <a href="{{ route('admin.servicio-tecnico.index', array_merge($qsBase, ['anio' => $anioActivo, 'mes' => $m])) }}"
                                       class="rounded-2xl border p-3 shadow-sm transition duration-150 {{ $mesActivo === $m ? 'border-brand-500 bg-brand-50' : 'border-neutral-200 bg-white hover:border-brand-300 hover:shadow' }}">
                                        <p class="text-sm font-semibold {{ $mesActivo === $m ? 'text-brand-700' : 'text-neutral-900' }}">{{ $nombreMes }}</p>
                                        <p class="text-xs {{ $mesActivo === $m ? 'text-brand-600' : 'text-neutral-500' }}">{{ $conteo }} {{ $conteo === 1 ? 'orden' : 'órdenes' }}</p>
                                    </a>
                                @else
                                    <div class="rounded-2xl border border-dashed border-neutral-200 bg-neutral-50 p-3">
                                        <p class="text-sm font-medium text-neutral-400">{{ $nombreMes }}</p>
                                        <p class="text-xs text-neutral-300">Sin órdenes</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <x-list-card title="Órdenes" :count="$ordenes->total()" :countLabel="$ordenes->total() === 1 ? 'orden' : 'órdenes'">
                @php $mesSeparador = null; @endphp
                @forelse ($ordenes as $orden)
                    {{-- Separador visual cuando la lista cambia de mes (viene ordenada
                         por fecha desc, así que los meses quedan contiguos). --}}
                    @php $mesOrden = $orden->fecha_ingreso ? ucfirst($orden->fecha_ingreso->translatedFormat('F Y')) : 'Sin fecha'; @endphp
                    @if ($mesOrden !== $mesSeparador)
                        @php $mesSeparador = $mesOrden; @endphp
                        <li class="bg-neutral-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-neutral-500 sm:px-6">{{ $mesOrden }}</li>
                    @endif
                    <x-list-row>
                        <x-slot name="leading">
                            {{-- Condición del ingreso como avatar: R = Reparación, G = Garantía.
                                 Ambas en fondo naranja (marca) por pedido del dueño; la letra y el
                                 tooltip distinguen el significado. Reemplaza al avatar del tipo. --}}
                            @php $esGarantia = $orden->condicion_efectiva === 'garantia'; @endphp
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white"
                                 title="{{ $esGarantia ? 'Garantía' : 'Reparación' }}">{{ $esGarantia ? 'G' : 'R' }}</div>
                        </x-slot>

                        @php
                            $detalle = collect([
                                $orden->cliente_rut,
                                $orden->tipo_equipo_label,
                                $orden->producto?->sku,
                                $orden->modelo ?: null,
                                $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
                            ])->filter()->implode(' · ');
                        @endphp
                        @php
                            $verHref = auth()->user()->can('manage servicio tecnico')
                                ? route('admin.servicio-tecnico.edit', $orden)
                                : route('admin.servicio-tecnico.show', $orden);
                        @endphp
                        <a href="{{ $verHref }}" class="block">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs text-neutral-400">{{ $orden->folio }}</span>
                                <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $orden->cliente_nombre }}</p>
                                {{-- Un solo badge de estado que incluye la sucursal de recepción:
                                     "Recibido en El Mirador" / "En Revisión en El Mirador"… --}}
                                <x-badge :variant="$orden->estado_variante">{{ \Illuminate\Support\Str::headline($orden->estado) }}@if ($orden->sucursal) en {{ $orden->sucursal->nombre }}@elseif ($orden->ruta) en Ruta {{ $orden->ruta }}@endif</x-badge>
                            </div>
                            <p class="truncate text-sm text-neutral-500">{{ $detalle }}</p>
                            {{-- Quién recibió/confirmó la orden (al registrar en mostrador o al
                                 confirmar un ingreso por QR). Solo si el dato existe. --}}
                            @if ($orden->recibida_por)
                                <p class="truncate text-xs text-neutral-400">Recibido por {{ $orden->recibida_por }}</p>
                            @endif
                        </a>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-32 sm:shrink-0 sm:text-right">
                                {{ $orden->fecha_ingreso?->format('d-m-Y') }}
                            </div>
                        </x-slot>

                        @can('manage servicio tecnico')
                            <x-slot name="actions">
                                <form method="POST" action="{{ route('admin.servicio-tecnico.destroy', $orden) }}" onsubmit="return confirm('¿Eliminar la orden {{ $orden->folio }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                        <x-icon.trash class="h-5 w-5" />
                                    </x-icon-button>
                                </form>
                            </x-slot>
                        @endcan
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">
                        No hay órdenes registradas. Registra el primer ingreso de un equipo al taller.
                    </li>
                @endforelse
            </x-list-card>

            @if ($ordenes->hasPages())
                <div>{{ $ordenes->links() }}</div>
            @endif
        </div>
    </div>

    @can('confirmar servicio tecnico')
        {{-- Aviso suave (SIN recargar la página): cada 25s consulta el conteo de
             "por confirmar" en segundo plano; si llegaron ingresos nuevos por QR
             desde que cargó la página, muestra este banner para actualizar cuando
             el encargado quiera. Se pausa si la pestaña no está visible. --}}
        <div id="aviso-nuevos" class="fixed inset-x-0 bottom-4 z-40 hidden text-center">
            <button type="button" onclick="window.location.reload()"
                class="inline-flex items-center gap-2 rounded-full bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition duration-150 hover:bg-brand-700 active:scale-[0.98]">
                <span id="aviso-nuevos-texto">Llegaron ingresos nuevos por QR</span>
                <span class="opacity-90">&middot; Actualizar &#8635;</span>
            </button>
        </div>
        <script>
            (function () {
                var base = {{ $porConfirmar->count() }};
                var aviso = document.getElementById('aviso-nuevos');
                var texto = document.getElementById('aviso-nuevos-texto');
                var url = '{{ route('admin.servicio-tecnico.por-confirmar.conteo') }}';
                setInterval(function () {
                    if (document.visibilityState !== 'visible') return;
                    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (d) {
                            if (!d) return;
                            var nuevos = d.total - base;
                            if (nuevos > 0) {
                                texto.textContent = nuevos === 1
                                    ? 'Llegó 1 ingreso nuevo por QR'
                                    : ('Llegaron ' + nuevos + ' ingresos nuevos por QR');
                                aviso.classList.remove('hidden');
                            }
                        })
                        .catch(function () {});
                }, 25000);
            })();
        </script>
    @endcan
</x-app-layout>
