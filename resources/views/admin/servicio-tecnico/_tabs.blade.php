{{--
    Barra de pestañas del flujo del técnico para UNA orden: navega entre las 3
    etapas del mismo dispensador. Se incluye en edit (recepción), cotización y
    reparación (parte del técnico). Requiere $orden y $activa in ['recepcion',
    'cotizacion', 'tecnico'].
--}}
@php
    $stTabs = [
        'recepcion' => ['label' => 'Recepción', 'url' => route('admin.servicio-tecnico.edit', $orden)],
        'cotizacion' => ['label' => 'Cotización', 'url' => route('admin.servicio-tecnico.cotizacion', $orden)],
        'tecnico' => ['label' => 'Parte del técnico', 'url' => route('admin.servicio-tecnico.reparacion', $orden)],
    ];
@endphp
<nav aria-label="Etapas de la orden"
     class="mb-4 grid grid-cols-3 gap-1 rounded-xl border border-neutral-200 bg-neutral-100 p-1">
    @foreach ($stTabs as $key => $tab)
        <a href="{{ $tab['url'] }}"
           @if ($key === $activa) aria-current="page" @endif
           class="rounded-lg px-1.5 py-2 text-center text-[13px] font-medium leading-tight transition sm:text-sm
                  {{ $key === $activa
                       ? 'bg-white text-brand-700 shadow-sm'
                       : 'text-neutral-500 hover:text-neutral-800' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
