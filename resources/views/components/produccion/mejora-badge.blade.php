@props(['estado'])

@php
    // Paleta de 4 colores: el significado va por relleno, no por matiz (mismo
    // criterio que estado-badge). Pendiente = naranjo suave (espera al jefe) ·
    // Revisada = gris suave (vista, en evaluacion) · Aplicada = neutro solido
    // (final bueno, como Aprobado) · Descartada = rojo suave (negativo
    // declarado, como Devuelto).
    $map = [
        'pendiente'  => ['label' => 'Pendiente',  'class' => 'bg-brand-50 text-brand-700 ring-brand-100'],
        'revisada'   => ['label' => 'Revisada',   'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'],
        'aplicada'   => ['label' => 'Aplicada',   'class' => 'bg-neutral-800 text-white ring-neutral-800'],
        'descartada' => ['label' => 'Descartada', 'class' => 'bg-red-50 text-red-700 ring-red-200'],
    ];

    $e = $map[$estado] ?? ['label' => $estado, 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$e['class']]) }}>{{ $e['label'] }}</span>
