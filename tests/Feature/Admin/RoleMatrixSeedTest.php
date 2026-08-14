<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleMatrixSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Matriz de partida esperada: rol => permisos exactos.
     */
    private function matrix(): array
    {
        return [
            'admin' => [
                'view users', 'create users', 'edit users', 'delete users',
                'manage roles', 'manage sucursales', 'manage settings', 'view audit',
                'manage productos', 'manage clientes', 'report production', 'manage production',
                'view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'editar recepcion servicio tecnico', 'confirmar servicio tecnico', 'autorizar reparacion', 'aplicar descuento servicio tecnico', 'crear lote servicio', 'despachar traslado servicio', 'recibir traslado servicio',
                'agendar servicio terreno', 'ver agenda terreno', 'gestionar cierres agenda', 'ver servicios terreno', 'gestionar instalaciones', 'gestionar tiempos reparacion',
                'ver informe dispensadores', 'ver informe industrial',
                'view notificaciones', 'gestionar notificaciones', 'aprobar solicitudes', 'view aprobaciones',
                'manage despachos', 'confirmar entrega',
                'emitir documentos tributarios', 'emitir nota de credito',
                'ver plan proyecto', 'gestionar plan proyecto',
                'ver vehiculos', 'manage vehiculos', 'simular carga',
                'view devoluciones', 'manage devoluciones',
                'manage hojas ruta', 'autorizar pagos ruta', 'autorizar ruta', 'autorizar carga',
            ],
            'member' => [],
            'vendedor' => ['manage clientes', 'view servicio tecnico', 'agendar servicio terreno', 'autorizar reparacion', 'ver informe dispensadores', 'ver informe industrial', 'simular carga'],
            // UNIÓN del merge 04-08: devoluciones + simulador (M13/Marcos) y
            // la llave 1 de la hoja de ruta (P-DSP-08).
            'jefe_ventas' => ['view users', 'manage clientes', 'view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'editar recepcion servicio tecnico', 'confirmar servicio tecnico', 'recibir traslado servicio', 'aplicar descuento servicio tecnico', 'aprobar solicitudes', 'agendar servicio terreno', 'gestionar cierres agenda', 'gestionar instalaciones', 'autorizar reparacion', 'gestionar tiempos reparacion', 'ver informe dispensadores', 'ver informe industrial', 'emitir nota de credito', 'manage devoluciones', 'simular carga', 'autorizar pagos ruta'],
            // Jefe de sucursal (2026-07-28): nace por la regla 9 de Contabilidad
            // (quiénes pueden anular con nota de crédito). Ver el seeder.
            // El jefe de sucursal DESPACHA (no recibe): la máquina sale de su
            // sucursal y llega al taller, que es quien confirma. Que una misma
            // persona pudiera cerrar las dos puntas anularía la cadena de custodia.
            'jefe_sucursal' => ['view users', 'manage clientes', 'view servicio tecnico', 'ver todo servicio tecnico', 'aprobar solicitudes', 'ver informe dispensadores', 'ver informe industrial', 'emitir nota de credito', 'despachar traslado servicio'],
            'jefe_bodega' => ['view users', 'manage production', 'view servicio tecnico', 'ver todo servicio tecnico', 'confirmar servicio tecnico', 'aprobar solicitudes', 'manage despachos', 'ver informe dispensadores', 'ver informe industrial', 'recibir traslado servicio', 'manage devoluciones', 'simular carga', 'autorizar carga'],
            // + 'ver vehiculos' (11-08-2026): el respaldo digital de los documentos
            // del vehículo existe PARA el conductor (mostrarlos en un control de
            // ruta). Solo consulta — subir sigue siendo de 'manage vehiculos'.
            'conductor' => ['crear lote servicio', 'confirmar entrega', 'ver vehiculos'],
            // Sin 'autorizar reparacion' (dueño 07-08): el taller no coordina plata
            // — manda la cotización, repara si el cliente acepta y avisa que el
            // equipo está listo; el cobro es en sala de ventas al retiro.
            'tecnico' => ['view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'confirmar servicio tecnico', 'crear lote servicio', 'recibir traslado servicio', 'ver informe dispensadores'],
            'tecnico_industrial' => ['ver agenda terreno', 'ver servicios terreno', 'gestionar instalaciones', 'ver informe industrial'],
            'soplador' => ['report production'],
            // Jefe de logística (2026-08-04): nace con el módulo LOGÍSTICA.
            // Alcance ACOTADO a la flota a propósito — hoy logística es
            // Vehículos. Cobranzas queda pendiente (no existe su perfil):
            // el día que exista recibe 'ver vehiculos' desde la UI de Roles,
            // sin tocar código.
            'jefe_logistica' => ['ver vehiculos', 'manage vehiculos', 'simular carga', 'manage hojas ruta'],
            // Jefe de despacho (2026-08-04, P-DSP-08): la llave 2 de la
            // cadena de la hoja de ruta (R11). Arma hojas y autoriza la ruta;
            // las otras dos llaves son de ventas y bodega a propósito — que
            // una persona pudiera dar dos llaves anularía el control cruzado.
            'jefe_despacho' => ['manage hojas ruta', 'autorizar ruta'],
        ];
    }

    public function test_seeder_crea_todos_los_roles_del_negocio_con_su_matriz(): void
    {
        foreach ($this->matrix() as $name => $expected) {
            $role = Role::findByName($name);

            $this->assertEqualsCanonicalizing(
                $expected,
                $role->permissions->pluck('name')->all(),
                "El rol '{$name}' no tiene los permisos esperados.",
            );
        }
    }

    public function test_seeder_deja_exactamente_doce_roles(): void
    {
        // 8 del negocio + tecnico_industrial (agenda de terreno, 2026-07-14)
        // + jefe_sucursal (notas de crédito, 2026-07-28)
        // + jefe_logistica (flota de vehículos, 2026-08-04)
        // + jefe_despacho (hoja de ruta digital, 2026-08-04 · P-DSP-08).
        $this->assertSame(12, Role::count());
    }

    public function test_solo_la_jefatura_puede_anular_con_nota_de_credito(): void
    {
        // Regla 9 de Contabilidad (28-jul-2026): anular es del gerente (admin),
        // el jefe de ventas y los jefes de sucursal. Nadie más — ni el técnico,
        // ni el vendedor, ni quien emitió el documento.
        foreach (['admin', 'jefe_ventas', 'jefe_sucursal'] as $rol) {
            $this->assertTrue(
                Role::findByName($rol)->hasPermissionTo('emitir nota de credito'),
                "El rol '{$rol}' debería poder anular con nota de crédito.",
            );
        }

        foreach (['vendedor', 'tecnico', 'tecnico_industrial', 'jefe_bodega', 'conductor', 'soplador', 'member'] as $rol) {
            $this->assertFalse(
                Role::findByName($rol)->hasPermissionTo('emitir nota de credito'),
                "El rol '{$rol}' NO debería poder anular un documento tributario.",
            );
        }
    }

    public function test_reseed_es_idempotente_y_no_borra_permisos_de_la_ui(): void
    {
        // Simula una personalizacion hecha desde la UI: jefe_ventas gana un permiso extra.
        Role::findByName('jefe_ventas')->givePermissionTo('manage sucursales');

        // Un nuevo deploy vuelve a correr el seeder.
        $this->seed(RolesAndPermissionsSeeder::class);

        $role = Role::findByName('jefe_ventas');

        // El permiso agregado por la UI sobrevive...
        $this->assertTrue($role->hasPermissionTo('manage sucursales'));
        // ...y el piso de la matriz sigue intacto.
        $this->assertTrue($role->hasPermissionTo('view users'));

        // No se duplicaron roles.
        $this->assertSame(12, Role::count());
    }

    public function test_index_muestra_nombres_y_permisos_legibles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/admin/roles')
            ->assertOk()
            ->assertSee('Jefe Ventas')        // Str::headline('jefe_ventas')
            ->assertSee('Jefe Bodega')        // Str::headline('jefe_bodega')
            ->assertSee('Reportar producción'); // label centralizado (config/permissions.php)
    }
}
