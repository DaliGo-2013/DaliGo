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

    ACTUALIZADO 14-08-2026: el técnico industrial SÍ ve el tarifario, con un
    permiso propio de solo lectura (`ver servicios terreno`). Pedido del dueño:
    «para Carlos crear el permiso de vista, actualmente no le aparece» — en la
    planta del cliente le preguntan cuánto sale y qué incluye. La pestaña ahora
    se gatea con CUALQUIERA de los dos permisos; editar sigue siendo de
    jefatura/ventas y los botones de la pantalla se esconden solos.
--}}
@php
    $agendaTabs = [
        ['label' => 'Agenda', 'url' => route('admin.agenda-terreno.index'), 'activa' => request()->routeIs('admin.agenda-terreno.*')],
    ];
    if (auth()->user()?->canAny(['agendar servicio terreno', 'ver servicios terreno'])) {
        $agendaTabs[] = ['label' => 'Servicios de terreno', 'url' => route('admin.servicios-terreno.index'), 'activa' => request()->routeIs('admin.servicios-terreno.*')];
    }
@endphp
@if (count($agendaTabs) > 1)
    <x-tab-nav label="Secciones de la agenda de terreno" :tabs="$agendaTabs" />
@endif
