<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Servicio Técnico" subtitle="Ingreso de máquinas y lavadoras al taller.">
            <x-slot name="action">
                <x-button-link :href="route('admin.servicio-tecnico.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Registrar ingreso
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.servicio-tecnico.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="q" value="Buscar (cliente, marca, modelo o serie)" />
                    <x-text-input id="q" name="q" class="mt-1.5" type="text" :value="$filtros['q'] ?? ''" placeholder="ej. 12.345.678-9, Samsung, SN-12345" />
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
                    <x-input-label for="tecnico_id" value="Técnico" />
                    <x-select id="tecnico_id" name="tecnico_id" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($tecnicos as $t)
                            <option value="{{ $t->id }}" @selected((int) ($filtros['tecnico_id'] ?? 0) === $t->id)>{{ $t->name }}</option>
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
                                ucfirst($orden->tipo_equipo),
                                trim(($orden->marca ?? '').' '.($orden->modelo ?? '')) ?: null,
                                $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
                            ])->filter()->implode(' · ');
                        @endphp
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-neutral-400">{{ $orden->folio }}</span>
                            <p class="truncate font-medium text-neutral-900">{{ $orden->cliente?->razon_social ?? 'Sin cliente' }}</p>
                            <x-badge :variant="$orden->estado === 'entregado' ? 'neutral' : 'brand'">{{ \Illuminate\Support\Str::headline($orden->estado) }}</x-badge>
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $detalle }}</p>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-40 sm:shrink-0 sm:text-right">
                                <div>{{ $orden->fecha_ingreso?->format('d-m-Y') }}</div>
                                @if ($orden->tecnico)
                                    <div class="text-xs text-neutral-400">{{ $orden->tecnico->name }}</div>
                                @else
                                    <div class="text-xs text-neutral-400">sin técnico</div>
                                @endif
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.servicio-tecnico.edit', $orden)" label="Editar" title="Editar">
                                <x-icon.pencil class="h-5 w-5" />
                            </x-icon-button>
                            <form method="POST" action="{{ route('admin.servicio-tecnico.destroy', $orden) }}" onsubmit="return confirm('¿Eliminar la orden {{ $orden->folio }}?');">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                    <x-icon.trash class="h-5 w-5" />
                                </x-icon-button>
                            </form>
                        </x-slot>
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
</x-app-layout>
