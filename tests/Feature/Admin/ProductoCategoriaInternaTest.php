<?php

namespace Tests\Feature\Admin;

use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Categoría INTERNA del catálogo: clasificación propia de DaliGo (curada a mano,
 * ej. "Industrial (Carlos)") que corre en paralelo a `categoria` (esa la manda
 * Bsale). Se asigna en masa por checkboxes y NO toca `categoria`.
 */
class ProductoCategoriaInternaTest extends TestCase
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

    private function url(): string
    {
        return route('admin.productos.clasificacion-interna');
    }

    public function test_sin_permiso_es_forbidden(): void
    {
        $role = Role::firstOrCreate(['name' => 'custom', 'guard_name' => 'web']);
        $role->syncPermissions([]);
        $user = tap(User::factory()->create())->assignRole($role);

        $p = Producto::factory()->create();
        $this->actingAs($user)->post($this->url(), ['ids' => [$p->id], 'accion' => 'asignar', 'categoria_interna' => 'X'])
            ->assertForbidden();
    }

    public function test_asignar_marca_solo_los_seleccionados_sin_tocar_categoria(): void
    {
        $a = Producto::factory()->create(['sku' => 'A-1', 'categoria' => 'AGUA REPUESTOS']);
        $b = Producto::factory()->create(['sku' => 'B-1', 'categoria' => 'AGUA REPUESTOS']);
        $c = Producto::factory()->create(['sku' => 'C-1', 'categoria' => 'AGUA REPUESTOS']);

        $this->actingAs($this->admin())
            ->post($this->url(), ['ids' => [$a->id, $b->id], 'accion' => 'asignar', 'categoria_interna' => 'Industrial (Carlos)'])
            ->assertRedirect();

        $this->assertSame('Industrial (Carlos)', $a->fresh()->categoria_interna);
        $this->assertSame('Industrial (Carlos)', $b->fresh()->categoria_interna);
        $this->assertNull($c->fresh()->categoria_interna);          // no seleccionado
        // La categoría de Bsale queda intacta.
        $this->assertSame('AGUA REPUESTOS', $a->fresh()->categoria);
    }

    public function test_quitar_limpia_la_categoria_interna(): void
    {
        $p = Producto::factory()->create(['categoria_interna' => 'Industrial (Carlos)']);

        $this->actingAs($this->admin())
            ->post($this->url(), ['ids' => [$p->id], 'accion' => 'quitar'])
            ->assertRedirect();

        $this->assertNull($p->fresh()->categoria_interna);
    }

    public function test_asignar_exige_nombre_de_categoria_interna(): void
    {
        $p = Producto::factory()->create();

        $this->actingAs($this->admin())
            ->post($this->url(), ['ids' => [$p->id], 'accion' => 'asignar', 'categoria_interna' => ''])
            ->assertSessionHasErrors('categoria_interna');
    }

    public function test_exige_al_menos_un_producto(): void
    {
        $this->actingAs($this->admin())
            ->post($this->url(), ['ids' => [], 'accion' => 'asignar', 'categoria_interna' => 'X'])
            ->assertSessionHasErrors('ids');
    }

    public function test_filtro_por_categoria_interna_y_sin_asignar(): void
    {
        Producto::factory()->create(['nombre' => 'Con Interna', 'categoria_interna' => 'Industrial (Carlos)']);
        Producto::factory()->create(['nombre' => 'Sin Interna', 'categoria_interna' => null]);

        // Filtra por la categoría interna: solo el asignado.
        $this->actingAs($this->admin())->get('/admin/productos?categoria_interna='.urlencode('Industrial (Carlos)'))
            ->assertOk()->assertSee('Con Interna')->assertDontSee('Sin Interna');

        // Filtra "(sin asignar)": solo el que no tiene.
        $this->actingAs($this->admin())->get('/admin/productos?categoria_interna=__none__')
            ->assertOk()->assertSee('Sin Interna')->assertDontSee('Con Interna');
    }
}
