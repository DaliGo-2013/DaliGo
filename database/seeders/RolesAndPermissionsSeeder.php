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
            'manage clientes',
            // Modulo Produccion.
            'report production',  // soplador: ve y envia su reporte diario
            'manage production',  // jefe de bodega: asigna y revisa/aprueba/devuelve
            // Modulo Servicio Tecnico (taller).
            'view servicio tecnico',      // jefes/vendedores: ver listado + detalle (solo lectura)
            'manage servicio tecnico',    // tecnico: ingreso/edicion + etapa de taller
            'confirmar servicio tecnico', // jefe de bodega / tecnico: autorizar la recepcion de lo que llego por QR
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($permissions);

        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

        // Roles del negocio: 8 roles ASCII (reconciliados por la migracion
        // reconcile_business_roles; los legacy Soplador/Jefatura ya no existen).
        // givePermissionTo es aditivo: garantiza el piso de permisos sin borrar
        // los que un admin haya agregado desde la UI. NO migrar a syncPermissions:
        // revertiria esas personalizaciones en cada deploy.
        // Regla #2 del negocio: "la gestion es por VENDEDOR" -> los vendedores
        // trabajan con la cartera de clientes desde el dia 1.
        // Vendedores y jefes pueden VER el estado de las maquinas en taller (solo
        // lectura), aunque no las gestionen.
        Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web'])
            ->givePermissionTo(['manage clientes', 'view servicio tecnico']);
        Role::firstOrCreate(['name' => 'jefe_ventas', 'guard_name' => 'web'])
            ->givePermissionTo(['view users', 'manage clientes', 'view servicio tecnico']);
        // El jefe de bodega AUTORIZA la recepcion de lo que llego por QR (revisa
        // que los datos esten bien) y luego el tecnico repara. Por eso tiene
        // 'confirmar servicio tecnico' pero NO 'manage' (no ingresa/edita).
        Role::firstOrCreate(['name' => 'jefe_bodega', 'guard_name' => 'web'])
            ->givePermissionTo(['view users', 'manage production', 'view servicio tecnico', 'confirmar servicio tecnico']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        // El tecnico gestiona TODO el taller (M12): ingreso/edicion, etapa de
        // reparacion y tambien confirmar la recepcion.
        Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web'])
            ->givePermissionTo(['view servicio tecnico', 'manage servicio tecnico', 'confirmar servicio tecnico']);
        Role::firstOrCreate(['name' => 'soplador', 'guard_name' => 'web'])
            ->givePermissionTo('report production');
    }
}
