{{--
    Pestañas del LISTADO de Servicio Técnico (consolidación A2,
    PLAN-MENU-DENSIDAD): «Traslados al taller» dejó de ser ítem del menú y
    vive como pestaña del Listado — es un FLUJO y por eso pestaña de primer
    nivel (la config del taller va en el desplegable «Configuración» de la
    cabecera, lotes 4 y A1). OJO: distinto del _tabs.blade.php de esta misma
    carpeta, que son las etapas de UNA orden en sus páginas de detalle.

    Cada pestaña se gatea por SU permiso (idioma del _tabs de ST): el Listado
    lo ven view|manage; Traslados, despachar|recibir (la cadena de custodia
    tiene dos puntas). Con una sola pestaña el nav no se dibuja.
--}}
@php
    $listadoTabs = [];
    if (auth()->user()?->canAny(['view servicio tecnico', 'manage servicio tecnico'])) {
        $listadoTabs[] = ['label' => 'Listado', 'url' => route('admin.servicio-tecnico.index'), 'activa' => request()->routeIs('admin.servicio-tecnico.index')];
    }
    if (auth()->user()?->canAny(['despachar traslado servicio', 'recibir traslado servicio'])) {
        $listadoTabs[] = ['label' => 'Traslados al taller', 'url' => route('admin.traslados.index'), 'activa' => request()->routeIs('admin.traslados.*')];
    }
@endphp
@if (count($listadoTabs) > 1)
    <x-tab-nav label="Secciones del taller" :tabs="$listadoTabs" />
@endif
