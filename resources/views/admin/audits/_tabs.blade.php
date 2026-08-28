{{--
    Pestañas del «Registro del sistema» (consolidación C2,
    PLAN-MENU-DENSIDAD — la primera de MÚLTIPLES ítems): Auditoría,
    Notificaciones e Historial de aprobaciones dejaron de ser tres ítems del
    menú y viven como pestañas de un solo anfitrión (admin.audits.index).

    Permisos: los tres («view audit», «view notificaciones»,
    «view aprobaciones») hoy son solo-admin por construcción (viven únicamente
    en la lista maestra del seeder), pero CADA pestaña se gatea por el SUYO en
    tiempo de render — si la UI le suma uno de los tres a alguien, esa persona
    ve solo sus pestañas y jamás una que le daría 403. Con una sola pestaña el
    nav no se dibuja (idioma de C1/users).

    Nombres: la pestaña se llama «Aprobaciones» a secas y NO choca con la
    bandeja (el hallazgo #1 del QA 15-07 que defendía el «Historial de…»):
    vive DENTRO de «Registro del sistema», mientras la bandeja sigue sola en
    la sidebar y la campanita conserva su link «Historial de aprobaciones».
--}}
@php
    $registroTabs = [];
    if (auth()->user()?->can('view audit')) {
        $registroTabs[] = ['label' => 'Cambios', 'url' => route('admin.audits.index'), 'activa' => request()->routeIs('admin.audits.*')];
    }
    if (auth()->user()?->can('view notificaciones')) {
        $registroTabs[] = ['label' => 'Notificaciones', 'url' => route('admin.notificaciones.index'), 'activa' => request()->routeIs('admin.notificaciones.*')];
    }
    if (auth()->user()?->can('view aprobaciones')) {
        $registroTabs[] = ['label' => 'Aprobaciones', 'url' => route('admin.aprobaciones.index'), 'activa' => request()->routeIs('admin.aprobaciones.*')];
    }
@endphp
@if (count($registroTabs) > 1)
    <x-tab-nav label="Secciones del registro del sistema" :tabs="$registroTabs" />
@endif
