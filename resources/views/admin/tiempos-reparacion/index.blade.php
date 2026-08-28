<x-app-layout>
    @php $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        {{-- Con «Volver» (doctrina P-NAV-08): desde el A1 de PLAN-MENU-DENSIDAD
             esta pantalla es HIJA del Listado — se entra por el desplegable
             «Configuración» de su cabecera, ya no por la sidebar. --}}
        <x-page-header title="Costos generales de reparación" subtitle="Tiempo estándar (horas) por trabajo — fija la mano de obra del taller."
                       :back="route('admin.servicio-tecnico.index')" backTitle="Volver al listado de Servicio Técnico">
            <x-slot name="action">
                <x-button-link :href="route('admin.tiempos-reparacion.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Agregar trabajo
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5 py-8 sm:py-12">
        <x-status-alert :status="session('status')" />

        <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm text-neutral-700 shadow-sm sm:p-5">
            Estas horas fijan la <span class="font-medium">mano de obra</span> de cada orden: se calcula
            <span class="font-medium">horas × valor hora</span>@if ($valorHora) (valor hora actual {{ $clp($valorHora) }})@endif.
            El técnico <span class="font-medium">no la puede modificar</span>: solo jefatura la ajusta aquí.
            <span class="mt-1 block">
                El técnico puede marcar <span class="font-medium">varios trabajos</span> en una orden y las horas se
                suman, hasta el tope de abajo.
            </span>
            @unless ($valorHora)
                <span class="mt-1 block text-xs text-red-600">Ojo: no hay valor hora configurado (SKU {{ config('servicio_tecnico.sku_hora_servicio') }} sin precio) → la mano de obra queda en $0 hasta cargarlo.</span>
            @endunless
        </div>

        {{-- EL TOPE DE HORAS POR ORDEN (dueño, 28-08-2026): «no quiero que se sumen 5 horas […]
             cuando un dispensador se desarma completo más estos cambios máximo puede ser dos
             horas, más de ahí no pasa». Vive acá y no en Configuración general porque es el
             mismo tema que las horas de al lado: quien calibra una calibra el otro. --}}
        <x-seccion titulo="Tope de mano de obra por orden">
            <form method="POST" action="{{ route('admin.tiempos-reparacion.tope') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div class="sm:max-w-xs">
                    <x-input-label for="tope_horas" value="Horas máximas por orden">
                        <x-slot:ayuda>
                            Cuando el técnico marca varios trabajos, las horas se suman pero no pasan de este
                            tope: el desarme del equipo se paga una vez, no una hora por cada cambio. Un
                            trabajo suelto que dure MÁS que el tope se cobra completo igual — el tope recorta
                            la acumulación, nunca el tiempo de un trabajo individual.
                            @if ($valorHora)
                                Hoy son {{ \App\Models\TiempoReparacion::fmt($topeHoras) }} h, o sea
                                {{ $clp($topeHoras * $valorHora) }} de mano de obra como máximo por orden.
                            @endif
                        </x-slot:ayuda>
                    </x-input-label>
                    <x-text-input id="tope_horas" name="tope_horas" class="mt-1.5 w-full"
                                  inputmode="decimal"
                                  value="{{ old('tope_horas', \App\Models\TiempoReparacion::fmt($topeHoras)) }}" />
                    {{-- UNA línea corta con lo operativo; el resto está en la ⓘ (doctrina 2026-08-17,
                         y el candado mide por CAMPO, no por texto). --}}
                    <x-input-hint>Acepta coma decimal: 2 o 2,5.</x-input-hint>
                    <x-input-error :messages="$errors->get('tope_horas')" class="mt-2" />
                </div>
                <x-primary-button>Guardar tope</x-primary-button>
            </form>
        </x-seccion>

        {{-- LO QUE LOS TÉCNICOS ESCRIBEN A MANO Y NO ESTÁ ACÁ. Es la cola de trabajo de jefatura:
             el catálogo se calibra con el uso real en vez de intentar adivinar combinaciones
             (que fue lo que hizo imposible la lista de respuestas fijas). Un trabajo que aparece
             seguido acá es un trabajo que le falta al catálogo. --}}
        @if ($escritosAMano->isNotEmpty())
            <x-list-card title="Escritos a mano por los técnicos, y no están en el catálogo"
                         :count="$escritosAMano->count()"
                         :countLabel="$escritosAMano->count() === 1 ? 'trabajo' : 'trabajos'">
                <li class="border-b border-neutral-100 bg-neutral-50 px-4 py-2 text-xs text-neutral-500 sm:px-6">
                    Estos trabajos no suman horas todavía. Agrega al catálogo los que se repitan: desde ahí
                    el técnico los va a poder marcar y su mano de obra se va a cobrar.
                    {{-- El recorte se DECLARA: un listado truncado en silencio se lee como «esto
                         fue todo lo que pasó». --}}
                    <span class="mt-1 block text-neutral-400">Se revisan las {{ $ordenesRevisadas }} órdenes más recientes.</span>
                </li>
                @foreach ($escritosAMano as $x)
                    <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <p class="font-medium text-neutral-900">{{ $x['texto'] }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                {{ $x['veces'] }} {{ $x['veces'] === 1 ? 'vez' : 'veces' }}
                                @if ($x['ultima']) · última el {{ $x['ultima'] }} @endif
                            </p>
                        </div>
                        <div class="shrink-0">
                            {{-- El texto viaja como sugerencia del formulario de alta, así jefatura no
                                 lo vuelve a tipear (ni le cambia una letra sin querer, que es lo que
                                 antes dejaba al trabajo sin coincidir). --}}
                            <x-secondary-link :href="route('admin.tiempos-reparacion.create', ['trabajo' => $x['texto']])">
                                Agregar al catálogo
                            </x-secondary-link>
                        </div>
                    </li>
                @endforeach
            </x-list-card>
        @endif

        @forelse ($porGrupo as $grupo => $tiempos)
            <x-list-card :title="$grupo ?: 'Sin grupo'" :count="$tiempos->count()" :countLabel="$tiempos->count() === 1 ? 'trabajo' : 'trabajos'">
                @foreach ($tiempos as $t)
                    <li class="px-4 py-3 sm:px-6 {{ $t->activo ? '' : 'opacity-60' }}">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded bg-brand-50 px-1.5 py-0.5 text-xs font-semibold text-brand-700">{{ $t->horas_fmt }} h</span>
                                    <p class="font-medium text-neutral-900">{{ $t->trabajo }}</p>
                                    @unless ($t->activo)
                                        <x-badge variant="neutral">Inactivo</x-badge>
                                    @endunless
                                </div>
                                @if ($valorHora)
                                    <p class="mt-0.5 text-xs text-neutral-500">Mano de obra: {{ $clp($t->horas * $valorHora) }}</p>
                                @endif
                            </div>
                            <div class="shrink-0">
                                <x-secondary-link :href="route('admin.tiempos-reparacion.edit', $t)">Editar</x-secondary-link>
                            </div>
                        </div>
                    </li>
                @endforeach
            </x-list-card>
        @empty
            <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500 shadow-sm">
                Sin trabajos en el catálogo todavía. Usa «Agregar trabajo».
            </div>
        @endforelse
    </div>
</x-app-layout>
