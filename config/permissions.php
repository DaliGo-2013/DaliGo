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
        'report production' => 'Reportar producción',
        'manage production' => 'Gestionar producción',
    ],
];
