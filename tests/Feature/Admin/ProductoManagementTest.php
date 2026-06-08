<?php

namespace Tests\Feature\Admin;

use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductoManagementTest extends TestCase
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

    private function csv(UploadedFile|string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('catalogo.csv', $content);
    }

    // --- Acceso ---------------------------------------------------------

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/productos')->assertRedirect('/login');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)->get('/admin/productos')->assertForbidden();
        $this->actingAs($member)->get('/admin/productos/create')->assertForbidden();
        $this->actingAs($member)->get('/admin/productos/exportar')->assertForbidden();
        $this->actingAs($member)->get('/admin/productos/importar')->assertForbidden();
    }

    public function test_manage_productos_permission_grants_access(): void
    {
        $this->actingAs($this->userWith(['manage productos']))->get('/admin/productos')->assertOk();
        $this->actingAs($this->userWith([]))->get('/admin/productos')->assertForbidden();
    }

    // --- CRUD -----------------------------------------------------------

    public function test_admin_can_create_producto(): void
    {
        $this->actingAs($this->admin())->post('/admin/productos', [
            'sku' => 'BOT-20L',
            'nombre' => 'Botellón 20L con manilla',
            'categoria' => 'Botellones',
            'marca' => 'DALI',
            'peso_kg' => '0.85',
            'activo' => '1',
        ])->assertRedirect(route('admin.productos.index'));

        $this->assertDatabaseHas('productos', ['sku' => 'BOT-20L', 'nombre' => 'Botellón 20L con manilla']);
    }

    public function test_store_requires_sku_and_nombre(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/productos', [])
            ->assertSessionHasErrors(['sku', 'nombre']);
    }

    public function test_sku_must_be_unique(): void
    {
        Producto::factory()->create(['sku' => 'DUP-1']);

        $this->actingAs($this->admin())
            ->post('/admin/productos', ['sku' => 'DUP-1', 'nombre' => 'Otro'])
            ->assertSessionHasErrors('sku');
    }

    public function test_peso_must_be_numeric(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/productos', ['sku' => 'X-1', 'nombre' => 'X', 'peso_kg' => 'abc'])
            ->assertSessionHasErrors('peso_kg');
    }

    public function test_atributos_must_be_valid_json(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/productos', ['sku' => 'X-2', 'nombre' => 'X', 'atributos' => '{bad'])
            ->assertSessionHasErrors('atributos');
    }

    public function test_admin_can_update_producto(): void
    {
        $producto = Producto::factory()->create(['sku' => 'UPD-1', 'nombre' => 'Viejo']);

        $this->actingAs($this->admin())
            ->put("/admin/productos/{$producto->id}", ['sku' => 'UPD-1', 'nombre' => 'Nuevo'])
            ->assertRedirect(route('admin.productos.index'));

        $this->assertSame('Nuevo', $producto->fresh()->nombre);
    }

    public function test_admin_can_delete_producto(): void
    {
        $producto = Producto::factory()->create(['sku' => 'DEL-1']);

        $this->actingAs($this->admin())->delete("/admin/productos/{$producto->id}");

        $this->assertDatabaseMissing('productos', ['sku' => 'DEL-1']);
    }

    // --- Filtros --------------------------------------------------------

    public function test_index_filters_by_search_and_categoria(): void
    {
        Producto::factory()->create(['sku' => 'ALFA-1', 'nombre' => 'Alfa', 'categoria' => 'Botellones']);
        Producto::factory()->create(['sku' => 'BETA-1', 'nombre' => 'Beta', 'categoria' => 'Accesorios']);

        $this->actingAs($this->admin())->get('/admin/productos?q=ALFA')
            ->assertSee('ALFA-1')->assertDontSee('BETA-1');

        $this->actingAs($this->admin())->get('/admin/productos?categoria=Accesorios')
            ->assertSee('BETA-1')->assertDontSee('ALFA-1');
    }

    // --- Import CSV -----------------------------------------------------

    public function test_import_creates_products(): void
    {
        $csv = "sku;nombre;categoria;peso_kg\n"
            ."BOT-20L;Botellón 20L;Botellones;0,85\n"
            ."BOT-10L;Botellón 10L;Botellones;0,5\n";

        $this->actingAs($this->admin())
            ->post('/admin/productos/importar', ['archivo' => $this->csv($csv)])
            ->assertRedirect(route('admin.productos.import.form'))
            ->assertSessionHas('importResult');

        $this->assertDatabaseHas('productos', ['sku' => 'BOT-20L', 'nombre' => 'Botellón 20L']);
        $this->assertEquals(0.85, (float) Producto::where('sku', 'BOT-20L')->value('peso_kg')); // coma decimal
        $this->assertSame(2, Producto::count());
    }

    public function test_import_is_idempotent_by_sku(): void
    {
        $csv = "sku;nombre\nUP-1;Primero\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $csv2 = "sku;nombre\nUP-1;Actualizado\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv2)]);

        $this->assertSame(1, Producto::count());
        $this->assertSame('Actualizado', Producto::where('sku', 'UP-1')->value('nombre'));
    }

    public function test_import_sniffs_comma_delimiter(): void
    {
        $csv = "sku,nombre,marca\nC-1,Con Coma,DALI\n";

        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $this->assertDatabaseHas('productos', ['sku' => 'C-1', 'nombre' => 'Con Coma', 'marca' => 'DALI']);
    }

    public function test_import_strips_bom_in_header(): void
    {
        $csv = "\xEF\xBB\xBF"."sku;nombre\nBOM-1;Con Bom\n";

        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $this->assertDatabaseHas('productos', ['sku' => 'BOM-1', 'nombre' => 'Con Bom']);
    }

    public function test_import_converts_windows1252_to_utf8(): void
    {
        // 0xF1 = ñ en Windows-1252 / Latin-1.
        $csv = "sku;nombre\nN-1;Bot".chr(0xF1)."on\n";

        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $this->assertSame("Bot\u{00F1}on", Producto::where('sku', 'N-1')->value('nombre'));
    }

    public function test_import_skips_and_reports_invalid_rows(): void
    {
        $csv = "sku;nombre\nOK-1;Bueno\nBAD-1;\n"; // segunda fila sin nombre

        $response = $this->actingAs($this->admin())
            ->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $this->assertDatabaseHas('productos', ['sku' => 'OK-1']);
        $this->assertDatabaseMissing('productos', ['sku' => 'BAD-1']);

        $result = session('importResult');
        $this->assertSame(1, $result['creados']);
        $this->assertCount(1, $result['errores']);
        $this->assertSame(3, $result['errores'][0]['fila']); // cabecera=1, OK=2, BAD=3
    }

    public function test_import_requires_sku_and_nombre_headers(): void
    {
        $csv = "codigo;titulo\nX;Y\n";

        $this->actingAs($this->admin())
            ->from(route('admin.productos.import.form'))
            ->post('/admin/productos/importar', ['archivo' => $this->csv($csv)])
            ->assertSessionHasErrors('archivo');

        $this->assertSame(0, Producto::count());
    }

    public function test_import_does_not_audit_each_row(): void
    {
        $csv = "sku;nombre\nA-1;Uno\nA-2;Dos\n";

        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        // Decision B1: la carga masiva NO genera audits por fila.
        $this->assertSame(0, Audit::where('auditable_type', Producto::class)->count());
    }

    // --- Export CSV -----------------------------------------------------

    public function test_export_returns_csv_with_bom_and_semicolon(): void
    {
        Producto::factory()->create(['sku' => 'EXP-1', 'nombre' => 'Exportable']);

        $response = $this->actingAs($this->admin())->get('/admin/productos/exportar');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);     // BOM
        $this->assertStringContainsString('sku;nombre;', $body);  // separador ;
        $this->assertStringContainsString('EXP-1', $body);
    }

    public function test_export_honors_filter(): void
    {
        Producto::factory()->create(['sku' => 'CAT-A', 'categoria' => 'Botellones']);
        Producto::factory()->create(['sku' => 'CAT-B', 'categoria' => 'Accesorios']);

        $body = $this->actingAs($this->admin())
            ->get('/admin/productos/exportar?categoria=Botellones')
            ->streamedContent();

        $this->assertStringContainsString('CAT-A', $body);
        $this->assertStringNotContainsString('CAT-B', $body);
    }

    public function test_export_round_trips_through_import(): void
    {
        Producto::factory()->create(['sku' => 'RT-1', 'nombre' => 'Ida y vuelta', 'peso_kg' => 1.5]);

        $body = $this->actingAs($this->admin())->get('/admin/productos/exportar')->streamedContent();

        // Reimportar el export no debe generar errores y debe actualizar (no duplicar).
        $this->actingAs($this->admin())
            ->post('/admin/productos/importar', ['archivo' => $this->csv($body)]);

        $result = session('importResult');
        $this->assertEmpty($result['errores']);
        $this->assertSame(1, Producto::count());
    }

    public function test_template_is_header_only(): void
    {
        $body = $this->actingAs($this->admin())->get('/admin/productos/plantilla')->streamedContent();

        $this->assertStringContainsString('sku;nombre;', $body);
        $lines = array_values(array_filter(explode("\n", trim($body))));
        $this->assertCount(1, $lines); // solo cabecera
    }
}
