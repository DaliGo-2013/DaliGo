<?php

namespace Tests\Feature\Admin;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodegaManagementTest extends TestCase
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

    public function test_guest_is_redirected(): void
    {
        $bodega = Bodega::factory()->create();

        $this->get('/admin/bodegas')->assertRedirect('/login');
        $this->get("/admin/bodegas/{$bodega->id}")->assertRedirect('/login');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $bodega = Bodega::factory()->create();

        $this->actingAs($member)->get('/admin/bodegas')->assertForbidden();
        $this->actingAs($member)->get("/admin/bodegas/{$bodega->id}")->assertForbidden();
    }

    public function test_admin_sees_index(): void
    {
        Bodega::factory()->create(['nombre' => 'MIRADOR']);
        Bodega::factory()->create(['nombre' => 'CONCEPCION', 'activa' => false]);

        $this->actingAs($this->admin())->get('/admin/bodegas')
            ->assertOk()
            ->assertSee('MIRADOR')
            ->assertSee('CONCEPCION')
            ->assertSee('inactiva');
    }

    public function test_show_lists_stock_and_filters(): void
    {
        $bodega = Bodega::factory()->create(['nombre' => 'MIRADOR']);
        $bidon = Producto::factory()->create(['sku' => 'BIDON-20L', 'nombre' => 'Bidón 20 litros']);
        $tapa = Producto::factory()->create(['sku' => 'TAPA-001', 'nombre' => 'Tapa válvula']);
        Stock::factory()->create(['bodega_id' => $bodega->id, 'producto_id' => $bidon->id, 'stock_real' => 33, 'stock_reservado' => 0, 'stock_disponible' => 33]);
        Stock::factory()->create(['bodega_id' => $bodega->id, 'producto_id' => $tapa->id, 'stock_real' => 0, 'stock_reservado' => 0, 'stock_disponible' => 0]);

        $this->actingAs($this->admin())->get("/admin/bodegas/{$bodega->id}")
            ->assertOk()
            ->assertSee('Bidón 20 litros')
            ->assertSee('Tapa válvula')
            ->assertSee('33');

        // Filtro por SKU
        $this->actingAs($this->admin())->get("/admin/bodegas/{$bodega->id}?q=BIDON")
            ->assertOk()
            ->assertSee('Bidón 20 litros')
            ->assertDontSee('Tapa válvula');

        // Filtro "solo con stock disponible"
        $this->actingAs($this->admin())->get("/admin/bodegas/{$bodega->id}?con_stock=1")
            ->assertOk()
            ->assertSee('Bidón 20 litros')
            ->assertDontSee('Tapa válvula');
    }

    public function test_producto_edit_shows_stock_per_bodega(): void
    {
        $producto = Producto::factory()->create(['bsale_variant_id' => 979]);
        $bodega = Bodega::factory()->create(['nombre' => 'MIRADOR']);
        Stock::factory()->create([
            'bodega_id' => $bodega->id,
            'producto_id' => $producto->id,
            'stock_real' => 145, 'stock_reservado' => 0, 'stock_disponible' => 145,
        ]);

        $this->actingAs($this->admin())->get("/admin/productos/{$producto->id}/edit")
            ->assertOk()
            ->assertSee('Stock por bodega')
            ->assertSee('MIRADOR')
            ->assertSee('145');
    }

    public function test_producto_edit_without_bsale_link_hides_stock_section(): void
    {
        $producto = Producto::factory()->create(['bsale_variant_id' => null]);

        $this->actingAs($this->admin())->get("/admin/productos/{$producto->id}/edit")
            ->assertOk()
            ->assertDontSee('Stock por bodega');
    }
}
