<?php

use App\Support\PermisosSoloAdmin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADMINISTRACIÓN es solo del admin (decisión del dueño, 27-08-2026).
 *
 * Mirando la ficha del rol jefe_ventas: *«no entiendo por qué ve conductores…»*
 * había sido el reporte anterior; este es su hermano — *«necesito que el rol jefe
 * de ventas no tenga la opción de ver administración… ya que es la opción para
 * cambiar y habilitar permisos, no debe tener acceso ningún perfil salvo admin»*.
 *
 * Dos cosas, y son distintas:
 *
 *  1. `view users` sale de `jefe_ventas`. Era lo único que le hacía aparecer el
 *     módulo ADMINISTRACIÓN en el menú: la visibilidad de un módulo se deriva de
 *     sus ítems, y Usuarios era el único para el que calificaba. Reversible.
 *
 *  2. Los permisos que REPARTEN permisos se le quitan a TODO rol que no sea
 *     admin. Hoy, en una base sembrada por el seeder, solo admin los tiene — pero
 *     la pantalla de Roles los repartía como cualquier otro, así que pudieron
 *     haberse dado desde la UI en cualquier momento y nadie lo sabría. Este
 *     barrido cierra ese pasado; el candado del futuro es
 *     `App\Support\PermisosSoloAdmin` (el controlador los descarta y la vista los
 *     dibuja bloqueados).
 *
 * Por qué una migración y no solo el seeder: `RolesAndPermissionsSeeder` usa
 * `givePermissionTo`, que SUMA y nunca quita — sacar un permiso de esa lista no
 * revoca nada en una base ya sembrada. Esta es la única vía para producción.
 * Idempotente (si ya no está, no hace nada) e invalida la caché de permisos de
 * spatie, que si no seguiría sirviendo el mapa viejo hasta que expire.
 */
return new class extends Migration
{
    private const PERMISO_MENU = 'view users';

    private const ROL_MENU = 'jefe_ventas';

    public function up(): void
    {
        $this->menuDelJefeDeVentas(quitar: true);
        $this->soloAdminRepartePermisos();
        $this->olvidarCache();
    }

    /**
     * Reversible SOLO en su primera mitad: devolverle el listado de usuarios al
     * jefe de ventas es un `insert` que sabemos hacer.
     *
     * El barrido del punto 2 NO se revierte, y es a propósito: no guardamos qué
     * rol tenía cuál de los cuatro permisos antes de correr, así que un `down()`
     * tendría que repartirlos a ciegas — y repartir permisos de acceso a ciegas es
     * exactamente lo que esta migración existe para impedir. Si hiciera falta
     * devolverle uno a alguien, se hace desde Administración → Roles… que ahora los
     * veta: o sea que la respuesta correcta es asignarle el rol `admin` a esa
     * persona, que es la decisión que el dueño tomó.
     */
    public function down(): void
    {
        $this->menuDelJefeDeVentas(quitar: false);
        $this->olvidarCache();
    }

    private function menuDelJefeDeVentas(bool $quitar): void
    {
        $permisoId = $this->permisoId(self::PERMISO_MENU);
        $rolId = DB::table('roles')->where('name', self::ROL_MENU)->where('guard_name', 'web')->value('id');

        if (! $permisoId || ! $rolId) {
            return; // base sin sembrar todavía (el seeder del deploy corre después).
        }

        $fila = DB::table('role_has_permissions')->where('permission_id', $permisoId)->where('role_id', $rolId);

        if ($quitar) {
            $fila->delete();
        } elseif (! $fila->exists()) {
            DB::table('role_has_permissions')->insert(['permission_id' => $permisoId, 'role_id' => $rolId]);
        }
    }

    /**
     * Le quita los cuatro permisos de acceso a todo rol que no sea `admin`.
     *
     * El `whereIn` va sobre los ids de los permisos que EXISTEN: si alguno todavía
     * no fue sembrado, la lista queda más corta y el resto se limpia igual. Con la
     * lista vacía no se ejecuta el delete —un `whereIn` con `[]` no borra nada,
     * pero dejarlo correr invita al gotcha del `whereNotIn([])` de la bitácora.
     */
    private function soloAdminRepartePermisos(): void
    {
        $permisoIds = DB::table('permissions')
            ->whereIn('name', PermisosSoloAdmin::PERMISOS)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();

        $adminId = DB::table('roles')
            ->where('name', PermisosSoloAdmin::ROL)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permisoIds || ! $adminId) {
            return;
        }

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permisoIds)
            ->where('role_id', '!=', $adminId)
            ->delete();
    }

    private function permisoId(string $nombre): ?int
    {
        return DB::table('permissions')->where('name', $nombre)->where('guard_name', 'web')->value('id');
    }

    /**
     * spatie cachea el mapa rol→permisos: sin esto, el jefe de ventas seguiría
     * viendo ADMINISTRACIÓN hasta que la caché expire (deploy.sh no corre
     * `cache:clear`, y `permission:cache-reset` corre ANTES de este punto).
     */
    private function olvidarCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
