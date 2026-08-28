{{--
    Pestañas de Administración · cuentas (consolidación C1,
    PLAN-MENU-DENSIDAD): «Roles» dejó de ser ítem del menú y vive como
    pestaña de Usuarios.

    OJO permisos — el matiz del lote: Usuarios lo ven, además del admin,
    jefe_bodega y jefe_sucursal (`view users`; el jefe de VENTAS lo perdió el
    27-08-2026 por decisión del dueño), pero definir roles es SOLO admin
    (`manage roles`). La pestaña se GATEA (idioma del _tabs de ST y de la
    Agenda: las pestañas se calculan por permiso) y con una sola pestaña el
    nav no se dibuja — el jefe ve su listado de cuentas igual que siempre,
    sin una pestaña que le daría 403.
--}}
@php
    $cuentasTabs = [
        ['label' => 'Usuarios', 'url' => route('admin.users.index'), 'activa' => request()->routeIs('admin.users.*')],
    ];
    if (auth()->user()?->can('manage roles')) {
        $cuentasTabs[] = ['label' => 'Roles', 'url' => route('admin.roles.index'), 'activa' => request()->routeIs('admin.roles.*')];
    }
@endphp
@if (count($cuentasTabs) > 1)
    <x-tab-nav label="Secciones de cuentas" :tabs="$cuentasTabs" />
@endif
