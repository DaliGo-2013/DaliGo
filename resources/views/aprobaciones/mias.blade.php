<x-app-layout>
    {{-- Historial personal del solicitante (M14): que pedi, en que quedo y por
         que. Solo lectura; el mismo idioma compacto de la bandeja. --}}
    <div class="mx-auto max-w-2xl space-y-4 px-4 py-6 sm:px-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-neutral-900">Mis solicitudes</h1>
            @can('aprobar solicitudes')
                <a href="{{ route('aprobaciones.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Bandeja</a>
            @endcan
        </div>

        @if ($solicitudes->isEmpty())
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white px-6 py-10 text-center shadow-sm">
                <p class="text-sm font-medium text-neutral-900">Sin solicitudes</p>
                <p class="mt-1 text-sm text-neutral-500">Cuando una acción tuya requiera aprobación, aparecerá aquí.</p>
            </div>
        @else
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <ul class="divide-y divide-neutral-100">
                    @foreach ($solicitudes as $solicitud)
                        <li class="space-y-1 px-4 py-4 sm:px-6">
                            <div class="flex items-start justify-between gap-3">
                                <p class="min-w-0 text-sm font-medium text-neutral-900">{{ $solicitud->descripcion }}</p>
                                <x-aprobaciones.estado-badge :estado="$solicitud->estado" class="shrink-0" />
                            </div>
                            <p class="text-xs text-neutral-500">
                                {{ $solicitud->etiquetaTipo() }} · {{ $solicitud->created_at->format('d-m-Y H:i') }}
                                @if ($solicitud->resuelta_at)
                                    · resuelta {{ $solicitud->resuelta_at->diffForHumans() }}
                                @endif
                            </p>
                            @if ($solicitud->estado === \App\Models\Aprobacion::ESTADO_RECHAZADA && $solicitud->resultado_motivo)
                                <p class="text-xs text-red-700">{{ $solicitud->resultado_motivo }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
