{{-- Hitos del plan con su countdown. Desde P-PLAN-06 viven en BD (`plan_hitos`) y
     se agregan/editan/eliminan acá con 'gestionar plan proyecto' (pedido del
     dueño 10-09: «que sea rápido agregar cuando el gerente pide algo»). Rojo SOLO
     para lo negativo (atrasado), doctrina de la paleta. Los forms se re-abren
     solos ante errores de validación vía old('_hito_id') (null = form de alta),
     mismo idioma que _extras. Se distinguen de los errores de un extra porque
     solo los forms de hito mandan `clave`. --}}
@php
    $hayErroresHito = $errors->any() && old('clave') !== null;
    $errorEnAltaHito = $hayErroresHito && old('_hito_id') === null;
@endphp

<div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-neutral-100 px-6 py-3">
        <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Hitos</h2>
        @can('gestionar plan proyecto')
            <span class="text-xs text-neutral-400">Se editan aquí · el lápiz abre cada hito</span>
        @endcan
    </div>

    @can('gestionar plan proyecto')
        <div class="border-b border-neutral-100 px-4 py-4 sm:px-6" x-data="{ paneles: { nuevo: {{ $errorEnAltaHito ? 'true' : 'false' }} } }">
            <x-collapsible label="Agregar hito" model="paneles.nuevo">
                <form method="POST" action="{{ route('plan.hitos.store') }}" class="grid gap-4 sm:grid-cols-6">
                    @csrf
                    <div class="sm:col-span-1">
                        <x-input-label for="hito-clave" value="Código *">
                            <x-slot:ayuda>Sigla corta que identifica el hito en la carta y en el Excel, por ejemplo H8. Debe ser única.</x-slot:ayuda>
                        </x-input-label>
                        <x-text-input id="hito-clave" name="clave" type="text" maxlength="12" class="mt-1 block w-full" :value="$errorEnAltaHito ? old('clave') : ''" required />
                        <x-input-error :messages="$errorEnAltaHito ? $errors->get('clave') : []" class="mt-2" />
                    </div>
                    <div class="sm:col-span-3">
                        <x-input-label for="hito-etiqueta" value="Hito *" />
                        <x-text-input id="hito-etiqueta" name="etiqueta" type="text" class="mt-1 block w-full" :value="$errorEnAltaHito ? old('etiqueta') : ''" required />
                        <x-input-error :messages="$errorEnAltaHito ? $errors->get('etiqueta') : []" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="hito-fecha" value="Fecha *" />
                        <x-text-input id="hito-fecha" name="fecha" type="date" class="mt-1 block w-full" :value="$errorEnAltaHito ? old('fecha') : ''" required />
                        <x-input-error :messages="$errorEnAltaHito ? $errors->get('fecha') : []" class="mt-2" />
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-6">
                        <x-checkbox-item name="cumplido" value="1" :checked="$errorEnAltaHito && old('cumplido')">Ya cumplido</x-checkbox-item>
                        <x-primary-button>Agregar</x-primary-button>
                    </div>
                </form>
            </x-collapsible>
        </div>
    @endcan

    <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 sm:p-6 xl:grid-cols-4">
        @forelse ($hitos as $hito)
            @php $errorEnEste = $hayErroresHito && old('_hito_id') == $hito['id']; @endphp
            {{-- Al editar, la tarjeta ocupa la fila entera (col-span-full): el form
                 no cabe en un cuarto de ancho. --}}
            <div class="rounded-lg border border-neutral-200 p-3"
                 x-data="{ editando: {{ $errorEnEste ? 'true' : 'false' }} }"
                 :class="editando ? 'col-span-full' : ''">
                <div x-show="! editando">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-neutral-500">{{ $hito['key'] }}</span>
                        @if ($hito['estado'] === 'cumplido')
                            <span class="inline-flex items-center rounded-full bg-neutral-800 px-2.5 py-0.5 text-xs font-medium text-white">Cumplido</span>
                        @elseif ($hito['estado'] === 'atrasado')
                            <x-badge variant="danger">Atrasado {{ abs($hito['dias']) }} d</x-badge>
                        @else
                            <x-badge variant="brand">Faltan {{ $hito['dias'] }} d</x-badge>
                        @endif
                    </div>
                    <p class="mt-2 text-sm font-medium text-neutral-900">{{ $hito['label'] }}</p>
                    <div class="mt-0.5 flex items-center justify-between gap-2">
                        <p class="text-xs tabular-nums text-neutral-500">{{ $hito['carbon']->format('d-m-Y') }}</p>
                        @can('gestionar plan proyecto')
                            <button type="button" @click="editando = true"
                                    class="-me-2 rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700"
                                    title="Editar hito">
                                <x-icon.pencil class="h-4 w-4" />
                                <span class="sr-only">Editar</span>
                            </button>
                        @endcan
                    </div>
                </div>

                @can('gestionar plan proyecto')
                    <form x-show="editando" x-cloak method="POST" action="{{ route('plan.hitos.update', $hito['id']) }}" class="grid gap-4 sm:grid-cols-6">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_hito_id" value="{{ $hito['id'] }}">
                        <div class="sm:col-span-1">
                            <x-input-label for="hito-clave-{{ $hito['id'] }}" value="Código *" />
                            <x-text-input id="hito-clave-{{ $hito['id'] }}" name="clave" type="text" maxlength="12" class="mt-1 block w-full"
                                          :value="$errorEnEste ? old('clave') : $hito['key']" required />
                            <x-input-error :messages="$errorEnEste ? $errors->get('clave') : []" class="mt-2" />
                        </div>
                        <div class="sm:col-span-3">
                            <x-input-label for="hito-etiqueta-{{ $hito['id'] }}" value="Hito *" />
                            <x-text-input id="hito-etiqueta-{{ $hito['id'] }}" name="etiqueta" type="text" class="mt-1 block w-full"
                                          :value="$errorEnEste ? old('etiqueta') : $hito['label']" required />
                            <x-input-error :messages="$errorEnEste ? $errors->get('etiqueta') : []" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="hito-fecha-{{ $hito['id'] }}" value="Fecha *" />
                            <x-text-input id="hito-fecha-{{ $hito['id'] }}" name="fecha" type="date" class="mt-1 block w-full"
                                          :value="$errorEnEste ? old('fecha') : $hito['fecha']" required />
                            <x-input-error :messages="$errorEnEste ? $errors->get('fecha') : []" class="mt-2" />
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-6">
                            <x-checkbox-item name="cumplido" value="1" :checked="$errorEnEste ? (bool) old('cumplido') : $hito['cumplido']">Cumplido</x-checkbox-item>
                            <div class="flex items-center gap-3">
                                <button type="submit" form="hito-borrar-{{ $hito['id'] }}"
                                        class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-red-50 hover:text-red-600"
                                        title="Eliminar hito">
                                    <x-icon.trash class="h-4 w-4" />
                                    <span class="sr-only">Eliminar</span>
                                </button>
                                <x-secondary-button type="button" @click="editando = false">Cerrar</x-secondary-button>
                                <x-primary-button>Guardar</x-primary-button>
                            </div>
                        </div>
                    </form>
                    {{-- Form de borrado HERMANO del de edición (un form no puede anidar
                         otro); el botón de la papelera lo dispara con `form=`. --}}
                    <form id="hito-borrar-{{ $hito['id'] }}" method="POST" action="{{ route('plan.hitos.destroy', $hito['id']) }}"
                          onsubmit="return confirm('¿Eliminar este hito del plan? Esta acción no se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                    </form>
                @endcan
            </div>
        @empty
            <p class="col-span-full py-6 text-center text-sm text-neutral-500">Sin hitos registrados.</p>
        @endforelse
    </div>
</div>
