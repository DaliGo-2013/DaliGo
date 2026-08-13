{{--
    Pestañas de la sección Catálogo (consolidación F1, PLAN-MENU-DENSIDAD):
    Productos y Listas de precios comparten el ítem «Catálogo» del menú y se
    navegan entre sí por esta barra. Mismo permiso (`manage productos`) en
    ambas pantallas, así que acá no hay gateo por rol.

    El render (y el porqué del aria-current="true") vive en <x-tab-nav>,
    extraído al 3er uso (Lote 3).
--}}
<x-tab-nav label="Secciones del catálogo" :tabs="[
    ['label' => 'Productos', 'url' => route('admin.productos.index'), 'activa' => request()->routeIs('admin.productos.*')],
    ['label' => 'Listas de precios', 'url' => route('admin.listas-precios.index'), 'activa' => request()->routeIs('admin.listas-precios.*')],
]" />
