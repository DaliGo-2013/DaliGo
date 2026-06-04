<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Reporte de '.$reporte->soplador->name"
                       :subtitle="$reporte->fecha->format('d-m-Y').' · turno '.$reporte->turno">
            <x-slot name="action">
                <x-produccion.estado-badge :estado="$reporte->estado" class="text-sm" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Datos del reporte --}}
            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-6 py-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Datos del reporte</h3>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 p-6 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Asignadas</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ number_format($reporte->asignadas, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Total producido</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ number_format($reporte->total, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Diferencia</dt>
                        <dd class="mt-1 text-sm font-medium {{ $reporte->diferencia === 0 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $reporte->diferencia }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Primera</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->primera }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Segunda</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->segunda }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Malos</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->malo }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de primera</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_primera }}%</dd>
                    </div>
                </dl>

                @if ($reporte->motivo || $reporte->obs || $reporte->motivo_ajuste || $reporte->devuelto_motivo)
                    <div class="space-y-2 border-t border-neutral-100 px-6 py-4 text-sm">
                        @if ($reporte->motivo)
                            <p><span class="font-medium text-neutral-700">Motivo del soplador:</span> <span class="text-neutral-600">{{ $reporte->motivo }}</span></p>
                        @endif
                        @if ($reporte->obs)
                            <p><span class="font-medium text-neutral-700">Observaciones:</span> <span class="text-neutral-600">{{ $reporte->obs }}</span></p>
                        @endif
                        @if ($reporte->motivo_ajuste)
                            <p><span class="font-medium text-neutral-700">Ajuste del jefe:</span> <span class="text-neutral-600">{{ $reporte->motivo_ajuste }}</span></p>
                        @endif
                        @if ($reporte->devuelto_motivo)
                            <p><span class="font-medium text-neutral-700">Motivo de devolución:</span> <span class="text-neutral-600">{{ $reporte->devuelto_motivo }}</span></p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Acciones del jefe --}}
            @if ($reporte->esPendienteDeRevision())
                <div x-data="{ panel: null }" class="space-y-4">
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.produccion.reporte.aprobar', $reporte) }}"
                              onsubmit="return confirm('¿Aprobar el reporte de {{ $reporte->soplador->name }}?');">
                            @csrf
                            <x-primary-button>Aprobar</x-primary-button>
                        </form>
                        <x-secondary-button x-on:click="panel = panel === 'ajustar' ? null : 'ajustar'">Ajustar cantidades</x-secondary-button>
                        <x-secondary-button x-on:click="panel = panel === 'devolver' ? null : 'devolver'">Devolver al soplador</x-secondary-button>
                    </div>

                    {{-- Devolver --}}
                    <div x-show="panel === 'devolver'" x-cloak class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                        <form method="POST" action="{{ route('admin.produccion.reporte.devolver', $reporte) }}" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="devuelto_motivo" value="Motivo de la devolución" />
                                <x-textarea id="devuelto_motivo" name="devuelto_motivo" rows="3" class="mt-1.5" required>{{ old('devuelto_motivo') }}</x-textarea>
                                <x-input-error :messages="$errors->get('devuelto_motivo')" class="mt-2" />
                            </div>
                            <div class="flex justify-end">
                                <x-danger-button>Devolver reporte</x-danger-button>
                            </div>
                        </form>
                    </div>

                    {{-- Ajustar --}}
                    <div x-show="panel === 'ajustar'" x-cloak class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                        <form method="POST" action="{{ route('admin.produccion.reporte.ajustar', $reporte) }}" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label for="primera" value="Primera" />
                                    <x-text-input id="primera" class="mt-1.5" type="number" min="0" name="primera" :value="old('primera', $reporte->primera)" required />
                                </div>
                                <div>
                                    <x-input-label for="segunda" value="Segunda" />
                                    <x-text-input id="segunda" class="mt-1.5" type="number" min="0" name="segunda" :value="old('segunda', $reporte->segunda)" required />
                                </div>
                                <div>
                                    <x-input-label for="malo" value="Malos" />
                                    <x-text-input id="malo" class="mt-1.5" type="number" min="0" name="malo" :value="old('malo', $reporte->malo)" required />
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('primera')" class="mt-1" />
                            <x-input-error :messages="$errors->get('segunda')" class="mt-1" />
                            <x-input-error :messages="$errors->get('malo')" class="mt-1" />
                            <div>
                                <x-input-label for="motivo_ajuste" value="Motivo del ajuste" />
                                <x-textarea id="motivo_ajuste" name="motivo_ajuste" rows="2" class="mt-1.5" required>{{ old('motivo_ajuste') }}</x-textarea>
                                <x-input-error :messages="$errors->get('motivo_ajuste')" class="mt-2" />
                            </div>
                            <div class="flex justify-end">
                                <x-primary-button>Guardar ajuste</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <p class="text-sm text-neutral-500">
                    Este reporte está en estado <span class="font-medium text-neutral-700">{{ $reporte->estado }}</span>;
                    no hay acciones de revisión disponibles.
                </p>
            @endif

            <div>
                <x-secondary-link :href="route('admin.produccion.index')">← Volver a la cola</x-secondary-link>
            </div>
        </div>
    </div>
</x-app-layout>
