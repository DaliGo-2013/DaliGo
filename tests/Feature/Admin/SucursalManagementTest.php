<?php

namespace Tests\Feature\Admin;

use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SucursalManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_view_sucursales_index(): void
    {
        $this->actingAs($this->admin())->get('/admin/sucursales')->assertOk();
    }

    public function test_admin_can_view_create_form(): void
    {
        $this->actingAs($this->admin())->get('/admin/sucursales/create')
            ->assertOk()
            ->assertSee('Crear sucursal');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)->get('/admin/sucursales')->assertForbidden();
        $this->actingAs($member)->get('/admin/sucursales/create')->assertForbidden();
        $this->actingAs($member)->post('/admin/sucursales', ['nombre' => 'X', 'codigo' => 'X'])->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/sucursales')->assertRedirect('/login');
    }

    public function test_admin_can_create_sucursal(): void
    {
        $this->actingAs($this->admin())->post('/admin/sucursales', [
            'nombre' => 'Mirador',
            'codigo' => 'MIRADOR',
            'ciudad' => 'La Serena',
            'es_central' => '1',
            'activa' => '1',
        ])->assertRedirect(route('admin.sucursales.index'));

        $this->assertDatabaseHas('sucursales', [
            'codigo' => 'MIRADOR',
            'nombre' => 'Mirador',
            'es_central' => true,
            'activa' => true,
        ]);
    }

    public function test_create_requires_nombre_and_codigo(): void
    {
        $this->actingAs($this->admin())->post('/admin/sucursales', [])
            ->assertSessionHasErrors(['nombre', 'codigo']);
    }

    public function test_create_rejects_duplicate_codigo(): void
    {
        Sucursal::create(['nombre' => 'Coquimbo', 'codigo' => 'COQUIMBO']);

        $this->actingAs($this->admin())->post('/admin/sucursales', [
            'nombre' => 'Otra',
            'codigo' => 'COQUIMBO',
        ])->assertSessionHasErrors('codigo');
    }

    public function test_admin_can_update_sucursal(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Buzeta', 'codigo' => 'BUZETA']);

        $this->actingAs($this->admin())
            ->put("/admin/sucursales/{$sucursal->id}", [
                'nombre' => 'Buzeta Norte',
                'codigo' => 'BUZETA',
                'activa' => '1',
            ])
            ->assertRedirect(route('admin.sucursales.index'));

        $this->assertSame('Buzeta Norte', $sucursal->fresh()->nombre);
    }

    public function test_admin_can_delete_unused_sucursal(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Temporal', 'codigo' => 'TEMP']);

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}");

        $this->assertDatabaseMissing('sucursales', ['codigo' => 'TEMP']);
    }

    public function test_cannot_delete_sucursal_with_users(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Abate Molina', 'codigo' => 'ABATE']);
        $user = User::factory()->create(['sucursal_id' => $sucursal->id]);
        $user->assignRole('member');

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}");

        $this->assertDatabaseHas('sucursales', ['codigo' => 'ABATE']);
    }

    public function test_admin_can_assign_sucursal_when_creating_user(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);

        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Con Sucursal',
            'email' => 'con.sucursal@impdali.cl',
            'role' => 'member',
            'sucursal_id' => $sucursal->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'con.sucursal@impdali.cl',
            'sucursal_id' => $sucursal->id,
        ]);
    }

    public function test_admin_can_update_user_sucursal(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Coquimbo', 'codigo' => 'COQUIMBO']);
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}", [
                'role' => 'member',
                'sucursal_id' => $sucursal->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($sucursal->id, $user->fresh()->sucursal_id);
    }

    public function test_seeder_creates_base_sucursales_idempotently(): void
    {
        $this->seed(\Database\Seeders\SucursalSeeder::class);
        $this->seed(\Database\Seeders\SucursalSeeder::class); // re-ejecutar no duplica

        $this->assertSame(4, Sucursal::count());
        $this->assertDatabaseHas('sucursales', ['codigo' => 'MIRADOR', 'es_central' => true]);
    }
}
