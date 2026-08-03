{{-- Trabajos extras en paralelo: features fuera de la planificación oficial.
     Lo ÚNICO editable de la página (BD, permiso 'gestionar plan proyecto') —
     el plan oficial se lee del repo. Los forms se re-abren solos ante errores
     de validación vía old('_extra_id') (null = form de alta). --}}
@php
    // Estados por RELLENO (doctrina de badges: en curso = brand sólido como
    // *Enviado*, finalizada = neutral-800 sólido como *Aprobado*) — espejo de
    // la leyenda del Gantt.
    $badgeExtra = [
        'no_iniciada' => 'bg-neutral-100 text-neutral-500 ring-1 ring-inset ring-neutral-200',
        'en_curso' => 'bg-brand-600 text-white',
        'finalizada' => 'bg-neutral-800 text-white',
    ];
    $rellenoExtra = ['no_iniciada' => 'bg-neutral-200', 'en_curso' => 'bg-brand-600', 'finalizada' => 'bg-neutral-800'];
    $hayErrores = $errors->any();
    $errorEnAlta = $hayErrores && old('_extra_id') === null;
@endphp

<div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm" x-data="{ selExtra: null }">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-neutral-100 px-6 py-3">
        <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Trabajos extras en paralelo</h2>
        <span class="text-xs text-neutral-400">Fuera de la planificación oficial</span>
    </div>

    {{-- Bloques AUTOMÁTICOS del repo: las unidades E-xx (con guión) de
         RUTA-MAESTRA — trabajo extra ya agrupado «en bloques con sentido»
         cuyos pasos [x]/[ ] se marcan en cada push. Aparecen y avanzan solos
         con cada deploy (pedido del dueño 31-07). Mismo idioma clickeable
         del Gantt: fila-botón → panel; sin x-transition (gotcha 22-07). --}}
    <div class="border-b border-neutral-100">
        <p class="px-4 pt-3 text-xs font-medium uppercase tracking-wide text-neutral-400 sm:px-6">Del repo (automático)</p>
        <div class="space-y-0.5 px-2 py-2 sm:px-4">
            @foreach ($bloquesExtra as $bloque)
                <button type="button"
                        @click="selExtra = selExtra === '{{ $bloque['key'] }}' ? null : '{{ $bloque['key'] }}'"
                        :aria-expanded="selExtra === '{{ $bloque['key'] }}' ? 'true' : 'false'"
                        aria-controls="plan-detalle-extra-{{ $bloque['key'] }}"
                        :class="selExtra === '{{ $bloque['key'] }}' ? 'bg-brand-50' : 'hover:bg-neutral-50'"
                        class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 rounded-lg px-2 py-2 text-left transition duration-150 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-neutral-900">{{ $bloque['titulo'] }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeExtra[$bloque['estado']] }}">{{ \App\Models\PlanExtra::LABELS[$bloque['estado']] }}</span>
                    <span class="h-2 w-24 overflow-hidden rounded-full bg-neutral-100">
                        <span class="block h-full rounded-full {{ $rellenoExtra[$bloque['estado']] }}" style="width: {{ $bloque['pct'] }}%"></span>
                    </span>
                    <span class="w-24 text-end text-xs tabular-nums text-neutral-600">{{ $bloque['hechos'] }}/{{ $bloque['total'] }} pasos · {{ $bloque['pct'] }}%</span>
                </button>
            @endforeach
        </div>

        @foreach ($bloquesExtra as $bloque)
            <div id="plan-detalle-extra-{{ $bloque['key'] }}" x-show="selExtra === '{{ $bloque['key'] }}'" x-cloak
                 class="border-t border-neutral-100 px-4 py-4 sm:px-6">
                @include('plan.partials._columnas-detalle', ['hecho' => $bloque['pasos_hechos'], 'falta' => $bloque['pasos_pendientes']])
            </div>
        @endforeach

        <p class="px-4 pb-3 pt-1 text-xs text-neutral-400 sm:px-6">Se alimenta solo de los bloques E-xx de RUTA-MAESTRA en cada deploy — toca uno para ver sus pasos.</p>
    </div>

    <p class="px-4 pt-3 text-xs font-medium uppercase tracking-wide text-neutral-400 sm:px-6">Anotados a mano <span class="normal-case tracking-normal">· ideas que aún no son bloque del repo — se editan aquí</span></p>

    @can('gestionar plan proyecto')
        <div class="border-b border-neutral-100 px-6 py-4" x-data="{ paneles: { nuevo: {{ $errorEnAlta ? 'true' : 'false' }} } }">
            <x-collapsible label="Agregar trabajo extra" model="paneles.nuevo">
                <form method="POST" action="{{ route('plan.extras.store') }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="titulo" value="Título *" />
                        <x-text-input id="titulo" name="titulo" type="text" class="mt-1 block w-full" :value="old('titulo')" required />
                        <x-input-error :messages="$errors->get('titulo')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <x-select id="estado" name="estado" class="mt-1">
                            @foreach (\App\Models\PlanExtra::ESTADOS as $estado)
                                <option value="{{ $estado }}" @selected(old('estado', 'en_curso') === $estado)>{{ \App\Models\PlanExtra::LABELS[$estado] }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('estado')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="avance" value="Avance (%)" />
                        <x-text-input id="avance" name="avance" type="number" min="0" max="100" class="mt-1 block w-full" :value="old('avance', 0)" required />
                        <x-input-error :messages="$errors->get('avance')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="responsable" value="Responsable" />
                        <x-text-input id="responsable" name="responsable" type="text" class="mt-1 block w-full" :value="old('responsable')" />
                        <x-input-error :messages="$errors->get('responsable')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="descripcion" value="Descripción" />
                        <x-textarea id="descripcion" name="descripcion" rows="2" class="mt-1">{{ old('descripcion') }}</x-textarea>
                        <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
                    </div>
                    <div class="flex justify-end sm:col-span-2">
                        <x-primary-button>Agregar</x-primary-button>
                    </div>
                </form>
            </x-collapsible>
        </div>
    @endcan

    <ul class="divide-y divide-neutral-100">
        @forelse ($extras as $extra)
            @php $errorEnEste = $hayErrores && old('_extra_id') == $extra->id; @endphp
            <li class="px-6 py-4" x-data="{ editando: {{ $errorEnEste ? 'true' : 'false' }} }">
                <div x-show="! editando" class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-neutral-900">{{ $extra->titulo }}</p>
                        @if ($extra->descripcion)
                            <p class="mt-0.5 text-xs text-neutral-500">{{ $extra->descripcion }}</p>
                        @endif
                        @if ($extra->responsable)
                            <p class="mt-0.5 text-xs text-neutral-400">Responsable: {{ $extra->responsable }}</p>
                        @endif
                    </div>
                    <div class="flex w-full items-center gap-3 sm:w-auto">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeExtra[$extra->estado] ?? $badgeExtra['no_iniciada'] }}">{{ \App\Models\PlanExtra::LABELS[$extra->estado] ?? $extra->estado }}</span>
                        <div class="h-2 w-24 overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full {{ $rellenoExtra[$extra->estado] ?? 'bg-neutral-200' }}" style="width: {{ $extra->avance }}%"></div>
                        </div>
                        <span class="w-10 text-end text-xs font-medium tabular-nums text-neutral-600">{{ $extra->avance }}%</span>
                        @can('gestionar plan proyecto')
                            <button type="button" @click="editando = true"
                                    class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700"
                                    title="Editar">
                                <x-icon.pencil class="h-4 w-4" />
                                <span class="sr-only">Editar</span>
                            </button>
                            <form method="POST" action="{{ route('plan.extras.destroy', $extra) }}"
                                  onsubmit="return confirm('¿Eliminar este trabajo extra? Esta acción no se puede deshacer.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-red-50 hover:text-red-600"
                                        title="Eliminar">
                                    <x-icon.trash class="h-4 w-4" />
                                    <span class="sr-only">Eliminar</span>
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>

                @can('gestionar plan proyecto')
                    <form x-show="editando" x-cloak method="POST" action="{{ route('plan.extras.update', $extra) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_extra_id" value="{{ $extra->id }}">
                        <div class="sm:col-span-2">
                            <x-input-label for="titulo-{{ $extra->id }}" value="Título *" />
                            <x-text-input id="titulo-{{ $extra->id }}" name="titulo" type="text" class="mt-1 block w-full"
                                          :value="$errorEnEste ? old('titulo') : $extra->titulo" required />
                            <x-input-error :messages="$errorEnEste ? $errors->get('titulo') : []" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="estado-{{ $extra->id }}" value="Estado" />
                            <x-select id="estado-{{ $extra->id }}" name="estado" class="mt-1">
                                @foreach (\App\Models\PlanExtra::ESTADOS as $estado)
                                    <option value="{{ $estado }}" @selected(($errorEnEste ? old('estado') : $extra->estado) === $estado)>{{ \App\Models\PlanExtra::LABELS[$estado] }}</option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errorEnEste ? $errors->get('estado') : []" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="avance-{{ $extra->id }}" value="Avance (%)" />
                            <x-text-input id="avance-{{ $extra->id }}" name="avance" type="number" min="0" max="100" class="mt-1 block w-full"
                                          :value="$errorEnEste ? old('avance') : $extra->avance" required />
                            <x-input-error :messages="$errorEnEste ? $errors->get('avance') : []" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="responsable-{{ $extra->id }}" value="Responsable" />
                            <x-text-input id="responsable-{{ $extra->id }}" name="responsable" type="text" class="mt-1 block w-full"
                                          :value="$errorEnEste ? old('responsable') : $extra->responsable" />
                            <x-input-error :messages="$errorEnEste ? $errors->get('responsable') : []" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="descripcion-{{ $extra->id }}" value="Descripción" />
                            <x-textarea id="descripcion-{{ $extra->id }}" name="descripcion" rows="2" class="mt-1">{{ $errorEnEste ? old('descripcion') : $extra->descripcion }}</x-textarea>
                            <x-input-error :messages="$errorEnEste ? $errors->get('descripcion') : []" class="mt-2" />
                        </div>
                        <div class="flex justify-end gap-3 sm:col-span-2">
                            <x-secondary-button type="button" @click="editando = false">Cerrar</x-secondary-button>
                            <x-primary-button>Guardar</x-primary-button>
                        </div>
                    </form>
                @endcan
            </li>
        @empty
            <li class="px-6 py-8 text-center text-sm text-neutral-500">Sin trabajos extras registrados.</li>
        @endforelse
    </ul>
</div>
