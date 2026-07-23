<x-app-layout>
    {{-- Pantalla de operario: lista de las producciones del dia. Un soplador
         puede tener varias; cada tarjeta abre su reporte (mi.show). --}}
    <div class="py-4 sm:py-8">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-semibold leading-tight text-neutral-900">Mi producción</h2>
                <p class="text-xs text-neutral-500">{{ \App\Support\FechaNegocio::ahora()->translatedFormat('l d \\d\\e F') }}</p>
            </div>

            <x-status-alert :status="session('status')" class="mb-4" />

            <x-produccion.indicador-red />

            {{-- Reportes devueltos pendientes de otros días --}}
            @if ($devueltos->isNotEmpty())
                <div class="dg-enter mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-medium">Tienes {{ $devueltos->count() === 1 ? 'un reporte devuelto' : 'reportes devueltos' }} por corregir:</p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach ($devueltos as $devuelto)
                            <li>
                                <a href="{{ route('produccion.mi.show', $devuelto) }}" class="font-medium underline underline-offset-2">
                                    {{ $devuelto->fecha->translatedFormat('d \\d\\e F') }} · turno {{ $devuelto->turno }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($reportes->isEmpty())
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-sm text-neutral-500">No tienes producciones asignadas para hoy.</p>
                    <p class="mt-1 text-xs text-neutral-400">El jefe de bodega debe asignarte preformas antes de poder reportar.</p>
                </div>
            @else
                <div class="dg-enter space-y-3">
                    @foreach ($reportes as $reporte)
                        @php
                            $editable = $reporte->editablePorSoplador();
                            // Nombre de la preforma + procedencia (saco/caja) si la asignación la trae.
                            $preforma = collect([
                                $reporte->asignacion?->preforma?->nombre,
                                $reporte->asignacion?->procedencia ? 'en '.$reporte->asignacion->procedencia : null,
                            ])->filter()->implode(' · ') ?: null;
                        @endphp
                        <a href="{{ route('produccion.mi.show', $reporte) }}"
                           class="block rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm transition duration-150 hover:bg-neutral-50 active:scale-[0.99] sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-neutral-900">
                                        {{ $reporte->asignadas }} preformas
                                        <span class="font-normal text-neutral-500">· turno {{ $reporte->turno }}</span>
                                    </p>
                                    @if ($preforma)
                                        <p class="truncate text-xs text-neutral-500">{{ $preforma }}</p>
                                    @endif
                                </div>
                                <x-produccion.estado-badge :estado="$reporte->estado" />
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <p class="text-xs text-neutral-500">
                                    @if ($reporte->registros_count > 0)
                                        Llevas {{ $reporte->total }} <span class="text-brand-600">({{ $reporte->producido }} vendibles)</span>
                                    @else
                                        Sin avances todavía.
                                    @endif
                                </p>
                                <span class="shrink-0 text-sm font-semibold {{ $editable ? 'text-brand-700' : 'text-neutral-400' }}">
                                    {{ $editable ? 'Reportar →' : 'Ver →' }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
