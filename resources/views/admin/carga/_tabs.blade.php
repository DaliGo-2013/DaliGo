{{--
    Pestañas del Simulador de carga (consolidación B1, PLAN-MENU-DENSIDAD):
    «Cargas reales» dejó de ser ítem del menú y vive como pestaña del
    Simulador. Permiso IDÉNTICO (`simular carga`, mismo grupo de rutas) en
    ambas pantallas — la precondición limpia del patrón: sin gateo por rol.
--}}
<x-tab-nav label="Secciones del simulador" :tabs="[
    ['label' => 'Simulador', 'url' => route('admin.carga.index'), 'activa' => request()->routeIs('admin.carga.*')],
    ['label' => 'Cargas reales', 'url' => route('admin.cargas-reales.index'), 'activa' => request()->routeIs('admin.cargas-reales.*')],
]" />
