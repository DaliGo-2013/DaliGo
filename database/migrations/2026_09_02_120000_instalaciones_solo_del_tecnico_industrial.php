<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El registro de Instalaciones es del técnico industrial, y de nadie más (dueño,
 * 02-09-2026).
 *
 * Mirando el menú de un jefe de ventas: *«saca de la vista del jefe de ventas el
 * apartado instalaciones porque eso es para Carlos Tablante, el técnico industrial,
 * que ingresa sus instalaciones para que le paguen el sueldo y horas extras. Es como
 * un respaldo personal… deshabilitarlo del jefe de ventas y vendedores»*.
 *
 * La pantalla nació como la planilla personal de Carlos y el seeder se la repartió
 * también a los jefes «por si acaso». Nunca fue de ellos. El permiso ya existía como
 * perilla en Administración → Roles (`gestionar instalaciones`); lo que falta es
 * quitárselo a quien no le corresponde.
 *
 * DOS roles, y no uno, a propósito: el seeder solo se lo dio a `jefe_ventas`, pero
 * en producción un usuario con rol `vendedor` lo veía en el menú (captura del dueño,
 * 01-09) — o sea que ese rol lo recibió desde la UI en algún momento. Sacarlo del
 * seeder no alcanza para ninguno de los dos: `givePermissionTo` SUMA y nunca quita.
 * Esta es la única vía para producción. Idempotente e invalida la caché de spatie.
 *
 * Lo conservan `tecnico_industrial` (su dueño) y `admin`.
 */
return new class extends Migration
{
    private const PERMISO = 'gestionar instalaciones';

    /** A quiénes se les quita. */
    private const ROLES = ['jefe_ventas', 'vendedor'];

    /**
     * A quién se le devuelve en `down()`: SOLO al jefe de ventas, porque es el único
     * al que el seeder se lo había dado — el vendedor lo tenía por fuera del código,
     * y devolvérselo sería volver a repartir algo que nunca se decidió repartir.
     */
    private const ROLES_REVERSIBLES = ['jefe_ventas'];

    public function up(): void
    {
        $permisoId = $this->permisoId();
        if (! $permisoId) {
            return; // base sin sembrar todavía (el seeder del deploy corre después).
        }

        $rolIds = $this->rolIds(self::ROLES);
        if ($rolIds) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permisoId)
                ->whereIn('role_id', $rolIds)
                ->delete();
        }

        $this->olvidarCache();
    }

    public function down(): void
    {
        $permisoId = $this->permisoId();
        if (! $permisoId) {
            return;
        }

        foreach ($this->rolIds(self::ROLES_REVERSIBLES) as $rolId) {
            $existe = DB::table('role_has_permissions')
                ->where('permission_id', $permisoId)
                ->where('role_id', $rolId)
                ->exists();

            if (! $existe) {
                DB::table('role_has_permissions')->insert(['permission_id' => $permisoId, 'role_id' => $rolId]);
            }
        }

        $this->olvidarCache();
    }

    private function permisoId(): ?int
    {
        return DB::table('permissions')->where('name', self::PERMISO)->where('guard_name', 'web')->value('id');
    }

    /** @return list<int> */
    private function rolIds(array $nombres): array
    {
        return DB::table('roles')->whereIn('name', $nombres)->where('guard_name', 'web')->pluck('id')->all();
    }

    /**
     * spatie cachea el mapa rol→permisos: sin esto el jefe de ventas seguiría viendo
     * Instalaciones hasta que la caché expire.
     */
    private function olvidarCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
