@props(['estado'])

@php
    $map = [
        'borrador' => ['label' => 'Borrador', 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'],
        'enviado'  => ['label' => 'Enviado',  'class' => 'bg-amber-50 text-amber-700 ring-amber-200'],
        'aprobado' => ['label' => 'Aprobado', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'devuelto' => ['label' => 'Devuelto', 'class' => 'bg-red-50 text-red-700 ring-red-200'],
    ];
    $e = $map[$estado] ?? ['label' => $estado, 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$e['class']]) }}>{{ $e['label'] }}</span>
