<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Etiquetas de permisos (UI)
    |--------------------------------------------------------------------------
    |
    | Nombre tecnico del permiso (spatie) => etiqueta legible en espanol.
    | Fuente unica para las vistas de roles (antes estaba copiada en cada
    | vista). Si una vista no encuentra la clave, cae al nombre tecnico.
    | Se cachea con `config:cache` en produccion (deploy.sh).
    |
    */
    'labels' => [
        'view users' => 'Ver usuarios',
        'create users' => 'Crear usuarios',
        'edit users' => 'Editar usuarios',
        'delete users' => 'Eliminar usuarios',
        'manage roles' => 'Gestionar roles',
        'manage sucursales' => 'Gestionar sucursales',
        'manage settings' => 'Gestionar configuración',
        'view audit' => 'Ver auditoría',
        'manage productos' => 'Gestionar catálogo',
        'manage clientes' => 'Gestionar clientes',
        'report production' => 'Reportar producción',
        'manage production' => 'Gestionar producción',
        'view servicio tecnico' => 'Ver servicio técnico',
        'ver todo servicio tecnico' => 'Ver TODO el servicio técnico (no solo la cartera propia)',
        'manage servicio tecnico' => 'Gestionar servicio técnico',
        'ver informe dispensadores' => 'Ver informe de dispensadores (taller)',
        'ver informe industrial' => 'Ver informe industrial (terreno)',
        'editar recepcion servicio tecnico' => 'Editar recepción / eliminar orden (servicio técnico)',
        'confirmar servicio tecnico' => 'Confirmar recepción (servicio técnico)',
        'autorizar reparacion' => 'Autorizar reparación (pago de la cotización)',
        'aplicar descuento servicio tecnico' => 'Aplicar descuento en la cotización (servicio técnico)',
        'view notificaciones' => 'Ver notificaciones',
        'gestionar notificaciones' => 'Gestionar canales de notificación (correo/WhatsApp del perfil)',
        'aprobar solicitudes' => 'Aprobar solicitudes (bandeja)',
        'view aprobaciones' => 'Ver historial de aprobaciones',
        'crear lote servicio' => 'Ingresar lote de máquinas (conductor en ruta)',
        'agendar servicio terreno' => 'Agendar trabajos en terreno + catálogo de servicios',
        'ver agenda terreno' => 'Ver la agenda de terreno y marcar trabajos realizados',
        'gestionar instalaciones' => 'Gestionar instalaciones (terreno)',
        'gestionar tiempos reparacion' => 'Gestionar tiempos de reparación (costos generales)',
        'manage despachos' => 'Gestionar despachos',
        'confirmar entrega' => 'Confirmar entrega (conductor)',
        'emitir documentos tributarios' => 'Emitir boletas y facturas',
        'emitir nota de credito' => 'Emitir notas de crédito (anular un documento)',
        'ver plan proyecto' => 'Ver el plan del proyecto (carta Gantt)',
        'gestionar plan proyecto' => 'Gestionar los trabajos extras del plan del proyecto',
        // Traslado de máquinas a reparar (03-08): faltaban sus etiquetas, así que
        // la pantalla de Roles mostraba el nombre técnico crudo.
        'despachar traslado servicio' => 'Despachar máquinas a reparar (sucursal → taller)',
        'recibir traslado servicio' => 'Confirmar la recepción de máquinas en el taller',
        // Logística · flota de vehículos (04-08).
        'ver vehiculos' => 'Ver la flota de vehículos y sus vencimientos',
        'manage vehiculos' => 'Gestionar la flota (crear, editar, dar de baja)',
        'simular carga' => 'Usar el simulador de carga (¿cuánto entra en cada camión?)',
        // Devoluciones (M13, 04-08).
        'view devoluciones' => 'Ver devoluciones',
        'manage devoluciones' => 'Gestionar devoluciones (recibir, categorizar, resolver)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categorías de permisos (UI de Roles)
    |--------------------------------------------------------------------------
    |
    | Agrupa los permisos por dominio en la pantalla de Roles. Cada categoría
    | lista SUBSTRINGS que se buscan en el nombre técnico del permiso; el PRIMER
    | keyword que matchea (recorriendo en este orden) manda. Los permisos que no
    | matchean ninguno caen en "Generales" (fallback). Así, cuando se agrega un
    | permiso nuevo con el tiempo, se deriva SOLO a su categoría; si abre un
    | dominio nuevo, basta con agregar una categoría aquí. El orden define además
    | el orden en que se muestran las categorías. Ver App\Support\PermisosAgrupados.
    |
    */
    'grupos' => [
        // 'traslado servicio' es propio: los dos permisos del traslado NO contienen
        // el literal 'servicio tecnico', así que sin este keyword caían en
        // "Generales" en la pantalla de Roles.
        'Servicio técnico' => ['servicio tecnico', 'traslado servicio', 'lote servicio', 'reparacion', 'descuento', 'informe'],
        'Terreno' => ['servicio terreno', 'agenda terreno', 'instalaciones'],
        'Producción' => ['production'],
        // Despachos (M07) va ANTES de Comercial: 'entrega' no colisiona con
        // ningún permiso de ST/terreno (verificado sobre la lista de labels).
        'Despachos' => ['despachos', 'entrega'],
        // Logística (04-08): hoy es la flota; cuando crezca (mantenciones,
        // kilometraje) sus permisos caen solos en esta categoría.
        'Logística' => ['vehiculos', 'carga'],
        // Devoluciones (M13): sin esta categoría, sus permisos caerían en
        // "Generales" (el fallback) — gotcha documentado en PLAN-M13 §3.
        'Devoluciones' => ['devoluciones'],
        'Comercial' => ['clientes', 'productos'],
        'Usuarios y accesos' => ['users', 'roles'],
        'Aprobaciones' => ['aprobaciones', 'solicitudes'],
        'Notificaciones' => ['notificaciones'],
        'Sistema' => ['settings', 'sucursales', 'audit', 'plan proyecto'],
    ],
];
