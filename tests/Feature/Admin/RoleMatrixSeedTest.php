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
                'report production', 'manage production',
            ],
            'member' => [],
            'Soplador' => ['report production'],
            'Jefatura' => ['manage production'],
            'vendedor' => [],
            'jefe_ventas' => ['view users'],
            'jefe_bodega' => ['view users'],
            'conductor' => [],
            'tecnico' => [],
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

    public function test_seeder_deja_exactamente_nueve_roles(): void
    {
        $this->assertSame(9, Role::count());
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
        $this->assertSame(9, Role::count());
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
