<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
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

    public function test_admin_can_view_roles_index(): void
    {
        $this->actingAs($this->admin())->get('/admin/roles')->assertOk();
    }

    public function test_admin_can_view_create_form(): void
    {
        $this->actingAs($this->admin())->get('/admin/roles/create')
            ->assertOk()
            ->assertSee('Crear rol');
    }

    public function test_member_without_manage_roles_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)->get('/admin/roles')->assertForbidden();
        $this->actingAs($member)->get('/admin/roles/create')->assertForbidden();
        $this->actingAs($member)->post('/admin/roles', ['name' => 'x'])->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/roles')->assertRedirect('/login');
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $this->actingAs($this->admin())->post('/admin/roles', [
            'name' => 'supervisor',
            'permissions' => ['view users', 'edit users'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::findByName('supervisor');
        $this->assertTrue($role->hasPermissionTo('view users'));
        $this->assertTrue($role->hasPermissionTo('edit users'));
        $this->assertFalse($role->hasPermissionTo('delete users'));
    }

    public function test_role_name_is_normalized_to_lowercase(): void
    {
        // El unique de MySQL 5.7 es case-insensitive; normalizamos al crear para
        // que local (SQLite) y prod se comporten igual y se mantenga la
        // convencion ASCII-minuscula de los roles del negocio.
        $this->actingAs($this->admin())->post('/admin/roles', [
            'name' => '  SUPERVISOR Regional ',
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['name' => 'supervisor regional']);
        $this->assertDatabaseMissing('roles', ['name' => 'SUPERVISOR Regional']);
    }

    public function test_create_rejects_duplicate_role_name(): void
    {
        $this->actingAs($this->admin())->post('/admin/roles', ['name' => 'admin'])
            ->assertSessionHasErrors('name');
    }

    public function test_create_rejects_invalid_role_name(): void
    {
        $this->actingAs($this->admin())->post('/admin/roles', ['name' => 'rol@invalido!'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $role = Role::create(['name' => 'supervisor', 'guard_name' => 'web']);
        $role->givePermissionTo('view users');

        $this->actingAs($this->admin())
            ->put("/admin/roles/{$role->id}", ['name' => 'supervisor', 'permissions' => ['edit users']])
            ->assertRedirect(route('admin.roles.index'));

        $role = $role->fresh();
        $this->assertTrue($role->hasPermissionTo('edit users'));
        $this->assertFalse($role->hasPermissionTo('view users'));
    }

    public function test_cannot_rename_base_role(): void
    {
        $adminRole = Role::findByName('admin');

        $this->actingAs($this->admin())->put("/admin/roles/{$adminRole->id}", [
            'name' => 'superadmin',
            'permissions' => ['manage roles'],
        ]);

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseMissing('roles', ['name' => 'superadmin']);
    }

    public function test_admin_role_always_retains_manage_roles(): void
    {
        $adminRole = Role::findByName('admin');

        // Intenta dejar el rol admin solo con 'view users' (sin manage roles).
        $this->actingAs($this->admin())
            ->put("/admin/roles/{$adminRole->id}", ['permissions' => ['view users']]);

        $this->assertTrue($adminRole->fresh()->hasPermissionTo('manage roles'));
    }

    public function test_cannot_delete_base_role(): void
    {
        $memberRole = Role::findByName('member');

        $this->actingAs($this->admin())->delete("/admin/roles/{$memberRole->id}");

        $this->assertDatabaseHas('roles', ['name' => 'member']);
    }

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $role = Role::create(['name' => 'supervisor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($this->admin())->delete("/admin/roles/{$role->id}");

        $this->assertDatabaseHas('roles', ['name' => 'supervisor']);
    }

    public function test_admin_can_delete_unused_custom_role(): void
    {
        $role = Role::create(['name' => 'temporal', 'guard_name' => 'web']);

        $this->actingAs($this->admin())->delete("/admin/roles/{$role->id}");

        $this->assertDatabaseMissing('roles', ['name' => 'temporal']);
    }
}
