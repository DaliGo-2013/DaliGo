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

    public function test_categoria_efectiva_usa_la_correccion_sobre_bsale(): void
    {
        $corregido = Producto::factory()->create(['categoria' => 'AGUA REPUESTOS', 'categoria_interna' => 'Industrial (Carlos)']);
        $sinCorregir = Producto::factory()->create(['categoria' => 'AGUA BOTELLON', 'categoria_interna' => null]);

        $this->assertSame('Industrial (Carlos)', $corregido->categoria_efectiva);  // la corrección manda
        $this->assertSame('AGUA BOTELLON', $sinCorregir->categoria_efectiva);      // sin corrección → Bsale
    }

    public function test_filtro_categoria_usa_la_efectiva(): void
    {
        Producto::factory()->create(['nombre' => 'Con Correccion', 'categoria' => 'AGUA REPUESTOS', 'categoria_interna' => 'Industrial (Carlos)']);
        Producto::factory()->create(['nombre' => 'Sin Correccion', 'categoria' => 'AGUA REPUESTOS', 'categoria_interna' => null]);

        // Filtra por la categoría CORREGIDA: solo el corregido.
        $this->actingAs($this->admin())->get('/admin/productos?categoria='.urlencode('Industrial (Carlos)'))
            ->assertOk()->assertSee('Con Correccion')->assertDontSee('Sin Correccion');

        // Filtra por AGUA REPUESTOS: el corregido ya NO aparece (se fue a Industrial).
        $this->actingAs($this->admin())->get('/admin/productos?categoria='.urlencode('AGUA REPUESTOS'))
            ->assertOk()->assertSee('Sin Correccion')->assertDontSee('Con Correccion');
    }

    public function test_categoria_sugerida_repuestos_industriales_siempre_disponible(): void
    {
        // Aunque ningún producto la use todavía, "Repuestos industriales" debe
        // aparecer como opción para corregir.
        Producto::factory()->create(['categoria' => 'AGUA BOTELLON', 'categoria_interna' => null]);

        $this->actingAs($this->admin())->get('/admin/productos')
            ->assertOk()
            ->assertSee('Repuestos industriales');
    }

    public function test_editar_producto_corrige_via_override_sin_tocar_bsale(): void
    {
        $p = Producto::factory()->create(['sku' => 'EDIT-1', 'nombre' => 'Planta', 'categoria' => 'AGUA PLANTA', 'categoria_interna' => null]);

        // Desde el form individual: cambiar "Categoría" a "Repuestos industriales".
        $this->actingAs($this->admin())
            ->put("/admin/productos/{$p->id}", ['sku' => 'EDIT-1', 'nombre' => 'Planta', 'categoria' => 'Repuestos industriales'])
            ->assertRedirect();

        $p->refresh();
        $this->assertSame('Repuestos industriales', $p->categoria_interna);   // corrección durable
        $this->assertSame('AGUA PLANTA', $p->categoria);                      // Bsale intacta
        $this->assertSame('Repuestos industriales', $p->categoria_efectiva);
    }

    public function test_editar_con_categoria_igual_a_bsale_no_crea_correccion(): void
    {
        $p = Producto::factory()->create(['sku' => 'EDIT-2', 'nombre' => 'Planta', 'categoria' => 'AGUA PLANTA', 'categoria_interna' => null]);

        $this->actingAs($this->admin())
            ->put("/admin/productos/{$p->id}", ['sku' => 'EDIT-2', 'nombre' => 'Planta', 'categoria' => 'AGUA PLANTA'])
            ->assertRedirect();

        // Igual a la de Bsale → no se crea corrección (override queda null).
        $this->assertNull($p->fresh()->categoria_interna);
    }

    public function test_editar_vaciando_categoria_quita_la_correccion(): void
    {
        $p = Producto::factory()->create(['sku' => 'EDIT-3', 'nombre' => 'Planta', 'categoria' => 'AGUA PLANTA', 'categoria_interna' => 'Repuestos industriales']);

        $this->actingAs($this->admin())
            ->put("/admin/productos/{$p->id}", ['sku' => 'EDIT-3', 'nombre' => 'Planta', 'categoria' => ''])
            ->assertRedirect();

        $this->assertNull($p->fresh()->categoria_interna);            // vuelve a la de Bsale
        $this->assertSame('AGUA PLANTA', $p->fresh()->categoria_efectiva);
    }

    public function test_filtro_corregidos(): void
    {
        Producto::factory()->create(['nombre' => 'Corregido X', 'categoria_interna' => 'Industrial (Carlos)']);
        Producto::factory()->create(['nombre' => 'Original Y', 'categoria_interna' => null]);

        $this->actingAs($this->admin())->get('/admin/productos?corregidos=1')
            ->assertOk()->assertSee('Corregido X')->assertDontSee('Original Y');
        $this->actingAs($this->admin())->get('/admin/productos?corregidos=0')
            ->assertOk()->assertSee('Original Y')->assertDontSee('Corregido X');
    }
}
