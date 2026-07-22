<?php

namespace Tests\Feature\Admin;

use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    /**
     * Usuario con un rol personalizado que solo tiene los permisos indicados.
     */
    private function userWith(array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'custom', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_view_users_index(): void
    {
        $this->actingAs($this->admin())->get('/admin/users')->assertOk();
    }

    public function test_admin_can_view_create_form(): void
    {
        $this->actingAs($this->admin())->get('/admin/users/create')
            ->assertOk()
            ->assertSee('Crear cuenta');
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
    }

    public function test_guest_is_redirected_from_user_management(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_admin_can_create_impdali_account(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Nueva Persona',
            'email' => 'nueva.persona@impdali.cl',
            'role' => 'member',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'nueva.persona@impdali.cl']);

        $user = User::where('email', 'nueva.persona@impdali.cl')->first();
        $this->assertTrue($user->hasRole('member'));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_create_rejects_non_impdali_email(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Externo',
            'email' => 'externo@gmail.com',
            'role' => 'member',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'externo@gmail.com']);
    }

    public function test_create_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@impdali.cl']);

        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Dup',
            'email' => 'dup@impdali.cl',
            'role' => 'member',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_public_register_route_is_removed(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_admin_can_view_edit_form(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($this->admin())->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee('Editar cuenta')
            ->assertSee($user->email, false); // nombre/correo ahora editables (value del input)
    }

    public function test_admin_can_update_user_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}", ['role' => 'admin', 'name' => $user->name, 'email' => $user->email])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertFalse($user->fresh()->hasRole('member'));
    }

    public function test_admin_can_edit_name_and_email(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}", [
                'role' => 'member',
                'name' => 'Carlos Tablante',
                'email' => 'carlos.tablante@impdali.cl',
            ])
            ->assertRedirect(route('admin.users.index'));

        $fresh = $user->fresh();
        $this->assertSame('Carlos Tablante', $fresh->name);
        $this->assertSame('carlos.tablante@impdali.cl', $fresh->email);
    }

    public function test_edit_rejects_non_impdali_email(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}", [
                'role' => 'member', 'name' => $user->name, 'email' => 'externo@gmail.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_update_rejects_unknown_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}", ['role' => 'inexistente'])
            ->assertSessionHasErrors('role');
    }

    public function test_cannot_remove_last_admin_role_via_update(): void
    {
        $admin = $this->admin(); // unico admin

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", ['role' => 'member', 'name' => $admin->name, 'email' => $admin->email]);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_can_change_admin_role_when_another_admin_exists(): void
    {
        $admin1 = $this->admin();
        $admin2 = $this->admin();

        $this->actingAs($admin1)
            ->put("/admin/users/{$admin2->id}", ['role' => 'member', 'name' => $admin2->name, 'email' => $admin2->email])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($admin2->fresh()->hasRole('member'));
        $this->assertFalse($admin2->fresh()->hasRole('admin'));
    }

    public function test_view_permission_allows_index_but_not_create(): void
    {
        $user = $this->userWith(['view users']);

        $this->actingAs($user)->get('/admin/users')->assertOk();
        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    }

    public function test_edit_permission_required_for_edit_and_update(): void
    {
        $viewer = $this->userWith(['view users']);
        $target = User::factory()->create();
        $target->assignRole('member');

        $this->actingAs($viewer)->get("/admin/users/{$target->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->put("/admin/users/{$target->id}", ['role' => 'admin'])->assertForbidden();
    }

    public function test_cannot_delete_last_admin(): void
    {
        $admin = $this->admin(); // unico admin
        $deleter = $this->userWith(['delete users']); // puede borrar pero no es admin

        $this->actingAs($deleter)->delete("/admin/users/{$admin->id}");

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_create_form_only_lists_active_sucursales(): void
    {
        $activa = Sucursal::factory()->create(['activa' => true]);
        $inactiva = Sucursal::factory()->create(['activa' => false]);

        $ids = $this->actingAs($this->admin())
            ->get('/admin/users/create')
            ->assertOk()
            ->viewData('sucursales')
            ->pluck('id');

        $this->assertTrue($ids->contains($activa->id));
        $this->assertFalse($ids->contains($inactiva->id));
    }

    public function test_edit_form_keeps_users_current_sucursal_even_if_inactive(): void
    {
        $inactiva = Sucursal::factory()->create(['activa' => false]);
        $user = User::factory()->create(['sucursal_id' => $inactiva->id]);
        $user->assignRole('member');

        $ids = $this->actingAs($this->admin())
            ->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->viewData('sucursales')
            ->pluck('id');

        $this->assertTrue($ids->contains($inactiva->id));
    }
}
