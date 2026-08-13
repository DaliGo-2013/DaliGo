{{--
    Pestañas de la Agenda de terreno (consolidación Lote 5, PLAN-MENU-DENSIDAD):
    «Servicios de terreno» (el tarifario UF) dejó de ser ítem del menú y vive
    como pestaña de la Agenda.

    OJO permisos — el matiz de este lote: la Agenda la ve también el técnico
    industrial con solo `ver agenda terreno`, y el tarifario exige `agendar
    servicio terreno`. Su pestaña se GATEA (idioma del _tabs de ST: las
    pestañas se calculan por permiso) y con una sola pestaña el nav no se
    dibuja — el técnico ve su agenda igual que siempre, sin una pestaña que
    le daría 403.
--}}
@php
    $agendaTabs = [
        ['label' => 'Agenda', 'url' => route('admin.agenda-terreno.index'), 'activa' => request()->routeIs('admin.agenda-terreno.*')],
    ];
    if (auth()->user()?->can('agendar servicio terreno')) {
        $agendaTabs[] = ['label' => 'Servicios de terreno', 'url' => route('admin.servicios-terreno.index'), 'activa' => request()->routeIs('admin.servicios-terreno.*')];
    }
@endphp
@if (count($agendaTabs) > 1)
    <x-tab-nav label="Secciones de la agenda de terreno" :tabs="$agendaTabs" />
@endif
