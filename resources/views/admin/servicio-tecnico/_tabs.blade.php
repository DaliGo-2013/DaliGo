{{--
    Barra de pestañas del flujo del técnico para UNA orden: navega entre las 3
    etapas del mismo dispensador. Se incluye en edit (recepción), cotización y
    reparación (parte del técnico). Requiere $orden y $activa in ['recepcion',
    'cotizacion', 'tecnico'].
--}}
@php
    $stTabs = [];
    // La pestaña Recepción (editable) solo la ve quien puede editar la recepción;
    // el técnico trabaja con Cotización + Parte del técnico (y ve el resumen de la
    // recepción dentro de esas pantallas).
    if (auth()->user()?->can('editar recepcion servicio tecnico')) {
        $stTabs['recepcion'] = ['label' => 'Recepción', 'url' => route('admin.servicio-tecnico.edit', $orden)];
    }
    $stTabs['cotizacion'] = ['label' => 'Cotización', 'url' => route('admin.servicio-tecnico.cotizacion', $orden)];
    $stTabs['tecnico'] = ['label' => 'Parte del técnico', 'url' => route('admin.servicio-tecnico.reparacion', $orden)];
@endphp
<nav aria-label="Etapas de la orden"
     class="mb-4 grid {{ count($stTabs) === 3 ? 'grid-cols-3' : 'grid-cols-2' }} gap-1 rounded-xl border border-neutral-200 bg-neutral-100 p-1">
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
