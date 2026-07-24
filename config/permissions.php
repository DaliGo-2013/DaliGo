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
        'editar recepcion servicio tecnico' => 'Editar recepción / eliminar orden (servicio técnico)',
        'confirmar servicio tecnico' => 'Confirmar recepción (servicio técnico)',
        'autorizar reparacion' => 'Autorizar reparación (pago de la cotización)',
        'aplicar descuento servicio tecnico' => 'Aplicar descuento en la cotización (servicio técnico)',
        'view notificaciones' => 'Ver notificaciones',
        'gestionar notificaciones' => 'Gestionar canales de notificación (correo/WhatsApp del perfil)',
        'aprobar solicitudes' => 'Aprobar solicitudes (bandeja)',
        'view aprobaciones' => 'Ver historial de aprobaciones',
        'gestionar instalaciones' => 'Gestionar instalaciones (terreno)',
        'gestionar tiempos reparacion' => 'Gestionar tiempos de reparación (costos generales)',
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
        'Servicio técnico' => ['servicio tecnico', 'lote servicio', 'reparacion', 'descuento'],
        'Terreno' => ['servicio terreno', 'agenda terreno', 'instalaciones'],
        'Producción' => ['production'],
        'Comercial' => ['clientes', 'productos'],
        'Usuarios y accesos' => ['users', 'roles'],
        'Aprobaciones' => ['aprobaciones', 'solicitudes'],
        'Notificaciones' => ['notificaciones'],
        'Sistema' => ['settings', 'sucursales', 'audit'],
    ],
];
