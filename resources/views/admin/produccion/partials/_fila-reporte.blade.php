{{-- Fila de un reporte en las colas del panel. Requiere: $reporte (con soplador
     y registros_count). $mostrarFecha antepone la fecha al subtitulo (para la
     lista "pendientes de otros dias"; la cola de hoy no la necesita). --}}
@php $mostrarFecha ??= false; @endphp
<x-list-row>
    <x-slot name="leading">
        <x-avatar>{{ mb_substr($reporte->soplador->name, 0, 1) }}</x-avatar>
    </x-slot>

    <a href="{{ route('admin.produccion.soplador', $reporte->soplador) }}"
       class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $reporte->soplador->name }}</a>
    <p class="truncate text-sm text-neutral-500">
        @if ($mostrarFecha)<span class="font-medium text-neutral-700">{{ $reporte->fecha->format('d/m') }}</span> · @endif
        Turno {{ $reporte->turno }} · asignadas {{ number_format($reporte->asignadas, 0, ',', '.') }}
    </p>

    <x-slot name="meta">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
            <x-produccion.metrica label="1ª" w="w-16">{{ $reporte->primera }}</x-produccion.metrica>
            <x-produccion.metrica label="2ª" w="w-16">{{ $reporte->segunda }}</x-produccion.metrica>
            <x-produccion.metrica label="Malos" w="w-24">{{ $reporte->malo }}</x-produccion.metrica>
            <x-produccion.metrica label="Dañadas" w="w-28">{{ $reporte->danada }}</x-produccion.metrica>
            <span class="inline-flex w-16 items-baseline gap-1 font-medium tabular-nums {{ $reporte->diferencia === 0 ? 'text-neutral-400' : 'text-neutral-900' }}">
                Δ <span>{{ $reporte->diferencia }}</span>
            </span>
            <x-produccion.estado-badge :estado="$reporte->estado" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <div class="flex items-center gap-1">
            <a href="{{ route('admin.produccion.reporte.show', $reporte) }}"
               class="whitespace-nowrap text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">
                Revisar
            </a>
            {{-- Eliminar una produccion asignada por error: solo borradores sin avances. --}}
            @if ($reporte->estado === \App\Models\ProduccionReporte::BORRADOR && $reporte->registros_count === 0)
                <form method="POST" action="{{ route('admin.produccion.reporte.destroy', $reporte) }}"
                      onsubmit="return confirm('¿Eliminar esta producción asignada? Aún no tiene avances.');">
                    @csrf
                    @method('DELETE')
                    <x-icon-button type="submit" variant="danger" label="Eliminar producción" title="Eliminar producción">
                        <x-icon.trash class="h-5 w-5" />
                    </x-icon-button>
                </form>
            @endif
        </div>
    </x-slot>
</x-list-row>
