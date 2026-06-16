<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Producto;
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
            'cliente_nombre' => 'Juan Pérez',
            'cliente_rut' => '12.345.678-5',
            'fecha_ingreso' => now()->toDateString(),
            'tipo_equipo' => 'maquina',
            'modelo' => 'Modelo X',
            'falla_reportada' => 'No enciende',
            'estado' => 'recibido',
            'facturacion' => 'garantia',
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
        $this->actingAs($member)->get('/admin/servicio-tecnico/buscar-producto?q=test')->assertForbidden();
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
        $producto = Producto::factory()->create();

        $this->actingAs($this->admin())->post('/admin/servicio-tecnico', $this->payload([
            'cliente_id' => $cliente->id,
            'producto_id' => $producto->id,
            'tipo_equipo' => 'lavadora',
            'modelo' => 'WX-100',
            'numero_serie' => 'SN-555',
            'facturacion' => 'garantia',
            'falla_reportada' => 'No enciende',
        ]))->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'cliente_id' => $cliente->id,
            'cliente_nombre' => 'Juan Pérez',
            'cliente_rut' => '12345678-5',   // normalizado
            'producto_id' => $producto->id,
            'tipo_equipo' => 'lavadora',
            'numero_serie' => 'SN-555',
            'facturacion' => 'garantia',
            'estado' => 'recibido',
        ]);
    }

    public function test_store_requires_obligatorios(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', [
                'cliente_nombre' => '', 'cliente_rut' => '', 'fecha_ingreso' => '',
                'tipo_equipo' => '', 'modelo' => '', 'falla_reportada' => '',
                'estado' => '', 'facturacion' => '',
            ])
            ->assertSessionHasErrors([
                'cliente_nombre', 'cliente_rut', 'fecha_ingreso',
                'tipo_equipo', 'modelo', 'falla_reportada', 'estado', 'facturacion',
            ]);
    }

    public function test_rut_invalido_es_rechazado_y_valido_se_normaliza(): void
    {
        // DV correcto de 12345678 es 5; -9 debe rechazarse.
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['cliente_rut' => '12.345.678-9']))
            ->assertSessionHasErrors('cliente_rut');

        // Valido con puntos: se guarda normalizado.
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['cliente_rut' => '12.345.678-5']))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', ['cliente_rut' => '12345678-5']);
    }

    public function test_herramienta_es_un_tipo_valido(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['tipo_equipo' => 'herramienta']))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', ['tipo_equipo' => 'herramienta']);
    }

    public function test_invalid_tipo_estado_y_facturacion_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['tipo_equipo' => 'auto', 'estado' => 'volando', 'facturacion' => 'tarjeta']))
            ->assertSessionHasErrors(['tipo_equipo', 'estado', 'facturacion']);
    }

    public function test_unknown_cliente_and_producto_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['cliente_id' => 9999, 'producto_id' => 8888]))
            ->assertSessionHasErrors(['cliente_id', 'producto_id']);
    }

    public function test_cliente_link_is_optional_pero_nombre_y_rut_se_guardan(): void
    {
        // El enlace al catalogo (cliente_id) es opcional: un cliente que no existe
        // se ingresa a mano y queda archivado por nombre + rut.
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['cliente_id' => '', 'cliente_nombre' => 'Pedro Soto']))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'cliente_id' => null,
            'cliente_nombre' => 'Pedro Soto',
            'cliente_rut' => '12345678-5',
        ]);
    }

    public function test_admin_can_update_orden(): void
    {
        $orden = OrdenServicio::factory()->create(['estado' => 'recibido', 'facturacion' => 'garantia']);

        $this->actingAs($this->admin())
            ->put("/admin/servicio-tecnico/{$orden->id}", $this->payload([
                'estado' => 'reparado',
                'facturacion' => 'boleta',
            ]))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $fresh = $orden->fresh();
        $this->assertSame('reparado', $fresh->estado);
        $this->assertSame('boleta', $fresh->facturacion);
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
        OrdenServicio::factory()->create(['cliente_nombre' => 'Alfa SpA', 'estado' => 'recibido', 'tipo_equipo' => 'maquina']);
        OrdenServicio::factory()->create(['cliente_nombre' => 'Beta Ltda', 'estado' => 'entregado', 'tipo_equipo' => 'lavadora']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?estado=recibido')
            ->assertOk()->assertSee('Alfa SpA')->assertDontSee('Beta Ltda');

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?tipo_equipo=lavadora')
            ->assertOk()->assertSee('Beta Ltda')->assertDontSee('Alfa SpA');
    }

    public function test_index_search_matches_cliente_and_serie(): void
    {
        OrdenServicio::factory()->create(['cliente_nombre' => 'Gamma Importadora', 'numero_serie' => 'SN-XYZ-9']);
        OrdenServicio::factory()->create(['cliente_nombre' => 'Delta Comercial', 'numero_serie' => 'SN-AAA-1']);

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

    public function test_buscar_producto_matches_sku_and_nombre(): void
    {
        $producto = Producto::factory()->create(['sku' => 'MAQ-001', 'nombre' => 'Dispensador Frío/Calor']);

        $this->actingAs($this->admin())
            ->getJson('/admin/servicio-tecnico/buscar-producto?q=MAQ-001')
            ->assertOk()
            ->assertJsonFragment(['id' => $producto->id]);

        $this->actingAs($this->admin())
            ->getJson('/admin/servicio-tecnico/buscar-producto?q=Dispensador')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'MAQ-001']);
    }
}
