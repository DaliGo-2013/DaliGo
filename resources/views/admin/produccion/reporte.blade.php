<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Reporte de '.$reporte->soplador->name"
                       :subtitle="$reporte->fecha->format('d-m-Y').' · turno '.$reporte->turno"
                       :back="route('admin.produccion.soplador', $reporte->soplador)">
            <x-slot name="action">
                <x-produccion.estado-badge :estado="$reporte->estado" class="text-sm" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            <div class="flex flex-wrap gap-x-4 gap-y-2">
                <x-secondary-link :href="route('admin.produccion.index')">← Volver a la cola</x-secondary-link>
                <x-secondary-link :href="route('admin.produccion.soplador', $reporte->soplador)">Ver historial del soplador</x-secondary-link>
            </div>

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
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Total contado</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ number_format($reporte->total, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Diferencia</dt>
                        <dd class="mt-1 text-sm font-medium {{ $reporte->diferencia === 0 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $reporte->diferencia }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Producido (1ª+2ª)</dt>
                        <dd class="mt-1 text-sm font-medium text-emerald-700">{{ number_format($reporte->producido, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Merma (malos+dañadas)</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-700">{{ number_format($reporte->merma, 0, ',', '.') }}</dd>
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
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Preforma dañada</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->danada }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de primera</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_primera }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de segunda</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_segunda }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de malas</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_malo }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de dañadas</dt>
                        <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_danada }}%</dd>
                    </div>
                </dl>

                @if ($reporte->registros->isNotEmpty())
                    <div class="border-t border-neutral-100">
                        <h3 class="px-6 pt-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Detalle por máquina y tipo</h3>
                        <ul class="divide-y divide-neutral-100">
                            @foreach ($reporte->registros as $registro)
                                @php
                                    $partes = array_filter([$registro->tipoBotellon?->nombre, $registro->maquina?->nombre]);
                                @endphp
                                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-6 py-3">
                                    @php
                                        $motivosTanda = collect(['2ª' => $registro->motivo_segunda, 'Malas' => $registro->motivo_malo])
                                            ->filter()->map(fn ($m, $k) => "$k: $m")->implode(' · ');
                                    @endphp
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-neutral-900">{{ $partes ? implode(' · ', $partes) : 'Registro inicial (sin máquina/tipo)' }}</p>
                                        <p class="text-xs text-neutral-400">{{ $registro->created_at->format('d-m-Y H:i') }}@if ($motivosTanda) · {{ $motivosTanda }}@endif</p>
                                    </div>
                                    <div class="flex items-center gap-4 text-sm text-neutral-600">
                                        <span><span class="text-neutral-400">1ª</span> {{ $registro->primera }}</span>
                                        <span><span class="text-neutral-400">2ª</span> {{ $registro->segunda }}</span>
                                        <span><span class="text-neutral-400">Malos</span> {{ $registro->malo }}</span>
                                        <span><span class="text-neutral-400">Dañadas</span> {{ $registro->danada }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

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

            {{-- Kardex: preview de lo que se registrará al aprobar, o los
                 movimientos ya generados si el reporte está aprobado. No toca el
                 stock espejo de Bsale; es el ledger local de producción. --}}
            @if ($reporte->esPendienteDeRevision())
                <div class="dg-enter overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm">
                    <div class="border-b border-brand-100 bg-brand-50 px-6 py-3">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-brand-700">Al aprobar se registrará en el kardex</h3>
                    </div>
                    <ul class="divide-y divide-neutral-100 text-sm">
                        <li class="flex items-center justify-between px-6 py-3">
                            <span class="text-neutral-700">Consumo de preforma{{ $reporte->asignacion?->preforma ? ' · '.$reporte->asignacion->preforma->nombre : ' (sin preforma asignada)' }}</span>
                            <span class="font-medium text-neutral-900">−{{ number_format($reporte->total, 0, ',', '.') }}</span>
                        </li>
                        <li class="flex items-center justify-between px-6 py-3">
                            <span class="text-neutral-700">Producción 1ª + 2ª (vendible)</span>
                            <span class="font-medium text-emerald-700">+{{ number_format($reporte->producido, 0, ',', '.') }}</span>
                        </li>
                        <li class="flex items-center justify-between px-6 py-3">
                            <span class="text-neutral-700">Merma (malos + dañadas)</span>
                            <span class="font-medium text-neutral-700">{{ number_format($reporte->merma, 0, ',', '.') }}</span>
                        </li>
                    </ul>
                </div>
            @elseif ($reporte->movimientos->isNotEmpty())
                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-3">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kardex generado</h3>
                        <x-secondary-link :href="route('admin.produccion.movimientos', ['q' => ''])">Ver kardex completo</x-secondary-link>
                    </div>
                    <ul class="divide-y divide-neutral-100 text-sm">
                        @foreach ($reporte->movimientos as $movimiento)
                            <li class="flex items-center justify-between px-6 py-3">
                                <span class="text-neutral-700">
                                    {{ \App\Models\ProduccionMovimiento::ETIQUETAS[$movimiento->tipo] ?? $movimiento->tipo }}
                                    @if ($movimiento->producto)
                                        · <span class="text-neutral-500">{{ $movimiento->producto->nombre }}</span>
                                    @endif
                                </span>
                                <span class="font-medium {{ $movimiento->tipo === \App\Models\ProduccionMovimiento::TIPO_CONSUMO_PREFORMA ? 'text-neutral-900' : ($movimiento->tipo === \App\Models\ProduccionMovimiento::TIPO_MERMA ? 'text-neutral-700' : 'text-emerald-700') }}">
                                    {{ number_format($movimiento->cantidad, 0, ',', '.') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Acciones del admin. La edición (asignadas + cantidades) está
                 disponible en CUALQUIER estado; aprobar/devolver solo si está
                 pendiente de revisión (enviado). --}}
            <div x-data="{ panel: null }" class="space-y-4">
                <div class="flex flex-wrap gap-3">
                    @if ($reporte->esPendienteDeRevision())
                        <form method="POST" action="{{ route('admin.produccion.reporte.aprobar', $reporte) }}"
                              onsubmit="return confirm('¿Aprobar el reporte de {{ $reporte->soplador->name }}?');">
                            @csrf
                            <x-primary-button>Aprobar</x-primary-button>
                        </form>
                        <x-secondary-button x-on:click="panel = panel === 'devolver' ? null : 'devolver'">Devolver al soplador</x-secondary-button>
                    @endif
                    <x-secondary-button x-on:click="panel = panel === 'editar' ? null : 'editar'">Editar reporte</x-secondary-button>
                </div>

                @if ($reporte->esPendienteDeRevision())
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
                @endif

                {{-- Editar reporte (asignadas + cantidades), cualquier estado --}}
                <div x-show="panel === 'editar'" x-cloak class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('admin.produccion.reporte.ajustar', $reporte) }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="asignadas" value="Asignadas (inicio del día)" />
                            <x-text-input id="asignadas" class="mt-1.5" type="number" min="1" name="asignadas" :value="old('asignadas', $reporte->asignadas)" required />
                            <x-input-hint>Sincroniza la asignación del soplador para ese día/turno.</x-input-hint>
                            <x-input-error :messages="$errors->get('asignadas')" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
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
                            <div>
                                <x-input-label for="danada" value="Preforma dañada" />
                                <x-text-input id="danada" class="mt-1.5" type="number" min="0" name="danada" :value="old('danada', $reporte->danada)" required />
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('primera')" class="mt-1" />
                        <x-input-error :messages="$errors->get('segunda')" class="mt-1" />
                        <x-input-error :messages="$errors->get('malo')" class="mt-1" />
                        <x-input-error :messages="$errors->get('danada')" class="mt-1" />
                        <div>
                            <x-input-label for="motivo_ajuste" value="Motivo del cambio" />
                            <x-textarea id="motivo_ajuste" name="motivo_ajuste" rows="2" class="mt-1.5" required>{{ old('motivo_ajuste') }}</x-textarea>
                            <x-input-error :messages="$errors->get('motivo_ajuste')" class="mt-2" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Guardar cambios</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
