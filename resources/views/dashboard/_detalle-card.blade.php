{{-- Detalle de una card de «Mi semana» (dueño 02-09: la frase corrida con
     cuatro números «quedaba apretada»): grilla 2×2 etiqueta-arriba /
     número-abajo, legible de un vistazo en 155px de card. $oscuro = la card
     del mes (fondo neutral-900). Solo clases ya presentes en el bundle. --}}
@php
    $etiqueta = $oscuro ? 'text-neutral-400' : 'text-neutral-500';
    $numero = $oscuro ? 'text-white' : 'text-neutral-900';
    $filas = [
        ['Pidieron', $card['asignadas']],
        ['1ª', $card['primera']],
        ['2ª', $card['segunda']],
        ['Malas', $card['malas']],
    ];
@endphp
<dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
    @foreach ($filas as [$rotulo, $valor])
        <div>
            <dt class="text-xs {{ $etiqueta }}">{{ $rotulo }}</dt>
            <dd class="text-sm font-medium leading-tight tabular-nums {{ $numero }}">{{ number_format($valor, 0, ',', '.') }}</dd>
        </div>
    @endforeach
</dl>
