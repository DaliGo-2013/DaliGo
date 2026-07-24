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
            'ver todo servicio tecnico',  // tecnico/jefes: ver TODAS las ordenes; sin este permiso, 'view' queda acotado a la cartera propia (regla #2: gestion por vendedor)
            'manage servicio tecnico',    // tecnico: ingreso + etapa de taller (parte del tecnico) + cotizacion
            'editar recepcion servicio tecnico', // gerencia: EDITAR los datos de recepcion de una orden + eliminarla (aparte de 'manage', para poder limitarlo al tecnico)
            'confirmar servicio tecnico', // jefe de bodega / tecnico: autorizar la recepcion de lo que llego por QR
            'autorizar reparacion',       // vendedor/jefe_ventas/tecnico: coordina el pago de la cotizacion y autoriza al tecnico a reparar
            'aplicar descuento servicio tecnico', // jefe_ventas/admin: aplicar descuentos en la cotizacion (decision comercial; el tecnico NO)
            'crear lote servicio',        // conductor: ingreso por lote en ruta (acotado, NO edita el taller)
            // Agenda de terreno (tecnico industrial): plantas de osmosis,
            // llenadoras y lavadoras en el cliente.
            'agendar servicio terreno',   // jefe/vendedores: agendar trabajos + editar el catalogo de servicios
            'ver agenda terreno',         // tecnico industrial: ver la agenda y marcar lo realizado
            'gestionar instalaciones',    // tecnico industrial / jefes: registro de instalaciones (Excel de terreno)
            'gestionar tiempos reparacion', // jefatura: catálogo de horas estándar por trabajo (mano de obra fija)
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
        // El vendedor VE el estado de las maquinas en taller (solo lectura), pero
        // acotado a SU cartera: tiene 'view servicio tecnico' y NO 'ver todo
        // servicio tecnico', asi que el listado y las fichas se filtran a los
        // clientes que tiene asignados (+ los de su equipo si es jefatura).
        Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web'])
            ->givePermissionTo(['manage clientes', 'view servicio tecnico', 'agendar servicio terreno', 'autorizar reparacion']);
        // Jefes: reciben la bandeja de aprobaciones YA (M14) — queda vacia hasta
        // que un modulo les apunte reglas (M04 transferencias, M05 facturas);
        // ademas, resolver exige portar el rol_aprobador de la solicitud.
        // Jefe de ventas supervisa TODO el servicio técnico (pedido de gerencia):
        // taller (Fernando) — gestiona/edita/confirma y aplica descuentos — e
        // industrial (Carlos) — ya agenda terreno + instalaciones. El DESCUENTO es
        // decisión comercial: solo jefe_ventas/admin lo aplican (el técnico no).
        Role::firstOrCreate(['name' => 'jefe_ventas', 'guard_name' => 'web'])
            ->givePermissionTo(['view users', 'manage clientes', 'view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'editar recepcion servicio tecnico', 'confirmar servicio tecnico', 'aplicar descuento servicio tecnico', 'aprobar solicitudes', 'agendar servicio terreno', 'gestionar instalaciones', 'autorizar reparacion', 'gestionar tiempos reparacion']);
        // El jefe de bodega AUTORIZA la recepcion de lo que llego por QR (revisa
        // que los datos esten bien) y luego el tecnico repara. Por eso tiene
        // 'confirmar servicio tecnico' pero NO 'manage' (no ingresa/edita).
        Role::firstOrCreate(['name' => 'jefe_bodega', 'guard_name' => 'web'])
            ->givePermissionTo(['view users', 'manage production', 'view servicio tecnico', 'ver todo servicio tecnico', 'confirmar servicio tecnico', 'aprobar solicitudes']);
        // El conductor solo carga lotes de ingreso en ruta (permiso acotado): NO
        // edita órdenes ni la etapa de taller.
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web'])
            ->givePermissionTo(['crear lote servicio']);
        // El tecnico gestiona TODO el taller (M12): ingreso/edicion, etapa de
        // reparacion y tambien confirmar la recepcion (y puede cargar lotes).
        Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web'])
            ->givePermissionTo(['view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'confirmar servicio tecnico', 'crear lote servicio', 'autorizar reparacion']);
        // El tecnico INDUSTRIAL trabaja en terreno (plantas de osmosis,
        // llenadoras, lavadoras en el cliente): gestiona su agenda (agenda,
        // edita y marca lo realizado desde el calendario) e instalaciones. Es un
        // rol aparte del tecnico de taller.
        // El tecnico INDUSTRIAL, por pedido de gerencia, solo VE su agenda (y marca
        // realizado); NO agenda ni edita la agenda (eso lo hacen jefes/vendedores).
        // Mantiene su registro de Instalaciones (su planilla). Si gerencia quiere
        // habilitarle agendar, lo activa en Administracion -> Roles.
        Role::firstOrCreate(['name' => 'tecnico_industrial', 'guard_name' => 'web'])
            ->givePermissionTo(['ver agenda terreno', 'gestionar instalaciones']);
        Role::firstOrCreate(['name' => 'soplador', 'guard_name' => 'web'])
            ->givePermissionTo('report production');
    }
}
