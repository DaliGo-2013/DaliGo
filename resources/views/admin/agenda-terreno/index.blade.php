<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Agenda de terreno" subtitle="Mantenciones, reparaciones e instalaciones del técnico industrial.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    {{-- El link «Catálogo de servicios» que vivía acá lo absorbió
                         la pestaña «Servicios de terreno» del tab-nav (Lote 5,
                         PLAN-MENU-DENSIDAD) — misma URL, mismo @can. --}}
                    {{-- Cuándo NO se atiende (feriados, vacaciones, media jornada). Va acá y
                         no escondido en un menú: es del jefe de ventas y se toca cuando se
                         está mirando la agenda, no buscándolo en otra parte. --}}
                    @can('gestionar cierres agenda')
                        <a href="{{ route('admin.agenda-terreno.cierres.index') }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                            Cuándo no se atiende
                        </a>
                    @endcan
                    @can('agendar servicio terreno')
                        <x-button-link :href="route('admin.agenda-terreno.create')">
                            <x-icon.plus class="h-4 w-4" />
                            Agendar trabajo
                        </x-button-link>
                    @endcan
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5 py-8 sm:py-12">
        <x-status-alert :status="session('status')" />

        @include('admin.agenda-terreno._tabs')

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
                                        @if ($s->cliente_id || in_array($s->cliente_rut, $rutsEnCatalogo, true))
                                            <span class="inline-flex items-center gap-0.5 rounded-full bg-brand-100 px-1.5 py-0.5 text-[11px] font-medium text-brand-700" title="Este cliente ya está en tu catálogo">
                                                <x-icon.check class="h-3 w-3" /> en catálogo
                                            </span>
                                        @endif
                                        {{-- ESTA NO LA PIDIÓ EL CLIENTE: la fijó un vendedor y
                                             espera el visto bueno del jefe de ventas. Vive en
                                             este bloque porque está 'solicitado' y sin fecha,
                                             pero sin decirlo se lee como un pedido del cliente
                                             y alguien intentaría «coordinarla» sin saber que la
                                             fecha ya está pedida y esperando. --}}
                                        @if ($s->esperandoAutorizacion())
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800"
                                                  title="La fijó un vendedor; el jefe de ventas la tiene que autorizar antes de que ocupe la agenda">
                                                esperando autorización
                                            </span>
                                        @endif
                                    </div>
                                    <p class="truncate text-sm text-neutral-600">
                                        {{ collect([$s->servicio?->nombre, $s->direccion, $s->ciudad])->filter()->implode(' · ') }}
                                    </p>
                                    @if ($s->descripcion)
                                        <p class="truncate text-sm text-neutral-500">{{ $s->descripcion }}</p>
                                    @endif
                                    @if ($s->disponibilidad)
                                        <p class="text-sm text-neutral-500"><span class="font-medium text-neutral-600">Disponibilidad:</span> {{ $s->disponibilidad }}</p>
                                    @endif
                                    {{-- El teléfono es tocable: quien coordina llama desde acá. --}}
                                    <div class="flex flex-wrap items-center gap-x-3">
                                        {{-- Sin `text-xs` a propósito: el tamaño del componente
                                             (text-sm) gana igual por orden de Tailwind, y para un
                                             blanco táctil es el tamaño que corresponde. --}}
                                        <x-tel-link :telefono="$s->cliente_telefono" />
                                        @if ($s->fecha_preferida)
                                            {{-- La hora que eligió el cliente va PEGADA a la fecha:
                                                 es el mismo dato («cuándo le acomoda») y quien
                                                 llama lo necesita junto, no en dos renglones. --}}
                                            <span class="text-xs text-neutral-400">Prefiere: {{ $s->fecha_preferida->format('d-m-Y') }}@if ($s->hora_preferida_corta) a las {{ $s->hora_preferida_corta }}@endif</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0" x-data="{ rechazar: false }">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-button-link :href="route('admin.agenda-terreno.edit', $s)">Coordinar</x-button-link>
                                        <button type="button" x-on:click="rechazar = ! rechazar"
                                                class="inline-flex items-center rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-50">
                                            No se puede
                                        </button>
                                    </div>

                                    {{-- Rechazo con motivo: marca la solicitud cancelada y le avisa al
                                         cliente por correo (con el motivo), como la cara "no" de coordinar. --}}
                                    <div x-show="rechazar" x-cloak class="mt-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3"
                                         x-data="{ motivo: '' }">
                                        <form method="POST" action="{{ route('admin.agenda-terreno.rechazar', $s) }}"
                                              onsubmit="return confirm('¿Rechazar esta solicitud y avisarle al cliente por correo?');">
                                            @csrf
                                            <label for="motivo-{{ $s->id }}" class="block text-xs font-medium text-neutral-600">Motivo del rechazo</label>
                                            <x-select id="motivo-{{ $s->id }}" name="motivo" x-model="motivo" required class="mt-1 w-full">
                                                <option value="">— Elige un motivo —</option>
                                                @foreach (\App\Models\AgendaTrabajo::MOTIVOS_CANCELACION as $k => $label)
                                                    <option value="{{ $k }}">{{ $label }}</option>
                                                @endforeach
                                            </x-select>
                                            <x-text-input name="motivo_otro" type="text" maxlength="191"
                                                x-show="motivo === 'otro'" x-cloak
                                                placeholder="Especifica el motivo" class="mt-2 w-full" />
                                            <div class="mt-3 flex items-center gap-3">
                                                <button type="submit"
                                                        class="inline-flex items-center rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                                                    Rechazar y avisar
                                                </button>
                                                <button type="button" x-on:click="rechazar = false"
                                                        class="text-sm font-medium text-neutral-500 hover:text-neutral-700">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endcan

        {{-- ===== Calendario (izq) + DÍA SELECCIONADO con formularios (der) ===== --}}
        @php $isoSel = $diaSel->toDateString(); @endphp
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            {{-- ---- Calendario del mes (izquierda, pegajoso al hacer scroll) ---- --}}
            <div class="xl:col-span-5">
                <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-5 xl:sticky xl:top-6">
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
                                $numClase = $sel ? 'font-bold text-brand-700' : (\App\Support\FechaNegocio::esHoy($d) ? 'font-bold text-brand-600' : ($delMes ? 'text-neutral-800' : 'text-neutral-300'));
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
            <div class="space-y-4 xl:col-span-7">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold {{ \App\Support\FechaNegocio::esHoy($diaSel) ? 'text-brand-700' : 'text-neutral-900' }}">
                        {{ ucfirst($diaSel->translatedFormat('l d \d\e F')) }}@if (\App\Support\FechaNegocio::esHoy($diaSel)) · HOY @endif
                    </h3>
                    <span class="text-xs text-neutral-400">{{ $trabajosDia->count() }} {{ $trabajosDia->count() === 1 ? 'trabajo' : 'trabajos' }}</span>
                </div>

                @unless ($puedeAgendar)
                    {{-- Solo lectura (sin permiso de agendar) = la pantalla del TÉCNICO en
                         terreno. Está de pie en la planta del cliente, con una mano y con
                         guantes, así que la tarjeta prioriza las tres cosas que necesita:
                         llamar, llegar y cerrar el trabajo.

                         La dirección y el teléfono eran texto plano dentro de un blob
                         separado por puntos: había que copiarlos a mano a Maps o al
                         marcador. Ahora son enlaces tocables de 44px.

                         El botón "Marcar realizado" usa la ruta `agenda-terreno.estado`,
                         que YA existía con su permiso (`ver agenda terreno` alcanza para
                         cerrar un trabajo agendado; el controlador lo valida) — pero
                         ninguna vista la exponía, así que el técnico no tenía forma de
                         cerrar un trabajo desde la app. --}}
                    @forelse ($trabajosDia as $t)
                        <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :variant="$t->estado_variante">{{ $t->estado_label }}</x-badge>
                                <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $t->tipo_label }}</span>
                                <span class="font-medium text-neutral-900">{{ $t->cliente_nombre }}</span>
                            </div>
                            <p class="mt-1 text-sm text-neutral-600">{{ collect([$t->rango_horas_label ? $t->rango_horas_label.' hs' : null, $t->servicio?->nombre])->filter()->implode(' · ') }}</p>

                            {{-- Llegar y llamar: lo primero que se toca al bajarse de la camioneta. --}}
                            <div class="mt-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-4">
                                <x-maps-link :direccion="$t->direccion" :ciudad="$t->ciudad" />
                                <x-tel-link :telefono="$t->cliente_telefono" />
                            </div>

                            @if ($t->descripcion)<p class="mt-1 text-sm text-neutral-500">{{ $t->descripcion }}</p>@endif
                            @if ($t->disponibilidad)<p class="mt-1 text-sm text-neutral-500"><span class="font-medium text-neutral-600">Disponibilidad:</span> {{ $t->disponibilidad }}</p>@endif

                            {{-- Cerrar el trabajo: solo cuando está agendado (el controlador
                                 exige esa transición para quien no puede agendar). --}}
                            @if ($t->estado === 'agendado')
                                {{-- CIERRE DEL TRABAJO EN TERRENO (dueño 14-08-2026).
                                     Antes era un botón pelado con un confirm(): el técnico
                                     cerraba y no quedaba registro de QUÉ hizo, aunque el
                                     controlador ya aceptaba el detalle.

                                     Ahora escribe el paso a paso —obligatorio, es lo que
                                     viaja en el aviso a ventas— y cierra de una de las dos
                                     formas. El «No realizado» existe porque el trabajo no
                                     siempre se puede hacer: faltó un repuesto, el cliente no
                                     quiso, lo que sea; sin ese botón el técnico dejaba el
                                     trabajo abierto y nadie se enteraba.

                                     Sin confirm(): el texto que escribió ES la confirmación,
                                     y un confirm() sobre un formulario ya llenado solo
                                     estorba con guantes en la planta. --}}
                                <form method="POST" action="{{ route('admin.agenda-terreno.estado', $t) }}" class="mt-4 space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <x-input-label :for="'notas-'.$t->id" value="¿Qué hiciste? Paso a paso" />
                                        <x-textarea :id="'notas-'.$t->id" name="notas_tecnico" rows="4" class="mt-1.5"
                                            placeholder="Ej. 1) Revisé la bomba booster: sin presión. 2) Cambié la membrana y el filtro de papel. 3) Purgué el sistema y medí 65 psi. 4) Dejé funcionando y el cliente lo probó.">{{ old('notas_tecnico', $t->notas_tecnico) }}</x-textarea>
                                        <x-input-hint>Esto es lo que le llega al jefe de ventas y al vendedor del cliente. Si no se pudo hacer, cuenta por qué.</x-input-hint>
                                        <x-input-error :messages="$errors->get('notas_tecnico')" class="mt-2" />
                                    </div>

                                    {{-- REPUESTOS USADOS (dueño 14-08-2026). Acompañan al paso a
                                         paso: el detalle cuenta QUÉ se hizo, esta lista con QUÉ.
                                         Viajan en el aviso a ventas y quedan en el informe.

                                         SIN PRECIO, y no por simplificar: al técnico le pagan por
                                         arreglar e instalar, no por cobrarle al cliente — la
                                         cotización formal la hacen el vendedor y el jefe de
                                         ventas. El buscador tampoco devuelve precios (endpoint
                                         aparte del taller, ver AgendaTrabajoController).

                                         Y SIN DESCUENTO DE STOCK: el inventario se descuenta con
                                         la factura o boleta del vendedor, porque Bsale descuenta
                                         al facturar y el técnico no emite documentos. Descontar
                                         acá también sería consumir el repuesto dos veces.

                                         Arranca vacío y ocupa una línea: el repuesto es opcional
                                         (una mantención puede no llevar ninguno) y la pantalla
                                         del técnico no crece de gratis. --}}
                                    <div x-data="terrenoRepuestos({
                                            repuestos: @js(old('repuestos', [])),
                                            endpointRepuestos: @js(route('admin.agenda-terreno.buscar-repuesto')),
                                         })">
                                        <div class="flex items-center justify-between">
                                            <x-input-label value="Repuestos que usaste" />
                                            <x-agregar-fila-button x-on:click="agregar()">Agregar repuesto</x-agregar-fila-button>
                                        </div>

                                        <div class="mt-2 space-y-2">
                                            <template x-for="(r, i) in repuestos" :key="i">
                                                <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-2 sm:flex-row sm:items-start">
                                                    {{-- Código del catálogo: lo pone el buscador al elegir.
                                                         Es lo que deja al vendedor armar la línea de la
                                                         factura sin volver a preguntarle al técnico.
                                                         Vacío = escrito a mano. --}}
                                                    <input type="hidden" :name="`repuestos[${i}][sku]`" :value="r.sku ?? ''">

                                                    <div class="relative sm:flex-1" x-on:click.outside="filaActiva === i && cerrar()">
                                                        <input type="text" x-model="r.nombre" :name="`repuestos[${i}][nombre]`"
                                                            placeholder="Código o nombre del repuesto" maxlength="191" autocomplete="off"
                                                            x-on:input.debounce.250ms="buscar(i)"
                                                            x-on:focus="buscar(i)"
                                                            x-on:keydown.escape="cerrar()"
                                                            class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">

                                                        <div x-show="filaActiva === i && (buscando || sugerencias.length)" x-cloak
                                                            class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                                                            <template x-if="buscando && sugerencias.length === 0">
                                                                <div class="px-3.5 py-2.5 text-sm text-neutral-400">Buscando…</div>
                                                            </template>
                                                            <ul class="max-h-60 divide-y divide-neutral-100 overflow-auto">
                                                                <template x-for="(s, si) in sugerencias" :key="si">
                                                                    <li>
                                                                        <button type="button" x-on:click="elegir(i, s)"
                                                                            class="flex min-h-12 w-full items-center gap-2 px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50">
                                                                            <span class="min-w-0">
                                                                                <span x-show="s.sku" class="font-mono text-xs text-neutral-400" x-text="s.sku"></span>
                                                                                <span x-text="s.nombre"></span>
                                                                            </span>
                                                                        </button>
                                                                    </li>
                                                                </template>
                                                            </ul>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-start gap-2">
                                                        <div class="w-20 sm:w-16">
                                                            <label class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Cant.</label>
                                                            <input type="number" min="1" x-model.number="r.cantidad" :name="`repuestos[${i}][cantidad]`"
                                                                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                                        </div>
                                                        <button type="button" x-on:click="quitar(i)"
                                                            class="shrink-0 self-end rounded-lg p-2 text-neutral-400 hover:bg-red-50 hover:text-red-600 sm:self-start" title="Quitar">
                                                            <x-icon.trash class="h-5 w-5" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>

                                            <p x-show="repuestos.length === 0" class="text-sm text-neutral-400">
                                                Si no usaste ninguno, déjalo así.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <button type="submit" name="estado" value="realizado"
                                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-base font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.99] sm:w-auto">
                                            <x-icon.check class="h-5 w-5" />
                                            Marcar realizado
                                        </button>
                                        <button type="submit" name="estado" value="no_realizado"
                                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-red-300 bg-white px-5 py-3 text-base font-semibold text-red-600 shadow-sm transition duration-150 hover:bg-red-50 active:scale-[0.99] sm:w-auto">
                                            No realizado
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500 shadow-sm">No hay trabajos agendados este día.</div>
                    @endforelse
                @else
                    {{-- Trabajos existentes del día: cada uno como formulario editable. --}}
                    @foreach ($trabajosDia as $t)
                        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6"
                             x-data="agendaTerrenoForm({
                                endpointCliente: '{{ route('admin.agenda-terreno.buscar-cliente') }}',
                                servicios: @js($serviciosJs),
                                clienteId: {{ (int) ($t->cliente_id ?? 0) }},
                                servicioId: @js((string) ($t->servicio_terreno_id ?? '')),
                             })">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Editar trabajo · {{ $t->tipo_label }}</p>
                                <x-badge :variant="$t->estado_variante">{{ $t->estado_label }}</x-badge>
                            </div>

                            {{-- El estado de la confirmación del cliente vive dentro de _form
                                 (aparece igual aquí y en la pantalla "Coordinar"). --}}
                            <form method="POST" action="{{ route('admin.agenda-terreno.update', $t) }}">
                                @csrf
                                @method('PUT')
                                @include('admin.agenda-terreno._form', ['trabajo' => $t, 'clienteCatalogo' => $t->cliente])
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

                    {{-- Día sin trabajos: mensaje claro de "nada agendado" en vez de
                         dejar el panel en blanco. Para agregar se usa el botón "Agendar
                         trabajo" de la cabecera (2026-07-30: se quitó el form inline por
                         día — duplicaba ese CTA y la fecha en el botón confundía). --}}
                    @if ($trabajosDia->isEmpty())
                        <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500 shadow-sm">No hay trabajos agendados este día.</div>
                    @endif
                @endunless
            </div>
        </div>
    </div>
</x-app-layout>
