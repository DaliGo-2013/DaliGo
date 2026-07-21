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
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Por coordinar: solicitudes que dejó el CLIENTE por el QR (sin
                 fecha). Quien agenda las revisa, llama al cliente y les pone
                 fecha + técnico desde "Coordinar" (el form de edición). --}}
            @can('agendar servicio terreno')
                @if ($porCoordinar->isNotEmpty())
                    <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 shadow-sm sm:p-5">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-brand-600 px-1.5 text-xs font-semibold text-white">{{ $porCoordinar->count() }}</span>
                            <h3 class="text-sm font-semibold text-brand-700">Por coordinar (solicitudes del cliente)</h3>
                        </div>
                        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($porCoordinar as $s)
                                <li class="flex flex-col gap-3 rounded-xl border border-brand-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $s->tipo_label }}</span>
                                            <span class="truncate font-medium text-neutral-900">{{ $s->cliente_nombre }}</span>
                                        </div>
                                        <p class="truncate text-sm text-neutral-600">
                                            {{ collect([$s->servicio?->nombre, $s->direccion, $s->ciudad])->filter()->implode(' · ') }}
                                        </p>
                                        @if ($s->descripcion)
                                            <p class="truncate text-sm text-neutral-500">{{ $s->descripcion }}</p>
                                        @endif
                                        <p class="text-xs text-neutral-400">
                                            {{ collect([
                                                $s->cliente_telefono,
                                                $s->fecha_preferida ? 'Prefiere: '.$s->fecha_preferida->format('d-m-Y') : null,
                                            ])->filter()->implode(' · ') }}
                                        </p>
                                    </div>
                                    <div class="shrink-0">
                                        <x-button-link :href="route('admin.agenda-terreno.edit', $s)">Coordinar</x-button-link>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endcan

            {{-- ===== Vista única: calendario (izq) + lista del mes (der) ===== --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-12" x-data="{ diaMsg: '' }">
                {{-- ---- Calendario del mes (izquierda, pegajoso al hacer scroll) ---- --}}
                <div class="lg:col-span-5">
                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5 lg:sticky lg:top-6">
                        <div class="mb-3 flex items-center justify-between">
                            <a href="{{ route('admin.agenda-terreno.index', $anterior) }}"
                               class="rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100" title="Mes anterior">&larr;</a>
                            <h3 class="text-base font-semibold text-neutral-900">{{ $mesLabel }}</h3>
                            <a href="{{ route('admin.agenda-terreno.index', $siguiente) }}"
                               class="rounded-lg px-3 py-1.5 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100" title="Mes siguiente">&rarr;</a>
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
                                    $iso = $d->toDateString();
                                    $count = ($jobsPorDia->get($iso) ?? collect())->count();
                                    $numClase = $d->isToday() ? 'font-bold text-brand-600' : ($delMes ? 'text-neutral-800' : 'text-neutral-300');
                                @endphp
                                @php $diaLabel = ucfirst($d->translatedFormat('l d \d\e F')); @endphp
                                @if ($count > 0)
                                    {{-- Día con trabajos: salta a ese día en la lista de la derecha. --}}
                                    <a href="#dia-{{ $iso }}" x-on:click="diaMsg = ''"
                                       class="flex min-h-14 flex-col items-center gap-1 rounded-lg border border-transparent p-1.5 transition hover:bg-neutral-50"
                                       title="Ver los {{ $count }} {{ $count === 1 ? 'trabajo' : 'trabajos' }} del {{ $d->translatedFormat('d \d\e F') }}">
                                        <span class="text-sm {{ $numClase }}">{{ $d->day }}</span>
                                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-semibold text-white">{{ $count }}</span>
                                    </a>
                                @elseif ($delMes)
                                    {{-- Día sin trabajos: al tocarlo avisa "sin trabajo por realizar" a la derecha. --}}
                                    <button type="button"
                                            x-on:click="diaMsg = '{{ $diaLabel }}: sin trabajo por realizar.'"
                                            class="flex min-h-14 w-full flex-col items-center gap-1 rounded-lg border border-transparent p-1.5 transition hover:bg-neutral-50"
                                            title="{{ $diaLabel }}">
                                        <span class="text-sm {{ $numClase }}">{{ $d->day }}</span>
                                    </button>
                                @else
                                    <div class="flex min-h-14 flex-col items-center gap-1 rounded-lg p-1.5">
                                        <span class="text-sm text-neutral-300">{{ $d->day }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-neutral-400">El número indica cuántos trabajos hay ese día. Toca un día con trabajos para saltar a él; un día sin número te avisa que está libre.</p>
                    </div>
                </div>

                {{-- ---- Lista del mes agrupada por día (derecha) ---- --}}
                <div class="space-y-5 lg:col-span-7">
                    {{-- Aviso al tocar un día sin trabajos en el calendario. --}}
                    <div x-show="diaMsg" x-cloak
                         class="flex items-center justify-between gap-3 rounded-2xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-600 shadow-sm">
                        <span x-text="diaMsg"></span>
                        <button type="button" x-on:click="diaMsg = ''"
                                class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-neutral-400 transition hover:text-neutral-600">Cerrar</button>
                    </div>

                    @if ($trabajos->isEmpty())
                        <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500 shadow-sm">
                            Sin trabajos agendados en {{ $mesLabel }}.
                            @can('agendar servicio terreno')
                                Usa «Agendar trabajo» para crear el primero.
                            @endcan
                        </div>
                    @endif

                    @foreach ($jobsPorDia as $dia => $delDia)
                        @php $fechaDia = \Illuminate\Support\Carbon::parse($dia); @endphp
                        <div id="dia-{{ $dia }}"
                             class="dg-enter scroll-mt-6 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm transition target:ring-2 target:ring-brand-400 {{ \App\Support\FechaNegocio::esHoy($fechaDia) ? 'ring-2 ring-brand-300' : '' }}">
                            <div class="flex items-center justify-between border-b border-neutral-100 bg-neutral-50 px-4 py-2.5 sm:px-6">
                                <h3 class="text-xs font-semibold uppercase tracking-wide {{ \App\Support\FechaNegocio::esHoy($fechaDia) ? 'text-brand-700' : 'text-neutral-500' }}">
                                    {{ ucfirst($fechaDia->translatedFormat('l d \d\e F')) }}@if (\App\Support\FechaNegocio::esHoy($fechaDia)) · HOY @endif
                                </h3>
                                <span class="text-xs text-neutral-400">{{ $delDia->count() }} {{ $delDia->count() === 1 ? 'trabajo' : 'trabajos' }}</span>
                            </div>
                            {{-- Trabajos del día separados en franjas de 2 horas (08/10/12/14/16/18),
                                 con los "Sin hora" al final. La franja sale del accessor del modelo. --}}
                            @php $porFranja = $delDia->groupBy(fn ($j) => $j->franja ?? 'Sin hora')->sortKeys(); @endphp
                            <div class="divide-y divide-neutral-100">
                                @foreach ($porFranja as $franja => $enFranja)
                                    <div class="px-4 py-2.5 sm:px-6">
                                        <p class="text-xs font-semibold uppercase tracking-wide {{ $franja === 'Sin hora' ? 'text-neutral-400' : 'text-brand-600' }}">
                                            {{ $franja === 'Sin hora' ? 'Sin hora' : $franja.' hs' }}
                                        </p>
                                        <ul class="mt-1.5 space-y-3">
                                            @foreach ($enFranja as $t)
                                    <li class="rounded-lg" x-data="cierreTerrenoForm()">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <x-badge :variant="$t->estado_variante">{{ ucfirst($t->estado) }}</x-badge>
                                                    <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $t->tipo_label }}</span>
                                                    <p class="truncate font-medium text-neutral-900">{{ $t->cliente_nombre }}</p>
                                                </div>
                                                {{-- Qué hay que hacer (la hora la da el encabezado de la franja). --}}
                                                <p class="mt-0.5 truncate text-sm text-neutral-600">
                                                    {{ collect([$t->servicio?->nombre, $t->direccion, $t->ciudad])->filter()->implode(' · ') ?: '—' }}
                                                </p>
                                                {{-- Viaje de varios días y/o horario extendido (día completo). --}}
                                                @php $rangoHoras = $t->hora_fin_corta && $t->hora_fin_corta !== $t->hora_corta; @endphp
                                                @if ($t->abarca_varios_dias || $rangoHoras)
                                                    <p class="mt-1 flex flex-wrap items-center gap-2">
                                                        @if ($t->abarca_varios_dias)
                                                            <span class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-brand-200">En terreno: {{ $t->rango_fechas_label }}</span>
                                                        @endif
                                                        @if ($rangoHoras)
                                                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">Horario: {{ $t->rango_horas_label }} hs</span>
                                                        @endif
                                                    </p>
                                                @endif
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
                                                @if ($t->repuestos->isNotEmpty())
                                                    <p class="mt-0.5 text-xs text-neutral-400">Repuestos: {{ $t->repuestos->map(fn ($r) => $r->cantidad.'× '.$r->nombre)->implode(', ') }}</p>
                                                @endif
                                            </div>
                                            <div class="flex shrink-0 items-center gap-2">
                                                @if ($t->estado === 'agendado')
                                                    <x-primary-button type="button" x-show="!abierto" x-on:click="abierto = true">Realizado</x-primary-button>
                                                @endif
                                                @can('agendar servicio terreno')
                                                    <x-secondary-link :href="route('admin.agenda-terreno.edit', $t)">Editar</x-secondary-link>
                                                @endcan
                                            </div>
                                        </div>

                                        @if ($t->estado === 'agendado')
                                            {{-- Panel de cierre: el técnico registra los repuestos usados
                                                 (nombre + cantidad) y notas al marcar el trabajo Realizado. --}}
                                            <div x-show="abierto" x-cloak class="mt-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                                                <form method="POST" action="{{ route('admin.agenda-terreno.estado', $t) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="estado" value="realizado">
                                                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Cerrar trabajo</p>

                                                    <div class="mt-2">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-sm font-medium text-neutral-700">Repuestos usados</span>
                                                            <button type="button" x-on:click="agregar()"
                                                                class="inline-flex items-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 shadow-sm hover:bg-neutral-50">
                                                                <x-icon.plus class="h-4 w-4" /> Agregar
                                                            </button>
                                                        </div>
                                                        <div class="mt-2 space-y-2">
                                                            <template x-for="(r, i) in repuestos" :key="i">
                                                                <div class="flex items-center gap-2">
                                                                    <input type="text" x-model="r.nombre" :name="`repuestos[${i}][nombre]`"
                                                                        placeholder="Ej. Membrana, filtro de papel" maxlength="191"
                                                                        class="block flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                                                    <input type="number" min="1" x-model.number="r.cantidad" :name="`repuestos[${i}][cantidad]`"
                                                                        class="block w-20 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                                                    <button type="button" x-on:click="quitar(i)"
                                                                        class="shrink-0 rounded-lg p-2 text-neutral-400 hover:bg-red-50 hover:text-red-600" title="Quitar">
                                                                        <x-icon.trash class="h-5 w-5" />
                                                                    </button>
                                                                </div>
                                                            </template>
                                                            <p x-show="repuestos.length === 0" class="text-sm text-neutral-400">Sin repuestos. Usa «Agregar» si usaste alguno.</p>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3">
                                                        <label for="notas-{{ $t->id }}" class="text-sm font-medium text-neutral-700">Notas (opcional)</label>
                                                        <textarea id="notas-{{ $t->id }}" name="notas_tecnico" rows="2"
                                                            class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">{{ $t->notas_tecnico }}</textarea>
                                                    </div>

                                                    <div class="mt-3 flex items-center gap-2">
                                                        <x-primary-button>Confirmar realizado</x-primary-button>
                                                        <button type="button" x-on:click="abierto = false"
                                                            class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-500 hover:text-neutral-700">Cancelar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        @endif
                                    </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
