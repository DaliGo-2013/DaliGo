<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Servicio Técnico" subtitle="Ingreso de máquinas y lavadoras al taller.">
            @can('manage servicio tecnico')
                <x-slot name="action">
                    <div class="flex items-center gap-2">
                        <x-secondary-link :href="route('admin.servicio-tecnico.qr')">Códigos QR</x-secondary-link>
                        <x-button-link :href="route('admin.servicio-tecnico.create')">
                            <x-icon.plus class="h-4 w-4" />
                            Registrar ingreso
                        </x-button-link>
                    </div>
                </x-slot>
            @endcan
        </x-page-header>
    </x-slot>

    <div class="py-12">
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
                                            {{ collect([ucfirst($p->tipo_equipo), $p->numero_serie ? 'N° '.$p->numero_serie : null, $p->sucursal?->nombre])->filter()->implode(' · ') }}
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
                    <x-input-label for="estado" value="Estado" />
                    <x-select id="estado" name="estado" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($estados as $e)
                            <option value="{{ $e }}" @selected(($filtros['estado'] ?? '') === $e)>{{ \Illuminate\Support\Str::headline($e) }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="sm:w-40">
                    <x-input-label for="tipo_equipo" value="Tipo" />
                    <x-select id="tipo_equipo" name="tipo_equipo" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($tipos as $t)
                            <option value="{{ $t }}" @selected(($filtros['tipo_equipo'] ?? '') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="sm:w-44">
                    <x-input-label for="facturacion" value="Condición" />
                    <x-select id="facturacion" name="facturacion" class="mt-1.5">
                        <option value="">Todas</option>
                        @foreach ($facturaciones as $f)
                            <option value="{{ $f }}" @selected(($filtros['facturacion'] ?? '') === $f)>{{ ucfirst($f) }}</option>
                        @endforeach
                    </x-select>
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
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.servicio-tecnico.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Órdenes" :count="$ordenes->total()" :countLabel="\Illuminate\Support\Str::plural('orden', $ordenes->total())">
                @forelse ($ordenes as $orden)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_strtoupper(mb_substr($orden->tipo_equipo, 0, 1)) }}</x-avatar>
                        </x-slot>

                        @php
                            $detalle = collect([
                                $orden->cliente_rut,
                                ucfirst($orden->tipo_equipo),
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
                                <x-badge :variant="$orden->estado_variante">{{ \Illuminate\Support\Str::headline($orden->estado) }}@if ($orden->sucursal) en {{ $orden->sucursal->nombre }}@endif</x-badge>
                                {{-- Condición compacta (1 letra): R rojo = Reparación, G verde = Garantía.
                                     Excepción intencional a la paleta (aprobada por el dueño); tooltip con la palabra. --}}
                                @if ($orden->condicion_efectiva === 'garantia')
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-green-600 text-xs font-bold text-white" title="Garantía">G</span>
                                @else
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-red-600 text-xs font-bold text-white" title="Reparación">R</span>
                                @endif
                            </div>
                            <p class="truncate text-sm text-neutral-500">{{ $detalle }}</p>
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
        {{-- Auto-refresco del mostrador: recarga sola para que aparezcan los
             ingresos por QR sin apretar nada. Se pausa si quien autoriza esta
             escribiendo en un filtro o si la pestana no esta visible. --}}
        <script>
            setInterval(function () {
                var el = document.activeElement;
                var escribiendo = el && ['INPUT', 'SELECT', 'TEXTAREA'].indexOf(el.tagName) !== -1;
                if (!escribiendo && document.visibilityState === 'visible') {
                    window.location.reload();
                }
            }, 25000);
        </script>
    @endcan
</x-app-layout>
