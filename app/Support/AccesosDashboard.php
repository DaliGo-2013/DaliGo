<?php

namespace App\Support;

/**
 * Fuente única de los accesos directos del Inicio (M16): key estable, grupo,
 * permiso, ícono y color por defecto de cada card, más las keys de la paleta
 * personalizable por usuario (D-013). Las CLASES CSS de cada color NO viven
 * aquí: Tailwind v4 purga lo que no esté literal en un Blade escaneado, así
 * que el mapa key→clases está en resources/views/dashboard.blade.php ($paleta).
 */
class AccesosDashboard
{
    /** Keys de la paleta de colores elegibles por card (default sobrio: naranjo/gris). */
    public const COLORES = ['naranjo', 'gris', 'celeste', 'verde', 'ambar', 'violeta', 'turquesa', 'indigo'];

    /**
     * Accesos por grupo (mismos grupos que el nav). `route` es el NOMBRE de la
     * ruta (se resuelve con route() al armar la vista); `icon` es un
     * componente de resources/views/components/icon/.
     */
    public const GRUPOS = [
        'Comercial' => [
            'catalogo' => ['label' => 'Catálogo', 'desc' => 'Productos: peso y dimensiones para despacho', 'route' => 'admin.productos.index', 'permiso' => 'manage productos', 'icon' => 'cube', 'color' => 'naranjo'],
            'precios' => ['label' => 'Precios', 'desc' => 'Listas de precios (espejo de Bsale)', 'route' => 'admin.listas-precios.index', 'permiso' => 'manage productos', 'icon' => 'tag', 'color' => 'naranjo'],
            'clientes' => ['label' => 'Clientes', 'desc' => 'Ficha local sincronizada con Bsale', 'route' => 'admin.clientes.index', 'permiso' => 'manage clientes', 'icon' => 'user-group', 'color' => 'naranjo'],
        ],
        'Operación' => [
            'inventario' => ['label' => 'Inventario', 'desc' => 'Stock por bodega (espejo de Bsale)', 'route' => 'admin.bodegas.index', 'permiso' => 'manage productos', 'icon' => 'archive-box', 'color' => 'naranjo'],
            'produccion' => ['label' => 'Producción', 'desc' => 'Asignar y revisar reportes de soplado', 'route' => 'admin.produccion.index', 'permiso' => 'manage production', 'icon' => 'cog-6-tooth', 'color' => 'naranjo'],
        ],
        'Servicio Técnico' => [
            'servicio-tecnico' => ['label' => 'Servicio Técnico', 'desc' => 'Ingreso de máquinas y lavadoras al taller', 'route' => 'admin.servicio-tecnico.index', 'permiso' => 'manage servicio tecnico', 'icon' => 'wrench-screwdriver', 'color' => 'naranjo'],
        ],
        'Administración' => [
            'usuarios' => ['label' => 'Usuarios', 'desc' => 'Cuentas y roles del equipo', 'route' => 'admin.users.index', 'permiso' => 'view users', 'icon' => 'users', 'color' => 'gris'],
            'roles' => ['label' => 'Roles', 'desc' => 'Permisos por rol', 'route' => 'admin.roles.index', 'permiso' => 'manage roles', 'icon' => 'shield-check', 'color' => 'gris'],
            'sucursales' => ['label' => 'Sucursales', 'desc' => 'Mirador, Coquimbo, Abate Molina, Buzeta', 'route' => 'admin.sucursales.index', 'permiso' => 'manage sucursales', 'icon' => 'building-storefront', 'color' => 'gris'],
            'configuracion' => ['label' => 'Configuración', 'desc' => 'Parámetros globales de la app', 'route' => 'admin.configuracion.index', 'permiso' => 'manage settings', 'icon' => 'adjustments-horizontal', 'color' => 'gris'],
            'auditoria' => ['label' => 'Auditoría', 'desc' => 'Quién cambió qué y cuándo', 'route' => 'admin.audits.index', 'permiso' => 'view audit', 'icon' => 'document-magnifying-glass', 'color' => 'gris'],
            'notificaciones' => ['label' => 'Notificaciones', 'desc' => 'Envíos, reintentos y fallas', 'route' => 'admin.notificaciones.index', 'permiso' => 'view notificaciones', 'icon' => 'bell', 'color' => 'gris'],
            'aprobaciones' => ['label' => 'Aprobaciones', 'desc' => 'Historial del motor de aprobaciones', 'route' => 'admin.aprobaciones.index', 'permiso' => 'view aprobaciones', 'icon' => 'check-badge', 'color' => 'gris'],
        ],
    ];

    /**
     * Mapa plano key => definición (+grupo), para validar el guardado.
     *
     * @return array<string, array<string, string>>
     */
    public static function cards(): array
    {
        $cards = [];
        foreach (self::GRUPOS as $grupo => $items) {
            foreach ($items as $key => $def) {
                $cards[$key] = $def + ['grupo' => $grupo];
            }
        }

        return $cards;
    }
}
