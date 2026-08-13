{{--
    Pestañas de Facturación (consolidación Lote 3, PLAN-MENU-DENSIDAD):
    «Estado» dejó de ser ítem del menú y vive como pestaña de Documentos.
    Mismo permiso (`emitir documentos tributarios`) en ambas pantallas, así
    que acá no hay gateo por rol.
--}}
<x-tab-nav label="Secciones de facturación" :tabs="[
    ['label' => 'Documentos', 'url' => route('admin.dte.index'), 'activa' => request()->routeIs('admin.dte.index')],
    ['label' => 'Estado de la conexión', 'url' => route('admin.dte.estado'), 'activa' => request()->routeIs('admin.dte.estado')],
]" />
