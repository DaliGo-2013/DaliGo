<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClienteManagementTest extends TestCase
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

    private function vendedor(): User
    {
        return tap(User::factory()->create())->assignRole('vendedor');
    }

    /**
     * Payload minimo valido para crear un cliente.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'razon_social' => 'Comercial Prueba SpA',
            'rut' => '12.345.678-5',
        ], $overrides);
    }

    // --- Acceso ---

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/clientes')->assertRedirect('/login');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)->get('/admin/clientes')->assertForbidden();
        $this->actingAs($member)->post('/admin/clientes', $this->payload())->assertForbidden();
    }

    public function test_manage_clientes_permission_grants_access(): void
    {
        $this->actingAs($this->userWith(['manage clientes']))
            ->get('/admin/clientes')
            ->assertOk();
    }

    public function test_vendedor_role_can_manage_clientes(): void
    {
        // Regla #2 del negocio: la gestion es por vendedor -> el seeder le da
        // 'manage clientes' como piso.
        $vendedor = $this->vendedor();

        $this->actingAs($vendedor)->get('/admin/clientes')->assertOk();
        $this->actingAs($vendedor)->post('/admin/clientes', $this->payload())
            ->assertRedirect(route('admin.clientes.index'));
    }

    // --- CRUD ---

    public function test_admin_can_create_cliente_and_rut_is_normalized(): void
    {
        $this->actingAs($this->admin())->post('/admin/clientes', $this->payload([
            'rut' => '12.345.678-5', // con puntos: se guarda normalizado
            'giro' => 'Comercio',
            'email' => 'contacto@prueba.cl',
            'segmento' => 'mayorista',
            'es_empresa' => '1',
            'activo' => '1',
        ]))->assertRedirect(route('admin.clientes.index'));

        $this->assertDatabaseHas('clientes', [
            'rut' => '12345678-5',
            'razon_social' => 'Comercial Prueba SpA',
            'segmento' => 'mayorista',
            'es_empresa' => true,
        ]);
    }

    public function test_store_requires_razon_social(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/clientes', ['razon_social' => ''])
            ->assertSessionHasErrors('razon_social');
    }

    public function test_rut_with_wrong_dv_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/clientes', $this->payload(['rut' => '12.345.678-9'])) // DV correcto: 5
            ->assertSessionHasErrors('rut');

        $this->assertDatabaseMissing('clientes', ['razon_social' => 'Comercial Prueba SpA']);
    }

    public function test_garbage_rut_is_rejected_not_silently_nulled(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/clientes', $this->payload(['rut' => 'abc']))
            ->assertSessionHasErrors('rut');
    }

    public function test_rut_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/clientes', $this->payload(['rut' => '']))
            ->assertRedirect(route('admin.clientes.index'));

        $this->assertDatabaseHas('clientes', ['razon_social' => 'Comercial Prueba SpA', 'rut' => null]);
    }

    public function test_duplicate_rut_in_different_format_is_rejected(): void
    {
        Cliente::factory()->create(['rut' => '12345678-5']);

        $this->actingAs($this->admin())
            ->post('/admin/clientes', $this->payload(['rut' => '12.345.678-5']))
            ->assertSessionHasErrors('rut');
    }

    public function test_invalid_segmento_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/clientes', $this->payload(['segmento' => 'vip']))
            ->assertSessionHasErrors('segmento');
    }

    public function test_unknown_vendedor_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/clientes', $this->payload(['vendedor_id' => 9999]))
            ->assertSessionHasErrors('vendedor_id');
    }

    public function test_admin_can_assign_vendedor(): void
    {
        $vendedor = $this->vendedor();

        $this->actingAs($this->admin())
            ->post('/admin/clientes', $this->payload(['vendedor_id' => $vendedor->id]))
            ->assertRedirect(route('admin.clientes.index'));

        $this->assertDatabaseHas('clientes', ['rut' => '12345678-5', 'vendedor_id' => $vendedor->id]);
    }

    public function test_admin_can_set_vendedor_libre_por_nombre(): void
    {
        // El vendedor asignado es texto libre (puede no ser usuario del sistema).
        $this->actingAs($this->admin())
            ->post('/admin/clientes', $this->payload(['vendedor_nombre' => 'Carlos Vega']))
            ->assertRedirect(route('admin.clientes.index'));

        $this->assertDatabaseHas('clientes', ['rut' => '12345678-5', 'vendedor_nombre' => 'Carlos Vega']);
    }

    public function test_admin_can_update_cliente(): void
    {
        $cliente = Cliente::factory()->create(['segmento' => null]);

        $this->actingAs($this->admin())
            ->put("/admin/clientes/{$cliente->id}", [
                'rut' => $cliente->rut,
                'razon_social' => 'Nuevo Nombre Ltda',
                'segmento' => 'retail',
                'activo' => '1',
            ])
            ->assertRedirect(route('admin.clientes.index'));

        $fresh = $cliente->fresh();
        $this->assertSame('Nuevo Nombre Ltda', $fresh->razon_social);
        $this->assertSame('retail', $fresh->segmento);
    }

    public function test_update_keeps_own_rut_without_unique_collision(): void
    {
        $cliente = Cliente::factory()->create(['rut' => '12345678-5']);

        $this->actingAs($this->admin())
            ->put("/admin/clientes/{$cliente->id}", [
                'rut' => '12.345.678-5', // el mismo, en otro formato
                'razon_social' => $cliente->razon_social,
                'activo' => '1',
            ])
            ->assertSessionDoesntHaveErrors('rut');
    }

    public function test_admin_can_delete_cliente(): void
    {
        $cliente = Cliente::factory()->create();

        $this->actingAs($this->admin())->delete("/admin/clientes/{$cliente->id}");

        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    }

    public function test_cannot_delete_cliente_linked_to_bsale(): void
    {
        // Anti-zombie: el proximo sync lo recrearia perdiendo segmento/notas/vendedor.
        $cliente = Cliente::factory()->create(['bsale_client_id' => 777]);

        $this->actingAs($this->admin())->delete("/admin/clientes/{$cliente->id}");

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id]);
    }

    // --- Filtros ---

    public function test_index_filters_by_razon_social_and_rut_with_dots(): void
    {
        Cliente::factory()->create(['razon_social' => 'Aguas del Sur', 'rut' => '11111111-1']);
        Cliente::factory()->create(['razon_social' => 'Botellones Norte', 'rut' => '12345678-5']);

        $this->actingAs($this->admin())->get('/admin/clientes?q=Aguas')
            ->assertOk()
            ->assertSee('Aguas del Sur')
            ->assertDontSee('Botellones Norte');

        // Busqueda por RUT escrito con puntos: matchea contra el rut normalizado.
        $this->actingAs($this->admin())->get('/admin/clientes?q=12.345.678')
            ->assertOk()
            ->assertSee('Botellones Norte')
            ->assertDontSee('Aguas del Sur');
    }

    public function test_index_filters_by_segmento_and_vendedor(): void
    {
        $vendedor = $this->vendedor();
        Cliente::factory()->create(['razon_social' => 'Mayorista Uno', 'segmento' => 'mayorista', 'vendedor_id' => $vendedor->id]);
        Cliente::factory()->create(['razon_social' => 'Retail Dos', 'segmento' => 'retail']);

        $this->actingAs($this->admin())->get('/admin/clientes?segmento=mayorista')
            ->assertOk()
            ->assertSee('Mayorista Uno')
            ->assertDontSee('Retail Dos');

        $this->actingAs($this->admin())->get("/admin/clientes?vendedor_id={$vendedor->id}")
            ->assertOk()
            ->assertSee('Mayorista Uno')
            ->assertDontSee('Retail Dos');
    }

    // --- Normalizacion / DV ---

    public function test_normalizar_rut_handles_formats(): void
    {
        $this->assertSame('12345678-5', Cliente::normalizarRut('12.345.678-5'));
        $this->assertSame('12345678-5', Cliente::normalizarRut('123456785'));
        $this->assertSame('1111111-K', Cliente::normalizarRut('1.111.111-k'));
        $this->assertNull(Cliente::normalizarRut(''));
        $this->assertNull(Cliente::normalizarRut(null));
        $this->assertNull(Cliente::normalizarRut('-'));
    }
}
