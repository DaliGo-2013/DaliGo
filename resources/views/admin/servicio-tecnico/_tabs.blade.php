{{--
    Barra de pestañas del flujo del técnico para UNA orden: navega entre las 3
    etapas del mismo dispensador (Recepción · Parte del técnico · Cotización).
    Se incluye en show (ficha), edit (recepción), cotización y reparación.
    Requiere $orden y $activa in ['recepcion', 'cotizacion', 'tecnico'].
--}}
@php
    $stTabs = [];
    // Recepción: SIEMPRE visible. Quien puede editar la recepción va a la vista
    // editable; el resto (p. ej. el técnico de taller) a la ficha de solo lectura.
    $stTabs['recepcion'] = [
        'label' => 'Recepción',
        'url' => auth()->user()?->can('editar recepcion servicio tecnico')
            ? route('admin.servicio-tecnico.edit', $orden)
            : route('admin.servicio-tecnico.show', $orden),
    ];
    // Parte del técnico y Cotización son etapas de taller (permiso manage); un
    // rol que solo VE la orden (vendedor/jefe) no las ve para no chocar con 403.
    // El orden de estas dos es el del arreglo: primero el parte —donde se registra
    // el trabajo Y se le pone precio desde el 20-08— y después la cotización, que
    // es la vista previa de lo que el cliente lee (no se edita ahí).
    if (auth()->user()?->can('manage servicio tecnico')) {
        $stTabs['tecnico'] = ['label' => 'Parte del técnico', 'url' => route('admin.servicio-tecnico.reparacion', $orden)];
        $stTabs['cotizacion'] = ['label' => 'Cotización', 'url' => route('admin.servicio-tecnico.cotizacion', $orden)];
    }
@endphp
@if (count($stTabs) > 1)
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
@endif
