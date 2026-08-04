<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Traslados al taller" subtitle="Máquinas a reparar que viajan de sucursal a la casa matriz.">
            @can('despachar traslado servicio')
                <x-slot name="action">
                    <x-button-link :href="route('admin.traslados.create')">
                        <x-icon.plus class="h-4 w-4" />
                        Despachar máquinas
                    </x-button-link>
                </x-slot>
            @endcan
        </x-page-header>
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        {{-- Lo que está EN CAMINO y espera confirmación. Va arriba porque es lo
             accionable: mientras no se confirme, esas máquinas no se pueden
             reparar (regla del dueño). --}}
        @if ($enTransito->isNotEmpty())
            <x-list-card title="En camino · falta confirmar la recepción" :count="$enTransito->count()"
                         :countLabel="\Illuminate\Support\Str::plural('traslado', $enTransito->count())">
                @foreach ($enTransito as $t)
                    <x-list-row>
                        <a href="{{ route('admin.traslados.show', $t) }}" class="block">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs text-neutral-400">{{ $t->codigo }}</span>
                                <p class="truncate font-medium text-neutral-900 hover:text-brand-600">
                                    {{ $t->ordenes_count }} {{ \Illuminate\Support\Str::plural('máquina', $t->ordenes_count) }}
                                    desde {{ $t->origen?->nombre }}
                                </p>
                                <x-badge :variant="$t->estado_variante">{{ $t->estado_label }}</x-badge>
                            </div>
                            <p class="truncate text-sm text-neutral-500">
                                Despachó {{ $t->emisor_nombre }}
                                @if ($t->conductor) · Conductor: {{ $t->conductor }} @endif
                            </p>
                        </a>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-32 sm:shrink-0 sm:text-right">
                                {{ $t->despachado_at?->enChile()->format('d-m-Y H:i') }}
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                        </x-slot>
                    </x-list-row>
                @endforeach
            </x-list-card>
        @endif

        {{-- Máquinas paradas en sucursal SIN despachar. Antes no se veían en
             ninguna pantalla: es el otro lado del agujero. --}}
        @if ($sinDespachar->isNotEmpty())
            <x-list-card title="En sucursal · sin despachar al taller" :count="$sinDespachar->count()"
                         :countLabel="\Illuminate\Support\Str::plural('máquina', $sinDespachar->count())">
                @foreach ($sinDespachar as $orden)
                    <x-list-row>
                        <a href="{{ route('admin.servicio-tecnico.show', $orden) }}" class="block">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs text-neutral-400">{{ $orden->folio }}</span>
                                <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $orden->cliente_nombre }}</p>
                                <x-badge variant="warning">En {{ $orden->sucursal?->nombre }}</x-badge>
                            </div>
                            <p class="truncate text-sm text-neutral-500">
                                {{ $orden->tipo_equipo_label }}@if ($orden->numero_serie) · N° {{ $orden->numero_serie }}@endif
                                · ingresó {{ $orden->fecha_ingreso?->format('d-m-Y') }}
                            </p>
                        </a>
                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-32 sm:shrink-0 sm:text-right">
                                {{ $orden->fecha_ingreso?->diffForHumans() }}
                            </div>
                        </x-slot>
                    </x-list-row>
                @endforeach
            </x-list-card>
        @endif

        {{-- Historial completo. --}}
        <x-list-card title="Historial de traslados" :count="$traslados->total()"
                     :countLabel="\Illuminate\Support\Str::plural('traslado', $traslados->total())">
            @forelse ($traslados as $t)
                <x-list-row>
                    <a href="{{ route('admin.traslados.show', $t) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-neutral-400">{{ $t->codigo }}</span>
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">
                                {{ $t->origen?->nombre }} → {{ $t->destino?->nombre }}
                            </p>
                            <x-badge :variant="$t->estado_variante">{{ $t->estado_label }}</x-badge>
                        </div>
                        <p class="truncate text-sm text-neutral-500">
                            {{ $t->emisor_nombre }} despachó {{ $t->total_enviado }}
                            @if ($t->recibido)
                                · {{ $t->receptor_nombre }} recibió {{ $t->total_recibido }}
                                @if ($t->tiene_diferencia)
                                    <span class="font-medium text-red-700">· faltan {{ $t->faltantes }}</span>
                                @endif
                            @endif
                        </p>
                    </a>

                    <x-slot name="meta">
                        <div class="text-sm text-neutral-500 sm:w-32 sm:shrink-0 sm:text-right">
                            {{ $t->despachado_at?->enChile()->format('d-m-Y') }}
                        </div>
                    </x-slot>

                    <x-slot name="actions">
                        <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    Aún no hay traslados registrados. El primero se crea despachando máquinas desde una sucursal.
                </li>
            @endforelse
        </x-list-card>

        @if ($traslados->hasPages())
            <div>{{ $traslados->links() }}</div>
        @endif
    </div>
</x-app-layout>
