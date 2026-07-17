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
            'crear lote servicio',        // conductor: ingreso por lote en ruta (acotado, NO edita el taller)
            // Agenda de terreno (tecnico industrial): plantas de osmosis,
            // llenadoras y lavadoras en el cliente.
            'agendar servicio terreno',   // jefe/vendedores: agendar trabajos + editar el catalogo de servicios
            'ver agenda terreno',         // tecnico industrial: ver la agenda y marcar lo realizado
            'gestionar instalaciones',    // tecnico industrial / jefes: registro de instalaciones (Excel de terreno)
            // Modulo Notificaciones (M15).
            'view notificaciones',        // ver el panel de todas las notificaciones del sistema
            // Modulo Aprobaciones (M14).
            'aprobar solicitudes',        // bandeja /aprobaciones: resolver pendientes del propio rol
            'view aprobaciones',          // historial completo del motor (admin)
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
            ->givePermissionTo(['manage clientes', 'view servicio tecnico', 'agendar servicio terreno']);
        // Jefes: reciben la bandeja de aprobaciones YA (M14) — queda vacia hasta
        // que un modulo les apunte reglas (M04 transferencias, M05 facturas);
        // ademas, resolver exige portar el rol_aprobador de la solicitud.
        Role::firstOrCreate(['name' => 'jefe_ventas', 'guard_name' => 'web'])
            ->givePermissionTo(['view users', 'manage clientes', 'view servicio tecnico', 'aprobar solicitudes', 'agendar servicio terreno', 'gestionar instalaciones']);
        // El jefe de bodega AUTORIZA la recepcion de lo que llego por QR (revisa
        // que los datos esten bien) y luego el tecnico repara. Por eso tiene
        // 'confirmar servicio tecnico' pero NO 'manage' (no ingresa/edita).
        Role::firstOrCreate(['name' => 'jefe_bodega', 'guard_name' => 'web'])
            ->givePermissionTo(['view users', 'manage production', 'view servicio tecnico', 'confirmar servicio tecnico', 'aprobar solicitudes']);
        // El conductor solo carga lotes de ingreso en ruta (permiso acotado): NO
        // edita órdenes ni la etapa de taller.
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web'])
            ->givePermissionTo(['crear lote servicio']);
        // El tecnico gestiona TODO el taller (M12): ingreso/edicion, etapa de
        // reparacion y tambien confirmar la recepcion (y puede cargar lotes).
        Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web'])
            ->givePermissionTo(['view servicio tecnico', 'manage servicio tecnico', 'confirmar servicio tecnico', 'crear lote servicio']);
        // El tecnico INDUSTRIAL trabaja en terreno (plantas de osmosis,
        // llenadoras, lavadoras en el cliente): ve su agenda y marca lo
        // realizado. Es un rol aparte del tecnico de taller.
        Role::firstOrCreate(['name' => 'tecnico_industrial', 'guard_name' => 'web'])
            ->givePermissionTo(['ver agenda terreno', 'gestionar instalaciones']);
        Role::firstOrCreate(['name' => 'soplador', 'guard_name' => 'web'])
            ->givePermissionTo('report production');
    }
}
