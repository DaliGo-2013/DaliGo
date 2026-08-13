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
<nav aria-label="{{ $label }}"
     class="grid {{ count($tabs) === 3 ? 'grid-cols-3' : 'grid-cols-2' }} gap-1 rounded-xl border border-neutral-200 bg-neutral-100 p-1">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}"
           @if ($tab['activa']) aria-current="true" @endif
           class="rounded-lg px-1.5 py-2 text-center text-[13px] font-medium leading-tight transition sm:text-sm
                  {{ $tab['activa']
                       ? 'bg-white text-brand-700 shadow-sm'
                       : 'text-neutral-500 hover:text-neutral-800' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
