{{-- Detalle desplegable de las tarjetas Realizados / Pendientes del informe
     industrial. $trabajos = colección de App\Models\AgendaTrabajo; $modo =
     'realizado' (muestra lo que se HIZO: notas del técnico) | 'pendiente'
     (muestra lo que se VA a realizar: la descripción del pedido). --}}
@php
    $modo = $modo ?? 'pendiente';
    $etiquetaDetalle = $modo === 'realizado' ? 'Lo que se hizo' : 'Lo que se va a realizar';
@endphp
<ul class="divide-y divide-neutral-100">
    @forelse ($trabajos as $t)
        @php
            // Realizado: prioriza las notas del técnico (lo efectivamente hecho);
            // si no las cargó, cae en la descripción del pedido. Pendiente: la
            // descripción de lo que se irá a hacer.
            $detalle = $modo === 'realizado'
                ? ($t->notas_tecnico ?: $t->descripcion)
                : $t->descripcion;
        @endphp
        <li class="px-4 py-3 sm:px-6">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-neutral-900">
                        {{ $t->fecha?->translatedFormat('d M Y') ?? 'Sin fecha' }}
                        @if ($t->hora_corta)
                            <span class="font-normal text-neutral-500">· {{ $t->hora_corta }}</span>
                        @endif
                    </p>
                    <p class="mt-0.5 truncate text-sm text-neutral-700">{{ $t->cliente_nombre ?: 'Sin cliente' }}</p>
                    <p class="mt-0.5 text-xs text-neutral-400">
                        {{ $t->servicio?->nombre ?: 'Fuera de tarifa' }}
                        @if ($t->ciudad)
                            <span> · {{ $t->ciudad }}</span>
                        @endif
                        @if ($t->tecnico?->name)
                            <span> · {{ $t->tecnico->name }}</span>
                        @endif
                    </p>
                </div>
                <x-badge :variant="\App\Models\AgendaTrabajo::ESTADO_VARIANTES[$t->estado] ?? 'brand'" class="shrink-0">
                    {{ $t->tipo_label }}
                </x-badge>
            </div>
            <p class="mt-1.5 text-sm text-neutral-600">
                <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ $etiquetaDetalle }}:</span>
                {{ $detalle ?: '—' }}
            </p>
        </li>
    @empty
        <li class="px-4 py-6 text-center text-sm text-neutral-500 sm:px-6">
            {{ $modo === 'realizado' ? 'Sin trabajos realizados en el período.' : 'Sin trabajos pendientes en el período.' }}
        </li>
    @endforelse
</ul>
