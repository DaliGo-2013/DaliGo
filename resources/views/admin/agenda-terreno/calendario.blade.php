<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Agenda de terreno" subtitle="Calendario del técnico industrial — mantenciones, reparaciones, instalaciones y visitas.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('dashboard')" size="lg" variant="secondary" label="Volver al inicio" title="Volver al inicio">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-secondary-link :href="route('admin.agenda-terreno.index')">Vista lista</x-secondary-link>
                    @can('agendar servicio terreno')
                        <x-button-link :href="route('admin.agenda-terreno.create', ['fecha' => $diaSel->toDateString()])">
                            <x-icon.plus class="h-4 w-4" />
                            Agendar trabajo
                        </x-button-link>
                    @endcan
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Por coordinar (solicitudes del cliente por QR, sin fecha) --}}
            @can('agendar servicio terreno')
                @if ($porCoordinar->isNotEmpty())
                    <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-brand-600 px-1.5 text-xs font-semibold text-white">{{ $porCoordinar->count() }}</span>
                            <h3 class="text-sm font-semibold text-brand-700">Por coordinar (solicitudes del cliente)</h3>
                            <a href="{{ route('admin.agenda-terreno.index') }}" class="ml-auto text-xs font-medium text-brand-600 hover:text-brand-700">Ver todas &rarr;</a>
                        </div>
                    </div>
                @endif
            @endcan

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
                {{-- ===== Calendario del mes ===== --}}
                <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm lg:col-span-3 sm:p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <a href="{{ route('admin.agenda-terreno.calendario', $anterior) }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100">&larr;</a>
                        <h3 class="text-base font-semibold text-neutral-900">{{ $mesLabel }}</h3>
                        <a href="{{ route('admin.agenda-terreno.calendario', $siguiente) }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100">&rarr;</a>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-neutral-400">
                        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dn)
                            <div class="py-1">{{ $dn }}</div>
                        @endforeach
                    </div>

                    <div class="mt-1 grid grid-cols-7 gap-1">
                        @foreach ($grid as $d)
                            @php
                                $delMes = $d->month === $mes;
                                $count = ($jobsPorDia->get($d->toDateString()) ?? collect())->count();
                                $esSel = $d->toDateString() === $diaSel->toDateString();
                            @endphp
                            <a href="{{ route('admin.agenda-terreno.calendario', ['anio' => $d->year, 'mes' => $d->month, 'dia' => $d->toDateString()]) }}"
                               class="flex min-h-14 flex-col items-center gap-1 rounded-lg border p-1.5 transition
                                      {{ $esSel ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-300' : 'border-transparent hover:bg-neutral-50' }}">
                                <span class="text-sm {{ $delMes ? ($d->isToday() ? 'font-bold text-brand-600' : 'text-neutral-800') : 'text-neutral-300' }}">{{ $d->day }}</span>
                                @if ($count > 0)
                                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-semibold text-white">{{ $count }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-neutral-400">El número indica cuántos trabajos hay ese día. Toca un día para verlo a la derecha.</p>
                </div>

                {{-- ===== Día seleccionado (franjas horarias) ===== --}}
                <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm lg:col-span-2">
                    <div class="border-b border-neutral-100 bg-neutral-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-neutral-900">{{ ucfirst($diaSel->translatedFormat('l d \d\e F')) }}@if ($diaSel->isToday()) · HOY @endif</h3>
                    </div>

                    @php $sinHora = $trabajosDia->filter(fn ($t) => ! $t->hora_corta); @endphp
                    @if ($sinHora->isNotEmpty())
                        <div class="border-b border-neutral-100 bg-neutral-50/60 px-4 py-2">
                            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-neutral-400">Sin hora asignada</p>
                            <div class="space-y-1">
                                @foreach ($sinHora as $t)
                                    <a href="{{ route('admin.agenda-terreno.edit', $t) }}" class="block rounded-lg border border-neutral-200 bg-white p-2 text-sm transition hover:border-brand-300">
                                        <x-badge :variant="$t->estado_variante">{{ ucfirst($t->estado) }}</x-badge>
                                        <span class="ml-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $t->tipo_label }}</span>
                                        <span class="font-medium text-neutral-900">{{ $t->cliente_nombre }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <ul class="divide-y divide-neutral-100">
                        @foreach ($horas as $h)
                            @php $delSlot = $trabajosDia->filter(fn ($t) => $t->hora_corta && substr($t->hora_corta, 0, 2) === substr($h, 0, 2)); @endphp
                            <li class="flex gap-3 px-4 py-2">
                                <span class="w-11 shrink-0 pt-1 text-xs font-medium text-neutral-400">{{ $h }}</span>
                                <div class="min-w-0 flex-1 space-y-1">
                                    @forelse ($delSlot as $t)
                                        <a href="{{ route('admin.agenda-terreno.edit', $t) }}" class="block rounded-lg border border-neutral-200 bg-white p-2 text-sm transition hover:border-brand-300">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <x-badge :variant="$t->estado_variante">{{ ucfirst($t->estado) }}</x-badge>
                                                <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $t->tipo_label }}</span>
                                                <span class="truncate font-medium text-neutral-900">{{ $t->cliente_nombre }}</span>
                                            </div>
                                            @if ($t->servicio || $t->ciudad)
                                                <p class="truncate text-xs text-neutral-500">{{ collect([$t->servicio?->nombre, $t->ciudad])->filter()->implode(' · ') }}</p>
                                            @endif
                                        </a>
                                    @empty
                                        @can('agendar servicio terreno')
                                            <a href="{{ route('admin.agenda-terreno.create', ['fecha' => $diaSel->toDateString(), 'hora' => $h]) }}"
                                               class="block rounded-lg border border-dashed border-neutral-200 p-2 text-xs text-neutral-300 transition hover:border-brand-300 hover:text-brand-500">
                                                + Agendar
                                            </a>
                                        @else
                                            <span class="block px-1 text-xs text-neutral-200">—</span>
                                        @endcan
                                    @endforelse
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
