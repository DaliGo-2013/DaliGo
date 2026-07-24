<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Por pedido de gerencia: el técnico industrial deja de AGENDAR/editar la
     * agenda (solo la ve y marca "realizado"). El seeder es aditivo, así que a los
     * roles YA sembrados hay que revocarles el permiso explícitamente aquí. La
     * edición de la recepción del técnico de taller NO necesita revocación: se
     * limita moviendo esas rutas a un permiso nuevo que el técnico no tiene.
     *
     * Idempotente y defensiva: no truena si el rol o el permiso aún no existen
     * (en tests el seeder corre después y ya trae el default correcto).
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'tecnico_industrial')->where('guard_name', 'web')->first();
        $permiso = Permission::where('name', 'agendar servicio terreno')->where('guard_name', 'web')->first();

        if ($role && $permiso) {
            $role->revokePermissionTo($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::where('name', 'tecnico_industrial')->where('guard_name', 'web')->first();
        $permiso = Permission::where('name', 'agendar servicio terreno')->where('guard_name', 'web')->first();

        if ($role && $permiso) {
            $role->givePermissionTo($permiso);
        }
    }
};
