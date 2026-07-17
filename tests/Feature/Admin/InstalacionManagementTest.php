<?php

namespace Tests\Feature\Admin;

use App\Models\Instalacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstalacionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tecnicoIndustrial(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico_industrial');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'fecha' => now()->toDateString(),
            'cliente_nombre' => 'Agua Purificada Canto del Agua',
            'comuna_region' => 'Copiapó',
            'categoria' => 'lavadora',
            'producto' => 'LAVADORA BOTELLON 20L-220V',
            'instalacion' => '1',
            'dias' => '2',
            'vendedor' => 'Luis Figueroa',
            'n_factura' => '250868',
            'forma_pago' => 'transferencia',
        ], $overrides);
    }

    // --- Acceso / gating ---

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/instalaciones')->assertRedirect('/login');
    }

    public function test_member_sin_permiso_es_rechazado(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)->get('/admin/instalaciones')->assertForbidden();
        $this->actingAs($member)->post('/admin/instalaciones', $this->payload())->assertForbidden();
    }

    public function test_tecnico_industrial_ve_y_registra(): void
    {
        $this->actingAs($this->tecnicoIndustrial())->get('/admin/instalaciones')->assertOk();
    }

    public function test_jefe_ventas_tambien_gestiona(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');

        $this->actingAs($jefe)->get('/admin/instalaciones')->assertOk();
    }

    // --- CRUD ---

    public function test_registra_una_instalacion(): void
    {
        $this->actingAs($this->tecnicoIndustrial())
            ->post('/admin/instalaciones', $this->payload(['puesta_en_marcha' => '1']))
            ->assertRedirect(route('admin.instalaciones.index'));

        $this->assertDatabaseHas('instalaciones', [
            'cliente_nombre' => 'Agua Purificada Canto del Agua',
            'categoria' => 'lavadora',
            'instalacion' => true,
            'puesta_en_marcha' => true,
            'dias' => 2,
            'vendedor' => 'Luis Figueroa',
            'forma_pago' => 'transferencia',
        ]);
    }

    public function test_checkboxes_sin_marcar_quedan_en_falso(): void
    {
        $data = $this->payload();
        unset($data['instalacion']); // ninguna marcada

        $this->actingAs($this->tecnicoIndustrial())
            ->post('/admin/instalaciones', $data)
            ->assertRedirect(route('admin.instalaciones.index'));

        $this->assertDatabaseHas('instalaciones', [
            'cliente_nombre' => 'Agua Purificada Canto del Agua',
            'instalacion' => false,
            'puesta_en_marcha' => false,
        ]);
    }

    public function test_crear_exige_fecha_cliente_y_categoria(): void
    {
        $this->actingAs($this->tecnicoIndustrial())
            ->post('/admin/instalaciones', [])
            ->assertSessionHasErrors(['fecha', 'cliente_nombre', 'categoria']);
    }

    public function test_categoria_y_forma_pago_invalidas_se_rechazan(): void
    {
        $this->actingAs($this->tecnicoIndustrial())
            ->post('/admin/instalaciones', $this->payload(['categoria' => 'inventada', 'forma_pago' => 'trueque']))
            ->assertSessionHasErrors(['categoria', 'forma_pago']);
    }

    public function test_actualiza_una_instalacion(): void
    {
        $ins = Instalacion::factory()->create(['categoria' => 'lavadora', 'vendedor' => 'Luis Figueroa']);

        $this->actingAs($this->tecnicoIndustrial())
            ->put("/admin/instalaciones/{$ins->id}", $this->payload(['categoria' => 'planta', 'vendedor' => 'Carlos Toledo']))
            ->assertRedirect(route('admin.instalaciones.index'));

        $this->assertSame('planta', $ins->fresh()->categoria);
        $this->assertSame('Carlos Toledo', $ins->fresh()->vendedor);
    }

    public function test_elimina_una_instalacion(): void
    {
        $ins = Instalacion::factory()->create();

        $this->actingAs($this->tecnicoIndustrial())->delete("/admin/instalaciones/{$ins->id}");

        $this->assertDatabaseMissing('instalaciones', ['id' => $ins->id]);
    }
}
