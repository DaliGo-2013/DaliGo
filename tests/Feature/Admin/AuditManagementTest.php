<?php

namespace Tests\Feature\Admin;

use App\Models\Configuracion;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditManagementTest extends TestCase
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

    private function userWith(array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'custom', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    // --- Acceso ---------------------------------------------------------

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/audits')->assertRedirect('/login');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)->get('/admin/audits')->assertForbidden();
    }

    public function test_admin_can_view_index(): void
    {
        $this->actingAs($this->admin())->get('/admin/audits')->assertOk();
    }

    public function test_view_audit_permission_grants_access(): void
    {
        $this->actingAs($this->userWith(['view audit']))->get('/admin/audits')->assertOk();
        $this->actingAs($this->userWith([]))->get('/admin/audits')->assertForbidden();
    }

    // --- La auditoria realmente registra -------------------------------

    public function test_updating_a_sucursal_is_audited(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Mirador', 'codigo' => 'MIRADOR']);

        $sucursal->update(['nombre' => 'Mirador Norte']);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Sucursal::class,
            'auditable_id' => $sucursal->id,
            'event' => 'updated',
        ]);
    }

    public function test_configuracion_set_is_audited(): void
    {
        $config = Configuracion::create([
            'clave' => 'ajuste_x',
            'valor' => '10',
            'tipo' => Configuracion::TIPO_INTEGER,
            'grupo' => 'pruebas',
        ]);

        Configuracion::set('ajuste_x', 20);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Configuracion::class,
            'auditable_id' => $config->id,
            'event' => 'updated',
        ]);
    }

    public function test_user_creation_audit_excludes_secrets(): void
    {
        $user = User::factory()->create();

        $audit = Audit::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('password', $audit->new_values);
        $this->assertArrayNotHasKey('remember_token', $audit->new_values);
        $this->assertArrayHasKey('email', $audit->new_values);
    }

    // --- Audit manual de cambio de rol ---------------------------------

    public function test_role_change_creates_custom_audit(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->assignRole('member');

        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}", ['role' => 'jefe_ventas', 'name' => $target->name, 'email' => $target->email])
            ->assertRedirect(route('admin.users.index'));

        $audit = Audit::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('event', 'roleChanged')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('member', $audit->old_values['roles']);
        $this->assertSame('jefe_ventas', $audit->new_values['roles']);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertNotNull($audit->ip_address);
    }

    public function test_no_audit_when_role_unchanged(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->assignRole('member');

        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}", ['role' => 'member', 'name' => $target->name, 'email' => $target->email])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('audits', [
            'auditable_id' => $target->id,
            'event' => 'roleChanged',
        ]);
    }

    // --- Filtros --------------------------------------------------------

    public function test_filter_by_model(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Coquimbo', 'codigo' => 'COQUIMBO']);
        $sucursal->update(['nombre' => 'Coquimbo Centro']);

        $response = $this->actingAs($this->admin())
            ->get('/admin/audits?auditable_type='.urlencode(Sucursal::class))
            ->assertOk();

        $audits = $response->viewData('audits');
        $this->assertGreaterThan(0, $audits->count());
        foreach ($audits as $audit) {
            $this->assertSame(Sucursal::class, $audit->auditable_type);
        }
    }

    public function test_filter_accepts_producto_model(): void
    {
        // Producto es Auditable y debe ser filtrable/etiquetable en /admin/audits.
        $this->actingAs($this->admin())
            ->get('/admin/audits?auditable_type='.urlencode(\App\Models\Producto::class))
            ->assertOk()
            ->assertSee('Producto');
    }

    public function test_filter_by_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->assignRole('member');

        $this->actingAs($admin)->put("/admin/users/{$target->id}", ['role' => 'jefe_ventas', 'name' => $target->name, 'email' => $target->email]);

        $response = $this->actingAs($admin)
            ->get('/admin/audits?user_id='.$admin->id)
            ->assertOk();

        $audits = $response->viewData('audits');
        $this->assertGreaterThan(0, $audits->count());
        foreach ($audits as $audit) {
            $this->assertSame($admin->id, $audit->user_id);
        }
    }
}
