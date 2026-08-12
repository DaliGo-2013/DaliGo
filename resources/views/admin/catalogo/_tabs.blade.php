{{--
    Pestañas de la sección Catálogo (consolidación F1, PLAN-MENU-DENSIDAD):
    Productos y Listas de precios comparten el ítem «Catálogo» del menú y se
    navegan entre sí por esta barra. Ambas pantallas viven detrás del MISMO
    permiso (`manage productos`), así que acá no hay gateo por rol.

    OJO: la pestaña activa lleva aria-current="true", JAMÁS "page" — estas
    pestañas se montan en rutas de ÍTEM del menú, y tanto SidebarTest como
    MenuConsolidacionesTest cuentan exactamente UN aria-current="page" por
    página (el de la sidebar).
--}}
@php
    $catalogoTabs = [
        ['label' => 'Productos', 'url' => route('admin.productos.index'), 'activa' => request()->routeIs('admin.productos.*')],
        ['label' => 'Listas de precios', 'url' => route('admin.listas-precios.index'), 'activa' => request()->routeIs('admin.listas-precios.*')],
    ];
@endphp
<nav aria-label="Secciones del catálogo"
     class="grid grid-cols-2 gap-1 rounded-xl border border-neutral-200 bg-neutral-100 p-1">
    @foreach ($catalogoTabs as $tab)
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
