@props([
    'filtros' => [],
    'vacio' => 'No hay nada cargado todavía.',
    'sinResultados' => null,
    'limpiarHref' => null,
])

{{--
    Fila de "no hay nada" para un listado, con DOS mensajes distintos según por qué
    está vacío. Va dentro del <ul> de <x-list-card>.

    Por qué existe (pedido del dueño, 31-jul-2026): las tarjetas del Inicio enlazan a
    listados YA FILTRADOS (ej. «Reparadas 0» → ?estado=reparado). Con un mensaje fijo
    del tipo "No hay órdenes registradas", hacer clic en una tarjeta en 0 afirmaba que
    el sistema estaba vacío cuando en realidad había 2.732 órdenes y ninguna en ese
    estado. El mensaje mentía y además ocultaba el motivo real.

    Los dos casos NO son el mismo y no se responden igual:
      · sin filtros  → está vacío de verdad; corresponde decir cómo cargar el primero.
      · con filtros  → hay datos, pero ninguno coincide; corresponde ofrecer quitarlos.

    Params:
      $filtros       array de filtros activos de la pantalla (normalmente $filtros).
      $vacio         mensaje cuando NO hay filtros. Conviene que diga qué hacer.
      $sinResultados mensaje cuando SÍ hay filtros (tiene un default razonable).
      $limpiarHref   ruta del listado sin filtros; si viene, se ofrece el enlace.
      slot           (opcional) reemplaza a $vacio con contenido libre, para las
                     pantallas que explican en varias líneas qué va a aparecer ahí.
                     El caso CON filtros nunca usa el slot: ese texto explicativo
                     sería engañoso cuando el listado sí tiene datos.
--}}

@php
    // OJO: no se usa array_filter(). Descarta '0' y 0, y hay filtros cuyo valor
    // legítimo es justamente cero — «activo=0» significa "ver los inactivos", que
    // es un filtro bien activo. Con array_filter el mensaje volvería a mentir,
    // esta vez en el caso contrario.
    $hayFiltros = collect($filtros)
        ->contains(fn ($v) => $v !== null && $v !== '' && $v !== []);

    $usarSlot = ! $hayFiltros && trim($slot ?? '') !== '';
@endphp

<li class="px-6 py-8 text-center">
    @if ($usarSlot)
        {{ $slot }}
    @else
        <p class="text-sm text-neutral-500">
            {{ $hayFiltros
                ? ($sinResultados ?? 'Ningún resultado coincide con los filtros aplicados.')
                : $vacio }}
        </p>
    @endif

    @if ($hayFiltros && $limpiarHref)
        <a href="{{ $limpiarHref }}"
           class="mt-2 inline-block text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">
            Quitar los filtros
        </a>
    @endif
</li>
