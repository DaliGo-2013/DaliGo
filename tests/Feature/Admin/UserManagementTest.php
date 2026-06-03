<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
