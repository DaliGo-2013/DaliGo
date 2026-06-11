<?php

namespace Tests\Feature\Admin;

use App\Models\ListaPrecio;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaPrecioManagementTest extends TestCase
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
        $lista = ListaPrecio::factory()->create();

        $this->get('/admin/listas-precios')->assertRedirect('/login');
        $this->get("/admin/listas-precios/{$lista->id}")->assertRedirect('/login');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $lista = ListaPrecio::factory()->create();

        $this->actingAs($member)->get('/admin/listas-precios')->assertForbidden();
        $this->actingAs($member)->get("/admin/listas-precios/{$lista->id}")->assertForbidden();
        $this->actingAs($member)->put("/admin/listas-precios/{$lista->id}", ['canal' => 'web'])->assertForbidden();
    }

    public function test_admin_sees_index_with_listas(): void
    {
        ListaPrecio::factory()->create(['nombre' => 'COQUIMBO-1', 'canal' => 'mayorista']);
        ListaPrecio::factory()->create(['nombre' => 'SANTIAGO RETAIL', 'activa' => false]);

        $this->actingAs($this->admin())->get('/admin/listas-precios')
            ->assertOk()
            ->assertSee('COQUIMBO-1')
            ->assertSee('mayorista')
            ->assertSee('SANTIAGO RETAIL')
            ->assertSee('inactiva');
    }

    public function test_show_lists_precios_and_filters_by_sku(): void
    {
        $lista = ListaPrecio::factory()->create();
        $bidon = Producto::factory()->create(['sku' => 'BIDON-20L', 'nombre' => 'Bidón 20 litros']);
        $tapa = Producto::factory()->create(['sku' => 'TAPA-001', 'nombre' => 'Tapa válvula']);
        Precio::factory()->create(['lista_precio_id' => $lista->id, 'producto_id' => $bidon->id, 'precio_con_iva' => 2500]);
        Precio::factory()->create(['lista_precio_id' => $lista->id, 'producto_id' => $tapa->id, 'precio_con_iva' => 500]);

        $this->actingAs($this->admin())->get("/admin/listas-precios/{$lista->id}")
            ->assertOk()
            ->assertSee('Bidón 20 litros')
            ->assertSee('Tapa válvula')
            ->assertSee('2.500');

        $this->actingAs($this->admin())->get("/admin/listas-precios/{$lista->id}?q=BIDON")
            ->assertOk()
            ->assertSee('Bidón 20 litros')
            ->assertDontSee('Tapa válvula');
    }

    public function test_admin_updates_canal(): void
    {
        $lista = ListaPrecio::factory()->create(['canal' => null]);

        $this->actingAs($this->admin())
            ->put("/admin/listas-precios/{$lista->id}", ['canal' => 'mayorista'])
            ->assertRedirect();

        $this->assertSame('mayorista', $lista->fresh()->canal);
    }

    public function test_canal_can_be_cleared_and_long_canal_is_rejected(): void
    {
        $lista = ListaPrecio::factory()->create(['canal' => 'web']);

        $this->actingAs($this->admin())
            ->put("/admin/listas-precios/{$lista->id}", ['canal' => ''])
            ->assertRedirect();
        $this->assertNull($lista->fresh()->canal);

        $this->actingAs($this->admin())
            ->put("/admin/listas-precios/{$lista->id}", ['canal' => str_repeat('x', 51)])
            ->assertSessionHasErrors('canal');
    }

    public function test_producto_edit_shows_mirrored_prices(): void
    {
        $producto = Producto::factory()->create(['bsale_variant_id' => 979]);
        $lista = ListaPrecio::factory()->create(['nombre' => 'COQUIMBO-1']);
        Precio::factory()->create([
            'lista_precio_id' => $lista->id,
            'producto_id' => $producto->id,
            'precio_con_iva' => 12990,
        ]);

        $this->actingAs($this->admin())->get("/admin/productos/{$producto->id}/edit")
            ->assertOk()
            ->assertSee('Precios por lista')
            ->assertSee('COQUIMBO-1')
            ->assertSee('12.990');
    }

    public function test_producto_edit_without_bsale_link_hides_prices_section(): void
    {
        $producto = Producto::factory()->create(['bsale_variant_id' => null]);

        $this->actingAs($this->admin())->get("/admin/productos/{$producto->id}/edit")
            ->assertOk()
            ->assertDontSee('Precios por lista');
    }
}
