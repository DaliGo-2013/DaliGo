<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Agenda de terreno" subtitle="Mantenciones, reparaciones e instalaciones del técnico industrial.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('dashboard')" size="lg" variant="secondary" label="Volver al inicio" title="Volver al inicio">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    @can('agendar servicio terreno')
                        <x-secondary-link :href="route('admin.servicios-terreno.index')">Catálogo de servicios</x-secondary-link>
                        <x-button-link :href="route('admin.agenda-terreno.create')">
                            <x-icon.plus class="h-4 w-4" />
                            Agendar trabajo
                        </x-button-link>
                    @endcan
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-5 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Navegación por mes --}}
            <div class="flex items-center justify-between rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm">
                <a href="{{ route('admin.agenda-terreno.index', $anterior) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100">&larr; Mes anterior</a>
                <h3 class="text-base font-semibold text-neutral-900">{{ $mesLabel }}</h3>
                <a href="{{ route('admin.agenda-terreno.index', $siguiente) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100">Mes siguiente &rarr;</a>
            </div>

            @if ($trabajos->isEmpty())
                <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500 shadow-sm">
                    Sin trabajos agendados en {{ $mesLabel }}.
                    @can('agendar servicio terreno')
                        Usa «Agendar trabajo» para crear el primero.
                    @endcan
                </div>
            @endif

            {{-- Lista agrupada por día --}}
            @foreach ($trabajos->groupBy(fn ($t) => $t->fecha->toDateString()) as $dia => $delDia)
                @php $fechaDia = \Illuminate\Support\Carbon::parse($dia); @endphp
                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm {{ $fechaDia->isToday() ? 'ring-2 ring-brand-300' : '' }}">
                    <div class="flex items-center justify-between border-b border-neutral-100 bg-neutral-50 px-4 py-2.5 sm:px-6">
                        <h3 class="text-xs font-semibold uppercase tracking-wide {{ $fechaDia->isToday() ? 'text-brand-700' : 'text-neutral-500' }}">
                            {{ ucfirst($fechaDia->translatedFormat('l d \d\e F')) }}@if ($fechaDia->isToday()) · HOY @endif
                        </h3>
                        <span class="text-xs text-neutral-400">{{ $delDia->count() }} {{ $delDia->count() === 1 ? 'trabajo' : 'trabajos' }}</span>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($delDia as $t)
                            <li class="px-4 py-3 sm:px-6">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-badge :variant="$t->estado_variante">{{ ucfirst($t->estado) }}</x-badge>
                                            <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $t->tipo_label }}</span>
                                            <p class="truncate font-medium text-neutral-900">{{ $t->cliente_nombre }}</p>
                                        </div>
                                        <p class="mt-0.5 truncate text-sm text-neutral-600">
                                            {{ collect([
                                                $t->servicio?->nombre,
                                                $t->direccion,
                                                $t->ciudad,
                                            ])->filter()->implode(' · ') }}
                                        </p>
                                        @if ($t->descripcion)
                                            <p class="mt-0.5 text-sm text-neutral-500">{{ $t->descripcion }}</p>
                                        @endif
                                        <p class="mt-0.5 text-xs text-neutral-400">
                                            {{ collect([
                                                $t->tecnico ? 'Técnico: '.$t->tecnico->name : null,
                                                $t->cliente_telefono,
                                                $t->creado_por ? 'Agendó: '.$t->creado_por : null,
                                            ])->filter()->implode(' · ') }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        @if ($t->estado === 'agendado')
                                            <form method="POST" action="{{ route('admin.agenda-terreno.estado', $t) }}"
                                                  onsubmit="return confirm('¿Marcar como realizado el trabajo de {{ $t->cliente_nombre }}?');">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="estado" value="realizado">
                                                <x-primary-button>Realizado</x-primary-button>
                                            </form>
                                        @endif
                                        @can('agendar servicio terreno')
                                            <x-secondary-link :href="route('admin.agenda-terreno.edit', $t)">Editar</x-secondary-link>
                                        @endcan
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
