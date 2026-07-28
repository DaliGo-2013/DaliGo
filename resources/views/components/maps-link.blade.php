@props(['direccion', 'ciudad' => null])

{{-- Dirección TOCABLE: abre la app de mapas con la dirección buscada. El
     técnico la tenía como texto plano y debía copiarla a la mano en Maps.

     Se usa el esquema universal de Google Maps (`?api=1&query=`) y NO `geo:`
     ni `maps://`: en iOS abre Google Maps si está instalada y, si no, cae al
     navegador; en Android abre la app directa. La ciudad entra en la búsqueda
     para desambiguar calles que se repiten entre comunas. --}}
@php
    $consulta = trim(collect([$direccion, $ciudad])->filter()->implode(', '));
@endphp
@if ($consulta !== '')
    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($consulta) }}"
       target="_blank" rel="noopener noreferrer"
       {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-start gap-1.5 text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700 active:scale-[0.98] sm:min-h-0']) }}>
        <x-icon.map-pin class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ $consulta }}</span>
    </a>
@endif
