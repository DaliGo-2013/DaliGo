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

    public function test_peso_out_of_range_is_rejected_not_500(): void
    {
        // decimal(10,3) desborda con valores absurdos -> debe ser error de
        // validacion, no un 500 "Out of range" de MySQL.
        $this->actingAs($this->admin())
            ->post('/admin/productos', ['sku' => 'X-9', 'nombre' => 'X', 'peso_kg' => '99999999999'])
            ->assertSessionHasErrors('peso_kg');

        $csv = "sku;nombre;peso_kg\nX-9;X;99999999999\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);
        $this->assertCount(1, session('importResult')['errores']);
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

    public function test_import_requires_sku_header(): void
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

    // --- Import: semántica de parche --------------------------------------

    public function test_minimal_measures_csv_preserves_other_fields(): void
    {
        $p = Producto::factory()->create([
            'sku' => 'MED-1', 'nombre' => 'Original', 'categoria' => 'Botellones',
            'marca' => 'DALI', 'activo' => true, 'peso_kg' => null,
        ]);

        $csv = "sku;peso_kg;alto_cm;ancho_cm;largo_cm\nMED-1;1,5;30;20;10\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $fresh = $p->fresh();
        $this->assertEquals(1.5, (float) $fresh->peso_kg);
        $this->assertEquals(30.0, (float) $fresh->alto_cm);
        $this->assertSame('Original', $fresh->nombre);      // columna ausente: no tocada
        $this->assertSame('Botellones', $fresh->categoria); // no tocada
        $this->assertSame('DALI', $fresh->marca);           // no tocada
        $this->assertTrue($fresh->activo);                  // no tocada
        $this->assertEmpty(session('importResult')['errores']);
    }

    public function test_new_row_without_nombre_column_errors(): void
    {
        $csv = "sku;peso_kg\nNUEVO-1;2\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $this->assertDatabaseMissing('productos', ['sku' => 'NUEVO-1']);
        $result = session('importResult');
        $this->assertCount(1, $result['errores']);
        $this->assertStringContainsString('nombre', $result['errores'][0]['error']);
    }

    public function test_empty_nombre_cell_on_existing_row_errors_not_500(): void
    {
        Producto::factory()->create(['sku' => 'EX-1', 'nombre' => 'Conservado']);

        $csv = "sku;nombre\nEX-1;\n";
        $response = $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $response->assertRedirect(route('admin.productos.import.form')); // no 500
        $this->assertSame('Conservado', Producto::where('sku', 'EX-1')->value('nombre'));
        $this->assertCount(1, session('importResult')['errores']);
    }

    public function test_empty_cell_clears_nullable_field_and_counts_vaciados(): void
    {
        Producto::factory()->create(['sku' => 'VAC-1', 'marca' => 'DALI']);

        $csv = "sku;marca\nVAC-1;\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $this->assertNull(Producto::where('sku', 'VAC-1')->value('marca'));
        $result = session('importResult');
        $this->assertSame(1, $result['vaciados']);
        $this->assertSame(1, $result['actualizados']);
    }

    public function test_activo_untouched_when_column_absent_and_invalid_token_errors(): void
    {
        Producto::factory()->create(['sku' => 'ACT-1', 'nombre' => 'X', 'activo' => false]);

        // Columna ausente: no se toca.
        $csv = "sku;marca\nACT-1;NuevaMarca\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);
        $this->assertFalse((bool) Producto::where('sku', 'ACT-1')->value('activo'));

        // Token no reconocido: error de fila, sin cambio.
        $csv2 = "sku;activo\nACT-1;x\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv2)]);
        $this->assertCount(1, session('importResult')['errores']);
        $this->assertFalse((bool) Producto::where('sku', 'ACT-1')->value('activo'));
    }

    public function test_ragged_row_treated_as_empty_cells(): void
    {
        Producto::factory()->create(['sku' => 'RAG-1', 'nombre' => 'Intacto', 'marca' => 'DALI', 'activo' => true]);

        // Fila con menos celdas que el header: marca sin celda = vacía (borra);
        // activo sin celda = no tocar.
        $csv = "sku;marca;activo\nRAG-1\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $fresh = Producto::where('sku', 'RAG-1')->first();
        $this->assertNull($fresh->marca);
        $this->assertTrue((bool) $fresh->activo);
        $this->assertEmpty(session('importResult')['errores']);
    }

    public function test_sku_only_file_no_ops_existing_and_errors_unknown(): void
    {
        Producto::factory()->create(['sku' => 'SOLO-1', 'nombre' => 'Igual']);

        $csv = "sku\nSOLO-1\nDESCONOCIDO-9\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $result = session('importResult');
        $this->assertSame(1, $result['sin_cambios']);
        $this->assertSame(0, $result['creados']);
        $this->assertCount(1, $result['errores']); // DESCONOCIDO-9 requiere nombre
    }

    public function test_bsale_columns_in_csv_are_ignored_on_import(): void
    {
        $p = Producto::factory()->create(['sku' => 'BS-1', 'nombre' => 'X', 'bsale_variant_id' => 777, 'barcode' => 'ABC']);

        $csv = "sku;bsale_variant_id;barcode;marca\nBS-1;999;HACKED;DALI\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $fresh = $p->fresh();
        $this->assertSame(777, (int) $fresh->bsale_variant_id); // intacto (solo-export)
        $this->assertSame('ABC', $fresh->barcode);              // intacto (solo-export)
        $this->assertSame('DALI', $fresh->marca);               // la importable sí aplicó
    }

    // --- Filtro de medidas + progreso ---------------------------------------

    public function test_medidas_filter_combines_with_categoria(): void
    {
        Producto::factory()->create(['sku' => 'MC-1', 'nombre' => 'A', 'categoria' => 'CatX', 'peso_kg' => null]);
        Producto::factory()->create(['sku' => 'MC-2', 'nombre' => 'B', 'categoria' => 'CatY', 'peso_kg' => null]);

        // El OR de medidas va agrupado: no debe "fugar" filas de otra categoría.
        $this->actingAs($this->admin())
            ->get('/admin/productos?medidas=incompletas&categoria=CatX')
            ->assertSee('MC-1')
            ->assertDontSee('MC-2');
    }

    public function test_partial_measures_count_as_incompletas(): void
    {
        Producto::factory()->create(['sku' => 'PARC-1', 'nombre' => 'Parcial',
            'peso_kg' => 1, 'alto_cm' => null, 'ancho_cm' => 1, 'largo_cm' => 1]);

        $this->actingAs($this->admin())->get('/admin/productos?medidas=incompletas')->assertSee('PARC-1');
        $this->actingAs($this->admin())->get('/admin/productos?medidas=completas')->assertDontSee('PARC-1');
    }

    public function test_index_shows_progress_counter(): void
    {
        Producto::factory()->create(['sku' => 'PRG-1', 'activo' => true,
            'peso_kg' => 1, 'alto_cm' => 1, 'ancho_cm' => 1, 'largo_cm' => 1]);
        Producto::factory()->create(['sku' => 'PRG-2', 'activo' => true, 'peso_kg' => null]);

        $this->actingAs($this->admin())
            ->get('/admin/productos')
            ->assertSee('Medidas completas')
            ->assertSee('1 de 2 activos');
    }

    // --- Plantilla de medidas ------------------------------------------------

    public function test_plantilla_medidas_access(): void
    {
        $this->get('/admin/productos/plantilla-medidas')->assertRedirect('/login');

        $member = User::factory()->create();
        $member->assignRole('member');
        $this->actingAs($member)->get('/admin/productos/plantilla-medidas')->assertForbidden();
    }

    public function test_plantilla_medidas_downloads_pending_and_honors_filters(): void
    {
        Producto::factory()->create(['sku' => 'PEND-1', 'nombre' => 'Pendiente', 'categoria' => 'CatA', 'peso_kg' => null]);
        Producto::factory()->create(['sku' => 'FULL-1', 'nombre' => 'Completo', 'categoria' => 'CatA',
            'peso_kg' => 1, 'alto_cm' => 1, 'ancho_cm' => 1, 'largo_cm' => 1]);
        Producto::factory()->create(['sku' => 'PEND-2', 'nombre' => 'OtraCat', 'categoria' => 'CatB', 'peso_kg' => null]);

        $body = $this->actingAs($this->admin())
            ->get('/admin/productos/plantilla-medidas?categoria=CatA')
            ->streamedContent();

        $this->assertStringContainsString('sku;producto;categoria_ref;codigo_barras;peso_kg;', $body);
        $this->assertStringContainsString('PEND-1', $body);
        $this->assertStringNotContainsString('FULL-1', $body); // completas excluidas por defecto
        $this->assertStringNotContainsString('PEND-2', $body); // otra categoría
    }

    public function test_plantilla_medidas_reimport_only_writes_measures(): void
    {
        $p = Producto::factory()->create([
            'sku' => 'PM-1', 'nombre' => 'NombreReal', 'categoria' => 'CatReal',
            'barcode' => 'BAR-1', 'peso_kg' => null,
        ]);

        // Simula la plantilla llenada en Excel: referencias "desactualizadas" + medidas nuevas.
        $csv = "sku;producto;categoria_ref;codigo_barras;peso_kg;alto_cm;ancho_cm;largo_cm\n"
            ."PM-1;Nombre Viejo En Excel;OtraCat;XXXX;2,5;30;20;10\n";
        $this->actingAs($this->admin())->post('/admin/productos/importar', ['archivo' => $this->csv($csv)]);

        $fresh = $p->fresh();
        $this->assertEquals(2.5, (float) $fresh->peso_kg);  // medida aplicada
        $this->assertEquals(10.0, (float) $fresh->largo_cm);
        $this->assertSame('NombreReal', $fresh->nombre);    // referencia ignorada
        $this->assertSame('CatReal', $fresh->categoria);    // referencia ignorada
        $this->assertSame('BAR-1', $fresh->barcode);        // referencia ignorada
        $this->assertEmpty(session('importResult')['errores']);
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

    public function test_export_header_is_exact_with_barcode_at_end(): void
    {
        Producto::factory()->create(['sku' => 'EXPB-1']);

        $body = $this->actingAs($this->admin())->get('/admin/productos/exportar')->streamedContent();

        $this->assertStringContainsString(
            'sku;nombre;descripcion;categoria;marca;peso_kg;alto_cm;ancho_cm;largo_cm;activo;barcode;bsale_variant_id;bsale_product_id',
            $body,
        );
    }

    public function test_template_is_header_only(): void
    {
        $body = $this->actingAs($this->admin())->get('/admin/productos/plantilla')->streamedContent();

        $this->assertStringContainsString('sku;nombre;', $body);
        $lines = array_values(array_filter(explode("\n", trim($body))));
        $this->assertCount(1, $lines); // solo cabecera
    }
}
