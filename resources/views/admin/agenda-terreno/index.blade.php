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
                    {{-- Carrusel horizontal: se ven ~2 solicitudes; "Ver más →" y "←"
                         deslizan para revisar el resto (aparecen solo si hay más de 2). --}}
                    <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 shadow-sm sm:p-5"
                         x-data="{
                            inicio: true, fin: false, hayMas: false,
                            init() { this.$nextTick(() => this.actualizar()); window.addEventListener('resize', () => this.actualizar()); },
                            actualizar() {
                                const el = this.$refs.pista; if (! el) return;
                                this.hayMas = el.scrollWidth > el.clientWidth + 4;
                                this.inicio = el.scrollLeft <= 4;
                                this.fin = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
                            },
                            mover(dir) { this.$refs.pista.scrollBy({ left: dir * this.$refs.pista.clientWidth * 0.9, behavior: 'smooth' }); }
                         }">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-brand-600 px-1.5 text-xs font-semibold text-white">{{ $porCoordinar->count() }}</span>
                            <h3 class="text-sm font-semibold text-brand-700">Por coordinar (solicitudes del cliente)</h3>
                            <div class="ml-auto flex items-center gap-1.5" x-show="hayMas" x-cloak>
                                <button type="button" x-on:click="mover(-1)" x-show="! inicio"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-brand-300 bg-white text-brand-700 transition hover:bg-brand-50" title="Anterior" aria-label="Anterior">&larr;</button>
                                <button type="button" x-on:click="mover(1)" x-show="! fin"
                                        class="inline-flex items-center gap-1 rounded-lg border border-brand-300 bg-white px-2.5 py-1 text-xs font-medium text-brand-700 transition hover:bg-brand-50">Ver más &rarr;</button>
                            </div>
                        </div>
                        <ul x-ref="pista" x-on:scroll.debounce.50ms="actualizar()"
                            class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            @foreach ($porCoordinar as $s)
                                <li class="flex shrink-0 basis-[86%] snap-start flex-col gap-3 rounded-xl border border-brand-200 bg-white p-3 sm:basis-[calc(50%-0.375rem)]">
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

            {{-- ===== Calendario (izq) + DÍA SELECCIONADO con formularios (der) ===== --}}
            @php $isoSel = $diaSel->toDateString(); @endphp
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
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
                                    $sel = $iso === $isoSel;
                                    $numClase = $sel ? 'font-bold text-brand-700' : ($d->isToday() ? 'font-bold text-brand-600' : ($delMes ? 'text-neutral-800' : 'text-neutral-300'));
                                @endphp
                                @if ($delMes)
                                    {{-- Al tocar un día se selecciona (?dia=) y se ve/edita a la derecha. --}}
                                    <a href="{{ route('admin.agenda-terreno.index', ['anio' => $anio, 'mes' => $mes, 'dia' => $iso]) }}"
                                       class="flex min-h-14 flex-col items-center gap-1 rounded-lg border p-1.5 transition {{ $sel ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-300' : 'border-transparent hover:bg-neutral-50' }}"
                                       title="{{ ucfirst($d->translatedFormat('l d \d\e F')) }}">
                                        <span class="text-sm {{ $numClase }}">{{ $d->day }}</span>
                                        @if ($count > 0)
                                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-semibold text-white">{{ $count }}</span>
                                        @endif
                                    </a>
                                @else
                                    <div class="flex min-h-14 flex-col items-center gap-1 rounded-lg p-1.5">
                                        <span class="text-sm text-neutral-300">{{ $d->day }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-neutral-400">El número indica cuántos trabajos hay ese día. Toca un día para verlo y editarlo a la derecha.</p>
                    </div>
                </div>

                {{-- ---- Día seleccionado (derecha): sus trabajos como FORMULARIOS
                         editables + un formulario para agregar. Solo un día por vez. ---- --}}
                <div class="space-y-4 lg:col-span-7">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold {{ \App\Support\FechaNegocio::esHoy($diaSel) ? 'text-brand-700' : 'text-neutral-900' }}">
                            {{ ucfirst($diaSel->translatedFormat('l d \d\e F')) }}@if (\App\Support\FechaNegocio::esHoy($diaSel)) · HOY @endif
                        </h3>
                        <span class="text-xs text-neutral-400">{{ $trabajosDia->count() }} {{ $trabajosDia->count() === 1 ? 'trabajo' : 'trabajos' }}</span>
                    </div>

                    @unless ($puedeAgendar)
                        {{-- Solo lectura (sin permiso de agendar): resumen del día. --}}
                        @forelse ($trabajosDia as $t)
                            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge :variant="$t->estado_variante">{{ ucfirst($t->estado) }}</x-badge>
                                    <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $t->tipo_label }}</span>
                                    <span class="font-medium text-neutral-900">{{ $t->cliente_nombre }}</span>
                                </div>
                                <p class="mt-1 text-sm text-neutral-600">{{ collect([$t->rango_horas_label ? $t->rango_horas_label.' hs' : null, $t->servicio?->nombre, $t->direccion, $t->ciudad])->filter()->implode(' · ') }}</p>
                                @if ($t->descripcion)<p class="mt-1 text-sm text-neutral-500">{{ $t->descripcion }}</p>@endif
                            </div>
                        @empty
                            <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500 shadow-sm">Sin trabajo por realizar este día.</div>
                        @endforelse
                    @else
                        {{-- Trabajos existentes del día: cada uno como formulario editable. --}}
                        @foreach ($trabajosDia as $t)
                            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6"
                                 x-data="agendaTerrenoForm({
                                    endpointCliente: '{{ route('admin.agenda-terreno.buscar-cliente') }}',
                                    servicios: @js($serviciosJs),
                                    clienteId: {{ (int) ($t->cliente_id ?? 0) }},
                                    servicioId: @js((string) ($t->servicio_terreno_id ?? '')),
                                 })">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Editar trabajo · {{ $t->tipo_label }}</p>
                                    <x-badge :variant="$t->estado_variante">{{ ucfirst($t->estado) }}</x-badge>
                                </div>
                                <form method="POST" action="{{ route('admin.agenda-terreno.update', $t) }}">
                                    @csrf
                                    @method('PUT')
                                    @include('admin.agenda-terreno._form', ['trabajo' => $t])
                                    <div class="mt-6 flex items-center gap-3">
                                        <x-primary-button>Guardar cambios</x-primary-button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('admin.agenda-terreno.destroy', $t) }}" class="mt-3 text-right"
                                      onsubmit="return confirm('¿Eliminar este trabajo de la agenda?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-700">Eliminar de la agenda</button>
                                </form>
                            </div>
                        @endforeach

                        {{-- Agregar un trabajo para este día (formulario prellenado con la fecha).
                             Si el día ya tiene trabajos, viene colapsado tras un botón. --}}
                        <div x-data="{ abierto: {{ $trabajosDia->isEmpty() ? 'true' : 'false' }} }">
                            <button type="button" x-show="! abierto" x-on:click="abierto = true"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 transition hover:bg-brand-100">
                                <x-icon.plus class="h-4 w-4" /> Agregar trabajo el {{ $diaSel->translatedFormat('d \d\e F') }}
                            </button>
                            <div x-show="abierto" x-cloak
                                 class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6"
                                 x-data="agendaTerrenoForm({
                                    endpointCliente: '{{ route('admin.agenda-terreno.buscar-cliente') }}',
                                    servicios: @js($serviciosJs),
                                    clienteId: 0,
                                    servicioId: '',
                                 })">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Nuevo trabajo</p>
                                <form method="POST" action="{{ route('admin.agenda-terreno.store') }}">
                                    @csrf
                                    @include('admin.agenda-terreno._form', ['trabajo' => null, 'fechaDefault' => $isoSel])
                                    <div class="mt-6 flex items-center gap-3">
                                        <x-primary-button>Agendar trabajo</x-primary-button>
                                        @unless ($trabajosDia->isEmpty())
                                            <button type="button" x-on:click="abierto = false"
                                                    class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-500 hover:text-neutral-700">Cancelar</button>
                                        @endunless
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
