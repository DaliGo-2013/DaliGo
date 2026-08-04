@props(['estado'])

@php
    // Paleta de 4: significado por relleno/peso, no por matiz. Borrador y las
    // llaves intermedias = tintes que suben de peso (la cadena avanza);
    // en_ruta = naranjo sólido (en movimiento, foco); cerrada = neutro sólido
    // (final).
    $map = [
        \App\Models\HojaDeRuta::BORRADOR => ['label' => 'Borrador', 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'],
        \App\Models\HojaDeRuta::PAGOS_OK => ['label' => 'Pagos OK', 'class' => 'bg-brand-50 text-brand-700 ring-brand-100'],
        \App\Models\HojaDeRuta::RUTA_AUTORIZADA => ['label' => 'Ruta autorizada', 'class' => 'bg-brand-100 text-brand-700 ring-brand-200'],
        \App\Models\HojaDeRuta::CARGADA => ['label' => 'Cargada', 'class' => 'bg-brand-200 text-brand-700 ring-brand-300'],
        \App\Models\HojaDeRuta::EN_RUTA => ['label' => 'En ruta', 'class' => 'bg-brand-600 text-white ring-brand-600'],
        \App\Models\HojaDeRuta::CERRADA => ['label' => 'Cerrada', 'class' => 'bg-neutral-800 text-white ring-neutral-800'],
    ];
    $e = $map[$estado] ?? ['label' => $estado, 'class' => 'bg-neutral-100 text-neutral-600 ring-neutral-200'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$e['class']]) }}>{{ $e['label'] }}</span>
