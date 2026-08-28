{{--
    Tab-nav de una CONSOLIDACIÓN del menú (PLAN-MENU-DENSIDAD): navega entre
    las pantallas hermanas que comparten un ítem anfitrión de la sidebar.
    Nació como partial del Catálogo (Lote 1) y se extrajo al 3er uso (Lote 3),
    como prometió el parte del Lote 1.

    Props: :tabs = lista de ['label' => …, 'url' => …, 'activa' => bool];
           label = aria-label del <nav>.

    OJO: la pestaña activa lleva aria-current="true", JAMÁS "page" — estas
    pestañas se montan en rutas de ÍTEM del menú, y tanto SidebarTest como
    MenuConsolidacionesTest cuentan exactamente UN aria-current="page" por
    página (el de la sidebar). El _tabs de ST NO usa este componente a
    propósito: vive en páginas de detalle (fuera de ese conteo), gatea sus
    pestañas por permiso y ahí aria-current="page" es correcto.
--}}
@props(['tabs', 'label'])
@php
    // Una columna por pestaña. Mapa count→clase con las clases LITERALES a
    // propósito (Tailwind v4 purga lo que no esté literal en un Blade
    // escaneado). Nació como ternario 3/2 y se volvió mapa al llegar la
    // primera consolidación de 4 pestañas (E1): con el ternario, 4 caían a
    // 2 columnas EN SILENCIO. Una 5ª pestaña cae al default sano de 2
    // columnas (envuelve en filas, no rompe) — agregar su entrada es una línea.
    $columnas = match (count($tabs)) {
        3 => 'grid-cols-3',
        4 => 'grid-cols-4',
        default => 'grid-cols-2',
    };
@endphp
<nav aria-label="{{ $label }}"
     class="grid {{ $columnas }} gap-1 rounded-xl border border-neutral-200 bg-neutral-100 p-1">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}"
           @if ($tab['activa']) aria-current="true" @endif
           {{-- flex + min-h-11: la pestaña medía 32px de alto en el celular. El flex
                es lo que permite centrar el rótulo cuando el mínimo lo hace más alto
                que su texto (con un `block` quedaría pegado arriba), y el rótulo
                sigue envolviendo en dos líneas si no cabe. Desde sm: vuelve la
                densidad de escritorio. --}}
           class="flex min-h-11 items-center justify-center rounded-lg px-1.5 py-2 text-center text-[13px] font-medium leading-tight transition sm:min-h-0 sm:text-sm
                  {{ $tab['activa']
                       ? 'bg-white text-brand-700 shadow-sm'
                       : 'text-neutral-500 hover:text-neutral-800' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
