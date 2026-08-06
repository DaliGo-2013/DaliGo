{{-- Badges de una bodega: espejo Bsale (virtual/inactiva) + capa local M04-F1.
     Doctrina de color: atencion/accion pendiente = brand; en reposo = neutral. --}}
@if ($bodega->es_virtual)
    <x-badge variant="neutral">virtual</x-badge>
@endif
@unless ($bodega->activa)
    <x-badge variant="neutral">inactiva</x-badge>
@endunless
@if ($bodega->proposito)
    <x-badge variant="neutral">{{ mb_strtolower(\App\Models\Bodega::PROPOSITOS[$bodega->proposito] ?? $bodega->proposito) }}</x-badge>
@endif
@if ($bodega->estado_baja !== null)
    <x-badge variant="brand">en baja</x-badge>
@elseif (! $bodega->clasificacion_confirmada)
    @if ($bodega->proposito === null)
        <x-badge variant="brand">nueva — por clasificar</x-badge>
    @else
        <x-badge variant="brand">por confirmar</x-badge>
    @endif
@endif
