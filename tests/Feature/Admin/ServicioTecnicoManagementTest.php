<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
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
            'tipo_equipo' => 'dispensador',
            'sucursal_id' => Sucursal::factory()->create()->id,
            'numero_serie' => 'SN-1234',
            'falla_reportada' => 'No enciende',
            'estado' => 'recibido',
            'facturacion' => 'reparacion',
        ], $overrides);
    }

    /**
     * Payload de garantia: incluye el documento de compra (factura/boleta),
     * su numero y la fecha de compra (dentro de los 6 meses por defecto).
     */
    private function payloadGarantia(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => 'B-12345',
            'garantia_doc_fecha' => now()->subMonths(2)->toDateString(),
        ], $overrides));
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

    public function test_vendedor_puede_ver_pero_no_gestionar(): void
    {
        // El seeder le da 'view servicio tecnico' al vendedor (solo lectura).
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $orden = OrdenServicio::factory()->create();

        // Ve listado y detalle.
        $this->actingAs($vendedor)->get('/admin/servicio-tecnico')->assertOk();
        $this->actingAs($vendedor)->get(route('admin.servicio-tecnico.show', $orden))->assertOk();

        // No puede gestionar ni entrar al taller.
        $this->actingAs($vendedor)->get(route('admin.servicio-tecnico.create'))->assertForbidden();
        $this->actingAs($vendedor)->get(route('admin.servicio-tecnico.reparacion', $orden))->assertForbidden();
        $this->actingAs($vendedor)->put(route('admin.servicio-tecnico.update', $orden), [])->assertForbidden();
        $this->actingAs($vendedor)->delete(route('admin.servicio-tecnico.destroy', $orden))->assertForbidden();
    }

    // --- CRUD ---

    /**
     * El navegador SIEMPRE envia los campos del bloque de garantia (el x-show
     * solo los oculta), asi que en una reparacion llegan como "" (-> null).
     * Regresion: sin 'nullable', Rule::in rechazaba garantia_doc_tipo null.
     */
    public function test_reparacion_acepta_campos_de_garantia_vacios(): void
    {
        $this->actingAs($this->admin())->post('/admin/servicio-tecnico', $this->payload([
            'facturacion' => 'reparacion',
            'garantia_doc_tipo' => '',
            'garantia_doc_numero' => '',
            'garantia_doc_fecha' => '',
        ]))->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.servicio-tecnico.index'));
    }

    public function test_admin_can_register_orden(): void
    {
        $cliente = Cliente::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($this->admin())->post('/admin/servicio-tecnico', $this->payload([
            'cliente_id' => $cliente->id,
            'producto_id' => $producto->id,
            'cliente_telefono' => '+56 9 1234 5678',
            'tipo_equipo' => 'lavadora',
            'numero_serie' => 'SN-555',
            'facturacion' => 'reparacion',
            'falla_reportada' => 'No enciende',
        ]))->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'cliente_id' => $cliente->id,
            'cliente_nombre' => 'Juan Pérez',
            'cliente_rut' => '12345678-5',   // normalizado
            'cliente_telefono' => '+56 9 1234 5678',
            'producto_id' => $producto->id,
            'tipo_equipo' => 'lavadora',
            'numero_serie' => 'SN-555',
            'facturacion' => 'reparacion',
            'estado' => 'recibido',
        ]);
    }

    public function test_garantia_vigente_se_registra_con_documento(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payloadGarantia([
                'garantia_doc_tipo' => 'factura',
                'garantia_doc_numero' => 'F-9001',
                'garantia_doc_fecha' => now()->subMonths(3)->toDateString(),
            ]))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'factura',
            'garantia_doc_numero' => 'F-9001',
        ]);
    }

    public function test_garantia_exige_documento_y_fecha(): void
    {
        // facturacion=garantia sin los datos del documento => error.
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['facturacion' => 'garantia']))
            ->assertSessionHasErrors(['garantia_doc_tipo', 'garantia_doc_numero', 'garantia_doc_fecha']);
    }

    public function test_garantia_vencida_mas_de_6_meses_es_rechazada(): void
    {
        // Compra hace 8 meses => al ingreso de hoy ya vencio la garantia.
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payloadGarantia([
                'garantia_doc_fecha' => now()->subMonths(8)->toDateString(),
            ]))
            ->assertSessionHasErrors('garantia_doc_fecha');
    }

    public function test_reparacion_no_guarda_datos_de_garantia(): void
    {
        // Aunque vengan datos de garantia, si la condicion es reparacion se descartan.
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload([
                'facturacion' => 'reparacion',
                'garantia_doc_tipo' => 'boleta',
                'garantia_doc_numero' => 'B-1',
                'garantia_doc_fecha' => now()->subMonths(1)->toDateString(),
            ]))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'facturacion' => 'reparacion',
            'garantia_doc_tipo' => null,
            'garantia_doc_numero' => null,
            'garantia_doc_fecha' => null,
        ]);
    }

    public function test_store_requires_obligatorios(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', [
                'cliente_nombre' => '', 'cliente_rut' => '', 'fecha_ingreso' => '',
                'tipo_equipo' => '', 'falla_reportada' => '',
                'estado' => '', 'facturacion' => '',
            ])
            ->assertSessionHasErrors([
                'cliente_nombre', 'cliente_rut', 'fecha_ingreso',
                'tipo_equipo', 'sucursal_id', 'numero_serie', 'falla_reportada', 'estado', 'facturacion',
            ]);
    }

    public function test_numero_serie_y_textos_exigen_minimo_3(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload([
                'numero_serie' => 'AB',
                'cliente_nombre' => 'Jo',
                'falla_reportada' => 'no',
            ]))
            ->assertSessionHasErrors(['numero_serie', 'cliente_nombre', 'falla_reportada']);
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
                'facturacion' => 'reparacion',
            ]))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $fresh = $orden->fresh();
        $this->assertSame('reparado', $fresh->estado);
        $this->assertSame('reparacion', $fresh->facturacion);
    }

    public function test_admin_can_delete_orden(): void
    {
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->admin())->delete("/admin/servicio-tecnico/{$orden->id}");

        $this->assertDatabaseMissing('ordenes_servicio', ['id' => $orden->id]);
    }

    public function test_admin_can_view_orden_detail(): void
    {
        $orden = OrdenServicio::factory()->create(['cliente_nombre' => 'Detalle SpA']);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('Detalle SpA')
            ->assertSee($orden->folio);
    }

    public function test_member_cannot_view_orden_detail(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($member)->get(route('admin.servicio-tecnico.show', $orden))->assertForbidden();
    }

    public function test_garantia_sin_documento_se_trata_como_reparacion(): void
    {
        $orden = OrdenServicio::factory()->create([
            'facturacion' => 'garantia',
            'garantia_doc_fecha' => null,
        ]);

        $this->assertSame('reparacion', $orden->condicion_efectiva);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertDontSee('Documento de garantía');   // no se muestra como garantía
    }

    public function test_garantia_vigente_se_muestra_como_garantia(): void
    {
        $orden = OrdenServicio::factory()->create([
            'facturacion' => 'garantia',
            'fecha_ingreso' => now()->toDateString(),
            'garantia_doc_fecha' => now()->subMonths(2)->toDateString(),
        ]);

        $this->assertSame('garantia', $orden->condicion_efectiva);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertSee('Documento de garantía');
    }

    // --- Reparacion (etapa de taller) ---

    public function test_member_cannot_open_reparacion(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($member)->get(route('admin.servicio-tecnico.reparacion', $orden))->assertForbidden();
    }

    public function test_tecnico_can_open_reparacion(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($tecnico)->get(route('admin.servicio-tecnico.reparacion', $orden))->assertOk();
    }

    public function test_guardar_reparacion_registra_arreglo_y_repuestos(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'estado' => 'recibido']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'trabajo_realizado' => 'Cambio de motor y correa',
                'mano_obra' => 15000,
                'fecha_aviso' => now()->toDateString(),
                'repuestos' => [
                    ['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000],
                    ['nombre' => 'Correa', 'cantidad' => 2, 'precio_unitario' => 5000],
                    ['nombre' => '', 'cantidad' => 1, 'precio_unitario' => 0], // fila vacia => se ignora
                ],
            ])
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $fresh = $orden->fresh()->load('repuestos');
        $this->assertSame('reparado', $fresh->estado);
        $this->assertSame('Cambio de motor y correa', $fresh->trabajo_realizado);
        $this->assertSame(15000, $fresh->mano_obra);
        $this->assertCount(2, $fresh->repuestos);                 // la vacia no se guarda
        $this->assertSame(55000, $fresh->costo_total);            // 30000 + (2*5000) + 15000
        $this->assertDatabaseHas('orden_servicio_repuestos', ['orden_servicio_id' => $orden->id, 'nombre' => 'Motor']);
    }

    public function test_guardar_reparacion_reemplaza_repuestos_anteriores(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion']);
        $orden->repuestos()->create(['nombre' => 'Viejo', 'cantidad' => 1, 'precio_unitario' => 1000]);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'repuestos' => [
                    ['nombre' => 'Nuevo', 'cantidad' => 1, 'precio_unitario' => 2000],
                ],
            ])
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseMissing('orden_servicio_repuestos', ['nombre' => 'Viejo']);
        $this->assertDatabaseHas('orden_servicio_repuestos', ['nombre' => 'Nuevo']);
        $this->assertCount(1, $orden->fresh()->repuestos);
    }

    public function test_guardar_reparacion_rechaza_estado_invalido(): void
    {
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), ['estado' => 'volando'])
            ->assertSessionHasErrors('estado');
    }

    public function test_repuesto_en_reparacion_exige_nombre_y_precio(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'repuestos' => [
                    ['nombre' => 'XY', 'cantidad' => 1, 'precio_unitario' => 0], // nombre corto + sin precio
                ],
            ])
            ->assertSessionHasErrors(['repuestos.0.nombre', 'repuestos.0.precio_unitario']);
    }

    // --- Filtros ---

    public function test_index_filters_by_estado_and_tipo(): void
    {
        OrdenServicio::factory()->create(['cliente_nombre' => 'Alfa SpA', 'estado' => 'recibido', 'tipo_equipo' => 'dispensador']);
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
