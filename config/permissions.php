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
        'manage servicio tecnico' => 'Gestionar servicio técnico',
        'confirmar servicio tecnico' => 'Confirmar recepción (servicio técnico)',
        'autorizar reparacion' => 'Autorizar reparación (pago de la cotización)',
        'view notificaciones' => 'Ver notificaciones',
        'aprobar solicitudes' => 'Aprobar solicitudes (bandeja)',
        'view aprobaciones' => 'Ver historial de aprobaciones',
        'gestionar instalaciones' => 'Gestionar instalaciones (terreno)',
        'gestionar tiempos reparacion' => 'Gestionar tiempos de reparación (costos generales)',
    ],
];
