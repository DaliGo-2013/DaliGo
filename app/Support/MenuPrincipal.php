<?php

namespace App\Support;

use App\Models\OrdenServicio;
use App\Models\User;

/**
 * Fuente única del menú principal (sidebar V4 "menú Talana"): módulos, ítems,
 * rutas, permisos, íconos y badges. La sidebar desktop y el drawer móvil se
 * renderizan desde ESTE árbol — nunca más dos copias del menú que driftean
 * (el nav viejo ya había divergido: el @canany móvil de ST omitía
 * 'gestionar tiempos reparacion' y el ítem admin Notificaciones no existía
 * en móvil).
 *
 * Misma regla anti-purga que AccesosDashboard: aquí viven DATOS (labels,
 * routes, permisos, keys de ícono/badge); las CLASES CSS viven literales en
 * los componentes x-sidebar-* / x-layout.* (Tailwind v4 purga lo que no esté
 * literal en un Blade escaneado).
 *
 * Contrato de cada entrada:
 * - Módulo CON 'items'  => acordeón (details/summary). Su visibilidad se
 *   DERIVA de los ítems: se muestra si el usuario puede ver al menos uno.
 * - Módulo SIN 'items'  => link directo de primer nivel (Dashboard,
 *   Mi producción, Aprobaciones — el acceso 1-clic del operario es a
 *   propósito, decisión del dueño 2026-07-24).
 * - 'permiso'           => permiso spatie; '|' = "cualquiera de" (canAny),
 *   igual que el middleware de routes/web.php. null = todo autenticado.
 * - 'activo'            => patrones para request()->routeIs(); todo patrón
 *   debe matchear su propia 'route' (candado en MenuPrincipalTest).
 * - 'activo_extra'      => patrones a nivel módulo para pantallas de detalle
 *   sin ítem propio (ej. show/edit de ST) — abren el acordeón igual.
 * - 'badge'             => key simbólica resuelta en badges();
 *   'badge_title' lleva ':n' que se reemplaza por el conteo (el texto
 *   "N equipo(s) por atender" es contrato de DashboardTest).
 */
class MenuPrincipal
{
    public const MODULOS = [
        'inicio' => [
            'label' => 'Dashboard',
            'icon' => 'home',
            'route' => 'dashboard',
            'activo' => ['dashboard'],
            'permiso' => null,
        ],
        'comercial' => [
            'label' => 'Comercial',
            'icon' => 'shopping-cart',
            'items' => [
                'catalogo' => ['label' => 'Catálogo', 'route' => 'admin.productos.index', 'activo' => ['admin.productos.*'], 'permiso' => 'manage productos'],
                'precios' => ['label' => 'Precios', 'route' => 'admin.listas-precios.index', 'activo' => ['admin.listas-precios.*'], 'permiso' => 'manage productos'],
                'clientes' => ['label' => 'Clientes', 'route' => 'admin.clientes.index', 'activo' => ['admin.clientes.*'], 'permiso' => 'manage clientes'],
            ],
        ],
        'operacion' => [
            'label' => 'Operación',
            'icon' => 'building-office-2',
            'items' => [
                'inventario' => ['label' => 'Inventario', 'route' => 'admin.bodegas.index', 'activo' => ['admin.bodegas.*'], 'permiso' => 'manage productos'],
                'produccion' => ['label' => 'Producción', 'route' => 'admin.produccion.index', 'activo' => ['admin.produccion.*'], 'permiso' => 'manage production'],
            ],
        ],
        'administracion' => [
            'label' => 'Administración',
            'icon' => 'shield-check',
            'items' => [
                'usuarios' => ['label' => 'Usuarios', 'route' => 'admin.users.index', 'activo' => ['admin.users.*'], 'permiso' => 'view users'],
                'roles' => ['label' => 'Roles', 'route' => 'admin.roles.index', 'activo' => ['admin.roles.*'], 'permiso' => 'manage roles'],
                'sucursales' => ['label' => 'Sucursales', 'route' => 'admin.sucursales.index', 'activo' => ['admin.sucursales.*'], 'permiso' => 'manage sucursales'],
                'configuracion' => ['label' => 'Configuración', 'route' => 'admin.configuracion.index', 'activo' => ['admin.configuracion.*'], 'permiso' => 'manage settings'],
                'auditoria' => ['label' => 'Auditoría', 'route' => 'admin.audits.index', 'activo' => ['admin.audits.*'], 'permiso' => 'view audit'],
                'notificaciones' => ['label' => 'Notificaciones', 'route' => 'admin.notificaciones.index', 'activo' => ['admin.notificaciones.*'], 'permiso' => 'view notificaciones'],
                // "Historial de…" a propósito: el QA 15-07 mostró que llamarlo
                // igual que la bandeja confunde (hallazgo #1 del acta).
                'historial-aprobaciones' => ['label' => 'Historial de aprobaciones', 'route' => 'admin.aprobaciones.index', 'activo' => ['admin.aprobaciones.*'], 'permiso' => 'view aprobaciones'],
            ],
        ],
        'mi-produccion' => [
            'label' => 'Mi producción',
            'icon' => 'user',
            'route' => 'produccion.mi.index',
            'activo' => ['produccion.mi.*'],
            'permiso' => 'report production',
        ],
        'aprobaciones' => [
            'label' => 'Aprobaciones',
            'icon' => 'check-badge',
            'route' => 'aprobaciones.index',
            'activo' => ['aprobaciones.*'],
            'permiso' => 'aprobar solicitudes',
        ],
        'servicio-tecnico' => [
            'label' => 'Servicio Técnico',
            'icon' => 'wrench-screwdriver',
            'badge' => 'st_pendientes',
            'badge_title' => ':n equipo(s) por atender',
            // Pantallas de detalle de ST (show, cotización, reparación…) no
            // tienen ítem propio pero deben abrir el acordeón del módulo.
            'activo_extra' => ['admin.servicio-tecnico.*'],
            'items' => [
                'listado' => ['label' => 'Listado', 'route' => 'admin.servicio-tecnico.index', 'activo' => ['admin.servicio-tecnico.index'], 'permiso' => 'view servicio tecnico|manage servicio tecnico'],
                // "Registrar ingreso" vive como botón dentro de Listado (no se duplica aquí).
                'lote' => ['label' => 'Ingreso por lote', 'route' => 'admin.servicio-tecnico.lote.create', 'activo' => ['admin.servicio-tecnico.lote.*'], 'permiso' => 'crear lote servicio'],
                'qr' => ['label' => 'Códigos QR', 'route' => 'admin.servicio-tecnico.qr', 'activo' => ['admin.servicio-tecnico.qr'], 'permiso' => 'manage servicio tecnico'],
                'informe' => ['label' => 'Informe', 'route' => 'admin.servicio-tecnico.informe', 'activo' => ['admin.servicio-tecnico.informe'], 'permiso' => 'view servicio tecnico|manage servicio tecnico'],
                'seguimiento' => ['label' => 'Seguimiento (boceto)', 'route' => 'admin.servicio-tecnico.seguimiento-demo', 'activo' => ['admin.servicio-tecnico.seguimiento-demo'], 'permiso' => 'view servicio tecnico|manage servicio tecnico'],
                'agenda-terreno' => ['label' => 'Agenda de terreno', 'route' => 'admin.agenda-terreno.index', 'activo' => ['admin.agenda-terreno.*'], 'permiso' => 'ver agenda terreno|agendar servicio terreno'],
                'servicios-terreno' => ['label' => 'Servicios de terreno', 'route' => 'admin.servicios-terreno.index', 'activo' => ['admin.servicios-terreno.*'], 'permiso' => 'agendar servicio terreno'],
                'instalaciones' => ['label' => 'Instalaciones', 'route' => 'admin.instalaciones.index', 'activo' => ['admin.instalaciones.*'], 'permiso' => 'gestionar instalaciones'],
                'tiempos-reparacion' => ['label' => 'Costos generales de reparación', 'route' => 'admin.tiempos-reparacion.index', 'activo' => ['admin.tiempos-reparacion.*'], 'permiso' => 'gestionar tiempos reparacion'],
            ],
        ],
    ];

