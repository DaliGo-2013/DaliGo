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
            'manage sucursales',
            'manage settings',
            'view audit',
            'manage productos',
            // Modulo Produccion.
            'report production',  // soplador: ve y envia su reporte diario
            'manage production',  // jefe de bodega: asigna y revisa/aprueba/devuelve
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($permissions);

        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

        // Modulo Produccion: el operario reporta, la jefatura gestiona.
        // firstOrCreate es aditivo: si los roles ya existen (creados desde la UI),
        // solo se les agrega el permiso correspondiente.
        Role::firstOrCreate(['name' => 'Soplador', 'guard_name' => 'web'])
            ->givePermissionTo('report production');
        Role::firstOrCreate(['name' => 'Jefatura', 'guard_name' => 'web'])
            ->givePermissionTo('manage production');

        // Roles del negocio (Incremento 2): matriz de partida.
        // givePermissionTo es aditivo: garantiza el piso de permisos sin borrar
        // los que un admin haya agregado desde la UI. NO migrar a syncPermissions:
        // revertiria esas personalizaciones en cada deploy.
        Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe_ventas', 'guard_name' => 'web'])
            ->givePermissionTo('view users');
        Role::firstOrCreate(['name' => 'jefe_bodega', 'guard_name' => 'web'])
            ->givePermissionTo('view users');
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web']);
    }
}
