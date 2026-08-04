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
            // Traslado de maquinas sucursal -> casa matriz (decision del dueño 03-08).
            // Son DOS permisos y no uno: despacha la sucursal, recibe el taller, y
            // que una misma persona pueda cerrar las dos puntas anularia la cadena
            // de custodia que este registro existe para tener.
            'despachar traslado servicio', // jefe de sucursal + administrativos de esa sucursal
            'recibir traslado servicio',   // tecnico, jefe de bodega y jefe de ventas (en la matriz)
            // Agenda de terreno (tecnico industrial): plantas de osmosis,
            // llenadoras y lavadoras en el cliente.
            'agendar servicio terreno',   // jefe/vendedores: agendar trabajos + editar el catalogo de servicios
            'ver agenda terreno',         // tecnico industrial: ver la agenda y marcar lo realizado
            'gestionar instalaciones',    // tecnico industrial / jefes: registro de instalaciones (Excel de terreno)
            'gestionar tiempos reparacion', // jefatura: catálogo de horas estándar por trabajo (mano de obra fija)
            // Informes de Servicio Tecnico (por dominio): el tecnico de taller ve
            // solo Dispensadores; el tecnico industrial solo Industrial; jefes/admin ambos.
            'ver informe dispensadores', // informe del taller (dispensadores)
            'ver informe industrial',    // informe del servicio en terreno (industrial)
            // Modulo Notificaciones (M15).
            'view notificaciones',        // ver el panel de todas las notificaciones del sistema
            'gestionar notificaciones',   // editar las preferencias de canal (correo/WhatsApp) del perfil — SOLO Luis + TI (pedido del jefe); distinto de 'view notificaciones' (panel de solo lectura)
            // Modulo Aprobaciones (M14).
            'aprobar solicitudes',        // bandeja /aprobaciones: resolver pendientes del propio rol
            'view aprobaciones',          // historial completo del motor (admin)
            // Unidad DESPACHOS-v1 (M05 parcial + M07 + M08 MVP).
            'manage despachos',           // jefe de bodega: crea despachos y valida retiros (QR)
            'confirmar entrega',          // conductor: confirma la entrega con firma+foto (PWA)
            // Facturacion electronica (M05 · DTE). Ver PROYECTO_DALIGO.md §10.
            'emitir documentos tributarios', // emitir boleta/factura desde DaliGo
            'emitir nota de credito',        // ANULAR un documento ya emitido (el unico camino: los DTE no se borran)
            // Plan del proyecto (/plan, carta Gantt transicional).
            'ver plan proyecto',       // consultar la pagina (gantt + tracker + hitos + extras)
            'gestionar plan proyecto', // crear/editar/eliminar los "trabajos extras en paralelo"
            // Modulo LOGISTICA · flota de vehiculos (pedido del dueño 04-08-2026).
            // DOS permisos: ver la flota y sus vencimientos es una consulta que
            // manana necesita cobranzas (paga permisos de circulacion y SOAP);
            // editar las fechas es de quien mantiene el registro. Separarlos
            // ahora evita tener que abrir el codigo cuando exista ese perfil.
            'ver vehiculos',
            'manage vehiculos',
            // Simulador de carga: es una calculadora, la usa ventas.
            'simular carga',
            // Modulo Devoluciones (M13, flujo A-12). DOS permisos: consultar el
            // listado/ficha es distinto de recibir, categorizar y resolver.
            'view devoluciones',
            'manage devoluciones',
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
            // 'simular carga': el vendedor es el usuario PRINCIPAL del simulador —
            // arma la ruta y responde "¿cuanto le cabe?" sin adivinar. Es solo
            // lectura y no escribe nada operativo, asi que no hay riesgo en darlo.
            ->givePermissionTo(['manage clientes', 'view servicio tecnico', 'agendar servicio terreno', 'autorizar reparacion', 'ver informe dispensadores', 'ver informe industrial', 'simular carga']);
        // Jefes: reciben la bandeja de aprobaciones YA (M14) — queda vacia hasta
        // que un modulo les apunte reglas (M04 transferencias, M05 facturas);
        // ademas, resolver exige portar el rol_aprobador de la solicitud.
        // Jefe de ventas supervisa TODO el servicio técnico (pedido de gerencia):
        // taller (Fernando) — gestiona/edita/confirma y aplica descuentos — e
        // industrial (Carlos) — ya agenda terreno + instalaciones. El DESCUENTO es
        // decisión comercial: solo jefe_ventas/admin lo aplican (el técnico no).
        Role::firstOrCreate(['name' => 'jefe_ventas', 'guard_name' => 'web'])
            ->givePermissionTo(['view users', 'manage clientes', 'view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'editar recepcion servicio tecnico', 'confirmar servicio tecnico', 'recibir traslado servicio', 'aplicar descuento servicio tecnico', 'aprobar solicitudes', 'agendar servicio terreno', 'gestionar instalaciones', 'autorizar reparacion', 'gestionar tiempos reparacion', 'ver informe dispensadores', 'ver informe industrial', 'manage devoluciones', 'simular carga']);
        // El jefe de bodega AUTORIZA la recepcion de lo que llego por QR (revisa
        // que los datos esten bien) y luego el tecnico repara. Por eso tiene
        // 'confirmar servicio tecnico' pero NO 'manage' (no ingresa/edita).
        Role::firstOrCreate(['name' => 'jefe_bodega', 'guard_name' => 'web'])
            // Bodega tambien simula: es quien carga y quien sabe si el numero cuadra.
            ->givePermissionTo(['view users', 'manage production', 'view servicio tecnico', 'ver todo servicio tecnico', 'confirmar servicio tecnico', 'recibir traslado servicio', 'aprobar solicitudes', 'manage despachos', 'ver informe dispensadores', 'ver informe industrial', 'manage devoluciones', 'simular carga']);
        // El conductor solo carga lotes de ingreso en ruta (permiso acotado): NO
        // edita órdenes ni la etapa de taller.
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web'])
            ->givePermissionTo(['crear lote servicio', 'confirmar entrega']);
        // El tecnico gestiona TODO el taller (M12): ingreso/edicion, etapa de
        // reparacion y tambien confirmar la recepcion (y puede cargar lotes).
        Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web'])
            ->givePermissionTo(['view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'confirmar servicio tecnico', 'crear lote servicio', 'recibir traslado servicio', 'autorizar reparacion', 'ver informe dispensadores']);
        // El tecnico INDUSTRIAL trabaja en terreno (plantas de osmosis,
        // llenadoras, lavadoras en el cliente): gestiona su agenda (agenda,
        // edita y marca lo realizado desde el calendario) e instalaciones. Es un
        // rol aparte del tecnico de taller.
        // El tecnico INDUSTRIAL, por pedido de gerencia, solo VE su agenda (y marca
        // realizado); NO agenda ni edita la agenda (eso lo hacen jefes/vendedores).
        // Mantiene su registro de Instalaciones (su planilla). Si gerencia quiere
        // habilitarle agendar, lo activa en Administracion -> Roles.
        Role::firstOrCreate(['name' => 'tecnico_industrial', 'guard_name' => 'web'])
            ->givePermissionTo(['ver agenda terreno', 'gestionar instalaciones', 'ver informe industrial']);
        Role::firstOrCreate(['name' => 'soplador', 'guard_name' => 'web'])
            ->givePermissionTo('report production');
        // JEFE DE SUCURSAL (2026-07-28). Nace por la regla 9 de Contabilidad: la
        // nota de credito —el unico modo de anular un documento tributario— la
        // pueden emitir el gerente, el jefe de ventas y los JEFES DE SUCURSAL
        // (Luis Figueroa en Coquimbo, Gonzalo Martinez en Abate Molina). Ese rol
        // no existia en DaliGo, asi que se crea ahora y no el dia de la primera
        // emision: el permiso tiene que estar antes que la funcionalidad.
        // Alcance deliberadamente ACOTADO a lo tributario + lo que ya se les
        // reconoce operativamente (ver el taller de su sucursal y aprobar). NO
        // lleva 'emitir documentos tributarios' todavia: emitir es del mostrador,
        // anular es de la jefatura.
        Role::firstOrCreate(['name' => 'jefe_sucursal', 'guard_name' => 'web'])
            ->givePermissionTo([
                'view users', 'manage clientes',
                'view servicio tecnico', 'ver todo servicio tecnico',
                'aprobar solicitudes',
                'ver informe dispensadores', 'ver informe industrial',
                'emitir nota de credito',
                // Despacha las maquinas a reparar de SU sucursal a la casa matriz.
                // Los administrativos de sucursal (1-2 por sucursal, dato del dueño)
                // reciben este mismo permiso desde la UI de Roles cuando se creen
                // sus cuentas — es aditivo y no exige tocar el codigo.
                'despachar traslado servicio',
            ]);
        // El gerente y el jefe de ventas tambien anulan (regla 9). El gerente usa
        // el rol admin, que ya recibe TODOS los permisos mas arriba.
        Role::findByName('jefe_ventas')->givePermissionTo('emitir nota de credito');

        // JEFE DE LOGISTICA (2026-08-04). Nace con el modulo LOGISTICA: la flota
        // la mantienen el gerente (rol admin, que ya tiene todo) y el jefe de
        // logistica (decision del dueño). Mismo criterio que jefe_sucursal el
        // 28-07: el rol se crea con el permiso, no el dia que se cree la cuenta.
        //
        // Alcance ACOTADO a la flota a proposito: hoy logistica es Vehiculos.
        // Cuando el modulo crezca (mantenciones, kilometraje, rutas) los
        // permisos nuevos se le suman aca.
        //
        // COBRANZAS queda PENDIENTE y es deliberado: el dueño lo pidio "en un
        // futuro, todavia no esta creado su perfil". No se inventa un rol vacio;
        // el dia que exista, se le da 'ver vehiculos' desde Administracion →
        // Roles y ve la flota y sus vencimientos SIN tocar codigo (es lo que
        // gana separar 'ver' de 'manage').
        Role::firstOrCreate(['name' => 'jefe_logistica', 'guard_name' => 'web'])
            ->givePermissionTo(['ver vehiculos', 'manage vehiculos', 'simular carga']);
    }
}
