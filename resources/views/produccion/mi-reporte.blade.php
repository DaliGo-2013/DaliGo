<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Mi producción de hoy" :subtitle="now()->translatedFormat('l d \\d\\e F')" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            @if (! $reporte)
                {{-- Sin asignación --}}
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-10 text-center shadow-sm">
                    <p class="text-sm text-neutral-500">No tienes una asignación de producción para hoy.</p>
                    <p class="mt-1 text-xs text-neutral-400">El jefe de bodega debe asignarte preformas antes de poder reportar.</p>
                </div>
            @elseif (! $reporte->editablePorSoplador())
                {{-- Enviado / aprobado: solo lectura --}}
                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-3">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Lo que reportaste</h3>
                        <x-produccion.estado-badge :estado="$reporte->estado" />
                    </div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 p-6">
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Asignadas</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->asignadas }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Total</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->total }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Primera</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->primera }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Segunda</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->segunda }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Malos</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->malo }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de primera</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_primera }}%</dd></div>
                    </dl>
                    <p class="border-t border-neutral-100 px-6 py-4 text-xs text-neutral-500">
                        El reporte enviado no se puede editar. Si hubo un error, el jefe puede devolvértelo.
                    </p>
                </div>
            @else
                {{-- Editable: borrador o devuelto --}}
                @if ($reporte->estado === \App\Models\ProduccionReporte::DEVUELTO && $reporte->devuelto_motivo)
                    <div class="dg-enter mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <span class="font-medium">El jefe te devolvió el reporte:</span> {{ $reporte->devuelto_motivo }}
                    </div>
                @endif

                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
                     x-data="{
                        primera: {{ (int) old('primera', $reporte->primera) }},
                        segunda: {{ (int) old('segunda', $reporte->segunda) }},
                        malo: {{ (int) old('malo', $reporte->malo) }},
                        asignadas: {{ (int) $reporte->asignadas }},
                        get total() { return (Number(this.primera) || 0) + (Number(this.segunda) || 0) + (Number(this.malo) || 0); },
                        get diferencia() { return this.asignadas - this.total; }
                     }">
                    <p class="mb-5 text-sm text-neutral-500">
                        Preformas asignadas hoy: <span class="font-medium text-neutral-700">{{ $reporte->asignadas }}</span>
                    </p>

                    <form method="POST" action="{{ route('produccion.mi.update', $reporte) }}" class="space-y-5">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="primera" value="Primera" />
                            <x-input-hint>Vendible normal.</x-input-hint>
                            <x-text-input id="primera" class="mt-1.5" type="number" min="0" name="primera" x-model.number="primera" :value="old('primera', $reporte->primera)" required />
                            <x-input-error :messages="$errors->get('primera')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="segunda" value="Segunda" />
                            <x-input-hint>Defecto leve.</x-input-hint>
                            <x-text-input id="segunda" class="mt-1.5" type="number" min="0" name="segunda" x-model.number="segunda" :value="old('segunda', $reporte->segunda)" required />
                            <x-input-error :messages="$errors->get('segunda')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="malo" value="Malos" />
                            <x-input-hint>No vendible · reciclaje.</x-input-hint>
                            <x-text-input id="malo" class="mt-1.5" type="number" min="0" name="malo" x-model.number="malo" :value="old('malo', $reporte->malo)" required />
                            <x-input-error :messages="$errors->get('malo')" class="mt-2" />
                        </div>

                        {{-- Resumen en vivo --}}
                        <div class="rounded-lg bg-neutral-50 px-4 py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-500">Total ingresado</span>
                                <span class="font-semibold text-neutral-900" x-text="total"></span>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <span class="text-neutral-500">Diferencia con asignadas</span>
                                <span class="font-semibold" :class="diferencia === 0 ? 'text-emerald-600' : 'text-amber-600'" x-text="diferencia"></span>
                            </div>
                        </div>

                        {{-- Motivo: requerido si hay diferencia --}}
                        <div x-show="diferencia !== 0" x-cloak>
                            <x-input-label for="motivo" value="Motivo de la diferencia" />
                            <x-text-input id="motivo" class="mt-1.5" type="text" name="motivo" :value="old('motivo', $reporte->motivo)" placeholder="Ej. molde dañado, corte de luz…" />
                            <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="obs" value="Observaciones (opcional)" />
                            <x-textarea id="obs" name="obs" rows="2" class="mt-1.5">{{ old('obs', $reporte->obs) }}</x-textarea>
                            <x-input-error :messages="$errors->get('obs')" class="mt-2" />
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                            <x-secondary-button type="submit" name="enviar" value="0">Guardar borrador</x-secondary-button>
                            <x-primary-button type="submit" name="enviar" value="1"
                                onclick="return confirm('¿Enviar el reporte? No podrás editarlo después.');">
                                Confirmar y enviar
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
