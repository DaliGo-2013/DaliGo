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
                'view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'editar recepcion servicio tecnico', 'confirmar servicio tecnico', 'autorizar reparacion', 'aplicar descuento servicio tecnico', 'crear lote servicio',
                'agendar servicio terreno', 'ver agenda terreno', 'gestionar instalaciones', 'gestionar tiempos reparacion',
                'ver informe dispensadores', 'ver informe industrial',
                'view notificaciones', 'gestionar notificaciones', 'aprobar solicitudes', 'view aprobaciones',
                'manage despachos', 'confirmar entrega',
                'emitir documentos tributarios', 'emitir nota de credito',
                'ver plan proyecto', 'gestionar plan proyecto',
            ],
            'member' => [],
            'vendedor' => ['manage clientes', 'view servicio tecnico', 'agendar servicio terreno', 'autorizar reparacion', 'ver informe dispensadores', 'ver informe industrial'],
            'jefe_ventas' => ['view users', 'manage clientes', 'view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'editar recepcion servicio tecnico', 'confirmar servicio tecnico', 'aplicar descuento servicio tecnico', 'aprobar solicitudes', 'agendar servicio terreno', 'gestionar instalaciones', 'autorizar reparacion', 'gestionar tiempos reparacion', 'ver informe dispensadores', 'ver informe industrial', 'emitir nota de credito'],
            // Jefe de sucursal (2026-07-28): nace por la regla 9 de Contabilidad
            // (quiénes pueden anular con nota de crédito). Ver el seeder.
            'jefe_sucursal' => ['view users', 'manage clientes', 'view servicio tecnico', 'ver todo servicio tecnico', 'aprobar solicitudes', 'ver informe dispensadores', 'ver informe industrial', 'emitir nota de credito'],
            'jefe_bodega' => ['view users', 'manage production', 'view servicio tecnico', 'ver todo servicio tecnico', 'confirmar servicio tecnico', 'aprobar solicitudes', 'manage despachos', 'ver informe dispensadores', 'ver informe industrial'],
            'conductor' => ['crear lote servicio', 'confirmar entrega'],
            'tecnico' => ['view servicio tecnico', 'ver todo servicio tecnico', 'manage servicio tecnico', 'confirmar servicio tecnico', 'crear lote servicio', 'autorizar reparacion', 'ver informe dispensadores'],
            'tecnico_industrial' => ['ver agenda terreno', 'gestionar instalaciones', 'ver informe industrial'],
            'soplador' => ['report production'],
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

    public function test_seeder_deja_exactamente_diez_roles(): void
    {
        // 8 del negocio + tecnico_industrial (agenda de terreno, 2026-07-14)
        // + jefe_sucursal (notas de crédito, 2026-07-28).
        $this->assertSame(10, Role::count());
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
        $this->assertSame(10, Role::count());
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
