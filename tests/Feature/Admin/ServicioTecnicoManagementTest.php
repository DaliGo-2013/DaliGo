<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServicioTecnicoManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function userWith(array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'custom', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return tap(User::factory()->create())->assignRole($role);
    }

    /**
     * Payload minimo valido para registrar un ingreso.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'fecha_ingreso' => now()->toDateString(),
            'tipo_equipo' => 'maquina',
            'estado' => 'recibido',
        ], $overrides);
    }

    // --- Acceso ---

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/servicio-tecnico')->assertRedirect('/login');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)->get('/admin/servicio-tecnico')->assertForbidden();
        $this->actingAs($member)->post('/admin/servicio-tecnico', $this->payload())->assertForbidden();
        $this->actingAs($member)->get('/admin/servicio-tecnico/buscar-cliente?q=test')->assertForbidden();
    }

    public function test_permission_grants_access(): void
    {
        $this->actingAs($this->userWith(['manage servicio tecnico']))
            ->get('/admin/servicio-tecnico')
            ->assertOk();
    }

    public function test_tecnico_role_can_manage(): void
    {
        // El seeder le da 'manage servicio tecnico' al rol tecnico.
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');

        $this->actingAs($tecnico)->get('/admin/servicio-tecnico')->assertOk();
        $this->actingAs($tecnico)->post('/admin/servicio-tecnico', $this->payload())
            ->assertRedirect(route('admin.servicio-tecnico.index'));
    }

    // --- CRUD ---

    public function test_admin_can_register_orden(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs($this->admin())->post('/admin/servicio-tecnico', $this->payload([
            'cliente_id' => $cliente->id,
            'tipo_equipo' => 'lavadora',
            'marca' => 'Samsung',
            'modelo' => 'WX-100',
            'numero_serie' => 'SN-555',
            'falla_reportada' => 'No enciende',
        ]))->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'cliente_id' => $cliente->id,
            'tipo_equipo' => 'lavadora',
            'marca' => 'Samsung',
            'numero_serie' => 'SN-555',
            'estado' => 'recibido',
        ]);
    }

    public function test_store_requires_fecha_tipo_y_estado(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', ['fecha_ingreso' => '', 'tipo_equipo' => '', 'estado' => ''])
            ->assertSessionHasErrors(['fecha_ingreso', 'tipo_equipo', 'estado']);
    }

    public function test_invalid_tipo_and_estado_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['tipo_equipo' => 'auto', 'estado' => 'volando']))
            ->assertSessionHasErrors(['tipo_equipo', 'estado']);
    }

    public function test_unknown_cliente_and_tecnico_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['cliente_id' => 9999, 'tecnico_id' => 8888]))
            ->assertSessionHasErrors(['cliente_id', 'tecnico_id']);
    }

    public function test_cliente_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['cliente_id' => '']))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', ['cliente_id' => null, 'tipo_equipo' => 'maquina']);
    }

    public function test_admin_can_update_orden(): void
    {
        $orden = OrdenServicio::factory()->create(['estado' => 'recibido', 'marca' => 'Vieja']);

        $this->actingAs($this->admin())
            ->put("/admin/servicio-tecnico/{$orden->id}", $this->payload([
                'estado' => 'reparado',
                'marca' => 'Nueva',
            ]))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $fresh = $orden->fresh();
        $this->assertSame('reparado', $fresh->estado);
        $this->assertSame('Nueva', $fresh->marca);
    }

    public function test_admin_can_delete_orden(): void
    {
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->admin())->delete("/admin/servicio-tecnico/{$orden->id}");

        $this->assertDatabaseMissing('ordenes_servicio', ['id' => $orden->id]);
    }

    // --- Filtros ---

    public function test_index_filters_by_estado_and_tipo(): void
    {
        $alfa = Cliente::factory()->create(['razon_social' => 'Alfa SpA']);
        $beta = Cliente::factory()->create(['razon_social' => 'Beta Ltda']);
        OrdenServicio::factory()->create(['cliente_id' => $alfa->id, 'estado' => 'recibido', 'tipo_equipo' => 'maquina']);
        OrdenServicio::factory()->create(['cliente_id' => $beta->id, 'estado' => 'entregado', 'tipo_equipo' => 'lavadora']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?estado=recibido')
            ->assertOk()->assertSee('Alfa SpA')->assertDontSee('Beta Ltda');

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?tipo_equipo=lavadora')
            ->assertOk()->assertSee('Beta Ltda')->assertDontSee('Alfa SpA');
    }

    public function test_index_search_matches_cliente_and_serie(): void
    {
        $gamma = Cliente::factory()->create(['razon_social' => 'Gamma Importadora']);
        OrdenServicio::factory()->create(['cliente_id' => $gamma->id, 'numero_serie' => 'SN-XYZ-9']);
        $delta = Cliente::factory()->create(['razon_social' => 'Delta Comercial']);
        OrdenServicio::factory()->create(['cliente_id' => $delta->id, 'numero_serie' => 'SN-AAA-1']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?q=Gamma')
            ->assertOk()->assertSee('Gamma Importadora')->assertDontSee('Delta Comercial');

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?q=SN-XYZ-9')
            ->assertOk()->assertSee('Gamma Importadora')->assertDontSee('Delta Comercial');
    }

    // --- buscarCliente (autocompletado JSON) ---

    public function test_buscar_cliente_matches_normalized_rut(): void
    {
        $cliente = Cliente::factory()->create(['rut' => '12345678-5', 'razon_social' => 'Aguas del Sur']);

        // RUT escrito con puntos: matchea contra el rut normalizado.
        $this->actingAs($this->admin())
            ->getJson('/admin/servicio-tecnico/buscar-cliente?q=12.345.678')
            ->assertOk()
            ->assertJsonFragment(['id' => $cliente->id]);

        $this->actingAs($this->admin())
            ->getJson('/admin/servicio-tecnico/buscar-cliente?q=Aguas')
            ->assertOk()
            ->assertJsonFragment(['razon_social' => 'Aguas del Sur']);
    }

    public function test_buscar_cliente_needs_two_chars(): void
    {
        Cliente::factory()->create(['razon_social' => 'Uno']);

        $this->actingAs($this->admin())
            ->getJson('/admin/servicio-tecnico/buscar-cliente?q=1')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_buscar_cliente_limits_results(): void
    {
        Cliente::factory()->count(20)->create(['razon_social' => fn () => 'Cliente '.fake()->unique()->numberBetween(1, 9999).' Norte']);

        $this->actingAs($this->admin())
            ->getJson('/admin/servicio-tecnico/buscar-cliente?q=Norte')
            ->assertOk()
            ->assertJsonCount(15);
    }
}
