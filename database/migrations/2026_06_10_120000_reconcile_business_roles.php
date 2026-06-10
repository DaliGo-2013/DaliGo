<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Reconcilia los roles del negocio a 8 nombres ASCII limpios (auditoria
     * 2026-06-08, docs/AUDITORIA-M01-M02.md):
     *
     *  - 'Soplador'  -> renombrado a 'soplador' (UPDATE preserva role_id =>
     *    asignaciones y permisos colgados intactos).
     *  - 'Jefatura'  -> consolidado en 'jefe_bodega' (el destino ya existe):
     *    se reasignan sus usuarios y se elimina. El seeder (corre despues en
     *    el deploy) le garantiza manage production a jefe_bodega.
     *  - 'Ventas'    -> rol huerfano creado a mano; se elimina SOLO si no
     *    tiene usuarios.
     *
     * En una BD fresca (tests / instalacion nueva) la tabla roles esta vacia
     * al migrar -> todo es no-op y el seeder crea los nombres correctos.
     *
     * OJO MySQL 5.7: la collation utf8mb4_unicode_ci es case-insensitive, asi
     * que no se usan guards tipo where(name,'soplador')->exists() (matchearian
     * 'Soplador'). El rename directo es inocuo si re-corre.
     */
    public function up(): void
    {
        // 1) Renombrar Soplador -> soplador.
        DB::table('roles')->where('name', 'Soplador')->where('guard_name', 'web')
            ->update(['name' => 'soplador']);

        // 2) Consolidar Jefatura en jefe_bodega.
        $jefatura = DB::table('roles')->where('name', 'Jefatura')->where('guard_name', 'web')->first();

        if ($jefatura) {
            $jefeBodega = DB::table('roles')
                ->where('name', 'jefe_bodega')->where('guard_name', 'web')
                ->where('id', '!=', $jefatura->id)
                ->first();

            if ($jefeBodega) {
                // Reasignar usuarios (defensivo; insertOrIgnore evita duplicar el pivote).
                foreach (DB::table('model_has_roles')->where('role_id', $jefatura->id)->get() as $asignacion) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $jefeBodega->id,
                        'model_type' => $asignacion->model_type,
                        'model_id' => $asignacion->model_id,
                    ]);
                }

                DB::table('model_has_roles')->where('role_id', $jefatura->id)->delete();
                DB::table('role_has_permissions')->where('role_id', $jefatura->id)->delete();
                DB::table('roles')->where('id', $jefatura->id)->delete();
            } else {
                // BD sin jefe_bodega: renombrar directo (preserva asignaciones).
                DB::table('roles')->where('id', $jefatura->id)->update(['name' => 'jefe_bodega']);
            }
        }

        // 3) Borrar el rol huerfano 'Ventas' SOLO si no tiene usuarios.
        $ventas = DB::table('roles')->where('name', 'Ventas')->where('guard_name', 'web')->first();

        if ($ventas && ! DB::table('model_has_roles')->where('role_id', $ventas->id)->exists()) {
            DB::table('role_has_permissions')->where('role_id', $ventas->id)->delete();
            DB::table('roles')->where('id', $ventas->id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * No-op: reconciliacion puntual de datos; el deploy es forward-only y el
     * seeder vuelve a garantizar la matriz si se re-siembra.
     */
    public function down(): void
    {
        //
    }
};
