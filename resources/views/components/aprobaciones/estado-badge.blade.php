@props(['estado'])

@php
    // Paleta de 4: significado por relleno, no por matiz (mismo idioma que
    // produccion.estado-badge). Pendiente = naranjo solido (requiere accion) ·
    // Aprobada = neutro solido (final) · Auto-aprobada = gris suave (fluyo sola) ·
    // Rechazada = rojo suave (negativo).
    $map = [
        \App\Models\Aprobacion::ESTADO_PENDIENTE => ['label' => 'Pendiente', 'class' => 'bg-brand-600 text-white ring-brand-600'],
        \App\Models\Aprobacion::ESTADO_APROBADA => ['label' => 'Aprobada', 'class' => 'bg-neutral-800 text-white ring-neutral-800'],
        \App\Models\Aprobacion::ESTADO_AUTO_APROBADA => ['label' => 'Auto-aprobada', 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'],
        \App\Models\Aprobacion::ESTADO_RECHAZADA => ['label' => 'Rechazada', 'class' => 'bg-red-50 text-red-700 ring-red-200'],
    ];
    $e = $map[$estado] ?? ['label' => $estado, 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$e['class']]) }}>{{ $e['label'] }}</span>
