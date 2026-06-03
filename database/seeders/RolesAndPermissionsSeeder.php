<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Roles y permisos base de DaliGo (idempotente).
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $createUsers = Permission::firstOrCreate(['name' => 'create users']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo($createUsers);

        Role::firstOrCreate(['name' => 'member']);
    }
}
