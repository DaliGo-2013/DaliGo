<x-app-layout>
    {{-- Pantalla de operario: sin banda de cabecera para aprovechar el alto del móvil.
         Título y fecha en una sola línea compacta. --}}
    <div class="py-4 sm:py-8">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-semibold leading-tight text-neutral-900">Mi producción</h2>
                <p class="text-xs text-neutral-500">{{ now()->translatedFormat('l d \\d\\e F') }}</p>
            </div>

            <x-status-alert :status="session('status')" class="mb-4" />

            @if (! $reporte)
                {{-- Sin asignación --}}
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-sm text-neutral-500">No tienes una asignación de producción para hoy.</p>
                    <p class="mt-1 text-xs text-neutral-400">El jefe de bodega debe asignarte preformas antes de poder reportar.</p>
                </div>
            @elseif (! $reporte->editablePorSoplador())
                {{-- Enviado / aprobado: solo lectura --}}
                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Lo que reportaste</h3>
                        <x-produccion.estado-badge :estado="$reporte->estado" />
                    </div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 p-4 sm:p-6">
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Asignadas</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->asignadas }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Total</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->total }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Primera</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->primera }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Segunda</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->segunda }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Malos</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->malo }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de primera</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_primera }}%</dd></div>
                    </dl>
                    <p class="border-t border-neutral-100 px-4 py-4 text-xs text-neutral-500 sm:px-6">
                        El reporte enviado no se puede editar. Si hubo un error, el jefe puede devolvértelo.
                    </p>
                </div>
            @else
                {{-- Editable: borrador o devuelto --}}
                @if ($reporte->estado === \App\Models\ProduccionReporte::DEVUELTO && $reporte->devuelto_motivo)
                    <div class="dg-enter mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <span class="font-medium">El jefe te devolvió el reporte:</span> {{ $reporte->devuelto_motivo }}
                    </div>
                @endif

                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm"
                     x-data="{
                        primera: {{ (int) old('primera', $reporte->primera) }},
                        segunda: {{ (int) old('segunda', $reporte->segunda) }},
                        malo: {{ (int) old('malo', $reporte->malo) }},
                        asignadas: {{ (int) $reporte->asignadas }},
                        get total() { return (Number(this.primera) || 0) + (Number(this.segunda) || 0) + (Number(this.malo) || 0); },
                        get diferencia() { return this.asignadas - this.total; }
                     }">
                    {{-- La asignación del día, siempre a la vista --}}
                    <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <span class="text-xs font-medium uppercase tracking-wide text-neutral-500">Preformas asignadas hoy</span>
                        <span class="text-xl font-bold text-neutral-900">{{ $reporte->asignadas }}</span>
                    </div>

                    <form method="POST" action="{{ route('produccion.mi.update', $reporte) }}" class="space-y-4 p-4 sm:p-6">
                        @csrf
                        @method('PATCH')

                        <x-stepper-input name="primera" label="Primera" hint="Vendible normal." :value="old('primera', $reporte->primera)" />
                        <x-stepper-input name="segunda" label="Segunda" hint="Defecto leve." :value="old('segunda', $reporte->segunda)" />
                        <x-stepper-input name="malo" label="Malos" hint="No vendible · reciclaje." :value="old('malo', $reporte->malo)" />

                        {{-- Resumen en vivo --}}
                        <div class="rounded-lg bg-neutral-50 px-4 py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-500">Total ingresado</span>
                                <span class="text-base font-semibold text-neutral-900" x-text="total"></span>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <span class="text-neutral-500">Diferencia con asignadas</span>
                                <span class="text-base font-semibold" :class="diferencia === 0 ? 'text-emerald-600' : 'text-amber-600'" x-text="diferencia"></span>
                            </div>
                        </div>

                        {{-- Motivo: requerido si hay diferencia; con sugerencias tocables --}}
                        <div x-show="diferencia !== 0" x-cloak>
                            <x-input-label for="motivo" value="Motivo de la diferencia" />
                            <x-text-input id="motivo" class="mt-1.5" type="text" name="motivo" list="motivos-comunes" :value="old('motivo', $reporte->motivo)" placeholder="Toca para elegir o escribe…" />
                            <datalist id="motivos-comunes">
                                <option value="Molde dañado"></option>
                                <option value="Falla de máquina"></option>
                                <option value="Corte de luz"></option>
                                <option value="Faltaron preformas"></option>
                                <option value="Preformas defectuosas"></option>
                            </datalist>
                            <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="obs" value="Observaciones (opcional)" />
                            <x-textarea id="obs" name="obs" rows="2" class="mt-1.5">{{ old('obs', $reporte->obs) }}</x-textarea>
                            <x-input-error :messages="$errors->get('obs')" class="mt-2" />
                        </div>

                        {{-- En móvil: botones a ancho completo, fáciles de tocar --}}
                        <div class="flex flex-col gap-3 pt-1 sm:flex-row sm:justify-end">
                            <x-secondary-button type="submit" name="enviar" value="0" class="h-12 w-full justify-center sm:h-auto sm:w-auto">
                                Guardar borrador
                            </x-secondary-button>
                            <x-primary-button type="submit" name="enviar" value="1" class="h-12 w-full sm:h-auto sm:w-auto"
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
