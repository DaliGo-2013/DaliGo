@props(['estado'])

@php
    // Paleta de 4: significado por relleno/peso, no por matiz (regla de diseño).
    // Preparado = gris suave (en cola) · Retirado/En ruta = naranjo sólido (en
    // movimiento, foco) · Entregado = neutro sólido (cerrado/final) ·
    // Entrega parcial = rojo suave (problema a resolver).
    $map = [
        \App\Models\Despacho::PREPARADO => ['label' => 'Preparado', 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'],
        \App\Models\Despacho::RETIRADO => ['label' => 'Retirado', 'class' => 'bg-brand-600 text-white ring-brand-600'],
        \App\Models\Despacho::EN_RUTA => ['label' => 'En ruta', 'class' => 'bg-brand-600 text-white ring-brand-600'],
        \App\Models\Despacho::ENTREGADO => ['label' => 'Entregado', 'class' => 'bg-neutral-800 text-white ring-neutral-800'],
        \App\Models\Despacho::ENTREGA_PARCIAL => ['label' => 'Entrega parcial', 'class' => 'bg-red-50 text-red-700 ring-red-200'],
    ];
    $e = $map[$estado] ?? ['label' => $estado, 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$e['class']]) }}>{{ $e['label'] }}</span>
