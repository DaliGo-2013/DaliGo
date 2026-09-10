@props(['reporte'])

{{-- Etiqueta «Modificado» (dueño 09-09): el jefe cambió cantidades de este
     reporte. Naranjo = requiere atención (paleta de 4); el estado del reporte
     sigue en su propio badge, al lado. Lee ajustes_count si la lista vino con
     withCount (sin N+1); si no, una consulta exists. --}}
@if ($reporte->modificado)
    <x-badge variant="brand" {{ $attributes }}>Modificado</x-badge>
@endif
