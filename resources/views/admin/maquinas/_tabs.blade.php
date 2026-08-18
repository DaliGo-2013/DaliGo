{{--
    Pestañas de «Configuración de producción» (consolidación E1,
    PLAN-MENU-DENSIDAD — el cierre del mapa): Máquinas, Tipos de botellón,
    Recetas y Moldes dejaron de ser cuatro ítems del menú y viven como
    pestañas de un solo anfitrión (admin.maquinas.index, la anfitriona por
    ser la primera de la fila física: máquina → molde → tipo → receta).

    SIN gateo a propósito: los cuatro comparten el MISMO permiso
    (`manage production`) por construcción — la precondición B1/carga. Si
    algún día uno de los cuatro gana permiso propio, este _tabs pasa al
    idioma gateado de C1/users (pestañas calculadas por permiso).

    Primera tab-nav de CUATRO: la deuda del componente (ternario 3/2) se
    pagó en este mismo lote — ver el mapa count→clase en tab-nav.blade.php.
--}}
@php
    $configTabs = [
        ['label' => 'Máquinas', 'url' => route('admin.maquinas.index'), 'activa' => request()->routeIs('admin.maquinas.*')],
        ['label' => 'Tipos de botellón', 'url' => route('admin.tipos-botellon.index'), 'activa' => request()->routeIs('admin.tipos-botellon.*')],
        ['label' => 'Recetas', 'url' => route('admin.recetas.index'), 'activa' => request()->routeIs('admin.recetas.*')],
        ['label' => 'Moldes', 'url' => route('admin.moldes.index'), 'activa' => request()->routeIs('admin.moldes.*')],
    ];
@endphp
<x-tab-nav label="Secciones de configuración de producción" :tabs="$configTabs" />
