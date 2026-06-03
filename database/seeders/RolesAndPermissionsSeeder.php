<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Roles y permisos base de DaliGo (idempotente y fuente de verdad de los permisos base).
     *
     * Es seguro re-ejecutarlo: firstOrCreate y givePermissionTo son aditivos, asi que
     * no borra roles ni permisos creados desde la UI; solo garantiza que existan los
     * permisos base y que el rol 'admin' los tenga todos.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage roles',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($permissions);

        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
    }
}