    /**
     * Árbol podado por permisos para el usuario: módulos con al menos un ítem
     * visible (o links directos permitidos). La visibilidad del módulo se
     * deriva — no existe una lista @canany aparte que pueda driftear.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function para(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $arbol = [];
        foreach (self::MODULOS as $key => $modulo) {
            if (isset($modulo['items'])) {
                $items = array_filter(
                    $modulo['items'],
                    fn (array $item) => self::puedeVer($user, $item['permiso'])
                );
                if ($items !== []) {
                    $arbol[$key] = array_merge($modulo, ['items' => $items]);
                }
            } elseif (self::puedeVer($user, $modulo['permiso'])) {
                $arbol[$key] = $modulo;
            }
        }

        return $arbol;
    }

    /**
     * Módulo cuyo patrón 'activo' (de ítems o del módulo) matchea la ruta
     * actual: abre su acordeón y da el título de la topbar. null si la ruta
     * no pertenece al menú (ej. perfil).
     *
     * @return array<string, mixed>|null
     */
    public static function moduloActivo(): ?array
    {
        foreach (self::MODULOS as $key => $modulo) {
            if (request()->routeIs(...self::patronesDe($modulo))) {
                return array_merge($modulo, ['key' => $key]);
            }
        }

        return null;
    }

    /**
     * Resolución centralizada de badges (key simbólica => conteo). Migrado
     * del View::composer de AppServiceProvider: COUNT liviano sobre la
     * columna indexada `estado`, solo para quien puede ver servicio técnico.
     *
     * Memoizado EN EL REQUEST (los atributos mueren con él — seguro entre
     * requests de un mismo test): sidebar y topbar lo piden en la misma
     * página y el COUNT no debe correr dos veces.
     *
     * @return array<string, int>
     */
    public static function badges(?User $user): array
    {
        $atributos = request()->attributes;
        $key = 'dg.menu.badges.'.($user?->id ?? 0);

        if (! $atributos->has($key)) {
            $atributos->set($key, [
                'st_pendientes' => ($user && $user->canAny(['view servicio tecnico', 'manage servicio tecnico']))
                    ? OrdenServicio::pendientesTecnico()->count()
                    : 0,
            ]);
        }

        return $atributos->get($key);
    }

    /**
     * Mapa plano "modulo" o "modulo.item" => definición, para los candados de
     * MenuPrincipalTest (espejo de AccesosDashboard::cards()).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function items(): array
    {
        $items = [];
        foreach (self::MODULOS as $key => $modulo) {
            if (isset($modulo['items'])) {
                foreach ($modulo['items'] as $subKey => $item) {
                    $items["{$key}.{$subKey}"] = $item;
                }
            } else {
                $items[$key] = $modulo;
            }
        }

        return $items;
    }

    /** Patrones routeIs de un módulo: los suyos + los de sus ítems + extras. */
    private static function patronesDe(array $modulo): array
    {
        $patrones = array_merge(
            $modulo['activo'] ?? [],
            $modulo['activo_extra'] ?? []
        );
        foreach ($modulo['items'] ?? [] as $item) {
            $patrones = array_merge($patrones, $item['activo']);
        }

        return $patrones;
    }

    private static function puedeVer(User $user, ?string $permiso): bool
    {
        return $permiso === null || $user->canAny(explode('|', $permiso));
    }
}
