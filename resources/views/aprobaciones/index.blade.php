<x-app-layout>
    {{-- Bandeja movil del aprobador (M14): pantalla de celular en terreno —
         titulo compacto en una linea (sin banda de cabecera), tarjetas con lo
         justo, aprobar en <=2 taps, rechazo con motivo obligatorio en un
         colapsable por tarjeta. --}}
    <div class="mx-auto max-w-2xl space-y-4 px-4 py-6 sm:px-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-neutral-900">
                Aprobaciones
                @if ($pendientes->isNotEmpty())
                    <span class="ms-1 inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-brand-600 px-1.5 text-xs font-semibold text-white">{{ $pendientes->count() }}</span>
                @endif
            </h1>
            <a href="{{ route('aprobaciones.mias') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Mis solicitudes</a>
        </div>

        <x-status-alert :status="session('status')" />

        @if ($pendientes->isEmpty())
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white px-6 py-10 text-center shadow-sm">
                <p class="text-sm font-medium text-neutral-900">Todo al día</p>
                <p class="mt-1 text-sm text-neutral-500">No tienes solicitudes pendientes de aprobación.</p>
            </div>
        @endif

        @foreach ($pendientes as $aprobacion)
            {{-- id: ancla de aterrizaje de la campanita (urlDestino puntual, lote NOTIF-1). --}}
            <div id="aprobacion-{{ $aprobacion->id }}" x-data="{ paneles: { rechazo: false } }"
                 class="dg-enter scroll-mt-6 rounded-2xl border border-neutral-200 bg-white shadow-sm target:ring-2 target:ring-brand-300">
                <div class="space-y-3 px-4 py-4 sm:px-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-neutral-900">{{ $aprobacion->descripcion }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                {{ $aprobacion->etiquetaTipo() }} · {{ $aprobacion->solicitante?->name ?? '—' }}
                                · {{ $aprobacion->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <x-aprobaciones.estado-badge :estado="$aprobacion->estado" />
                            @if ($aprobacion->nivel_escalamiento > 0)
                                <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-100">Escalada</span>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Motivo del solicitante</p>
                        <p class="mt-0.5 text-sm text-neutral-700">{{ $aprobacion->motivo }}</p>
                        @if ($aprobacion->monto !== null)
                            <p class="mt-1 text-xs text-neutral-500">Magnitud: <span class="font-medium tabular-nums text-neutral-700">{{ number_format($aprobacion->monto, 0, ',', '.') }}</span></p>
                        @endif
                        @php
                            // Qué cambia exactamente (hallazgo #7 del QA 15-07): el payload ya
                            // trae anterior/nuevo — se pintan SOLO los campos que difieren.
                            // is_array: un payload con forma inesperada degrada a "sin diff".
                            $anterior = $aprobacion->datos['anterior'] ?? [];
                            $nuevo = $aprobacion->datos['nuevo'] ?? [];
                            $anterior = is_array($anterior) ? $anterior : [];
                            $nuevo = is_array($nuevo) ? $nuevo : [];
                            $cambios = collect([
                                'asignadas' => 'Asignadas', 'primera' => '1ª', 'segunda' => '2ª',
                                'malo' => 'Malos', 'danada' => 'Dañadas',
                            ])
                                ->filter(fn ($label, $campo) => array_key_exists($campo, $nuevo)
                                    && (int) ($anterior[$campo] ?? 0) !== (int) $nuevo[$campo])
                                ->map(fn ($label, $campo) => $label.': '.number_format((int) ($anterior[$campo] ?? 0), 0, ',', '.').' → '.number_format((int) $nuevo[$campo], 0, ',', '.'));
                        @endphp
                        @if ($cambios->isNotEmpty())
                            <p class="mt-1 text-xs tabular-nums text-neutral-700">{{ $cambios->implode(' · ') }}</p>
                        @endif
                    </div>

                    {{-- Acciones: aprobar = 1 tap (POST directo); rechazar abre
                         el panel del motivo (obligatorio). Botones h-12 ancho
                         completo, objetivo tactil de operario. --}}
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <form method="POST" action="{{ route('aprobaciones.aprobar', $aprobacion) }}">
                            @csrf
                            <x-primary-button type="submit" class="h-12 w-full justify-center">
                                Aprobar
                            </x-primary-button>
                        </form>
                        <x-secondary-button type="button" class="h-12 w-full justify-center"
                                            x-on:click="paneles.rechazo = ! paneles.rechazo">
                            Rechazar…
                        </x-secondary-button>
                    </div>

                    <div x-show="paneles.rechazo" x-cloak>
                        <form method="POST" action="{{ route('aprobaciones.rechazar', $aprobacion) }}" class="space-y-3">
                            @csrf
                            <x-reason-chips name="motivo" label="Motivo del rechazo"
                                            :options="\App\Models\Aprobacion::MOTIVOS_RECHAZO"
                                            :selected="old('motivo')" :allowOther="true" />
                            <x-danger-button type="submit" class="h-12 w-full justify-center">
                                Confirmar rechazo
                            </x-danger-button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
