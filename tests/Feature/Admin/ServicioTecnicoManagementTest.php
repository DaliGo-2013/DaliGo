<?php

namespace Tests\Feature\Admin;

use App\Mail\IngresoTallerRecibido;
use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Precio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
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
            'cliente_email' => 'cliente@correo.cl',
            'fecha_ingreso' => now()->toDateString(),
            'tipo_equipo' => 'dispensador',
            'producto_id' => Producto::factory()->create()->id,   // obligatorio: del catálogo Dali
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

    public function test_registra_recepcion_en_ruta_guarda_la_ciudad(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload([
                'sucursal_id' => 'ruta',
                'ruta' => 'Rancagua',
            ]))
            ->assertRedirect('/admin/servicio-tecnico');

        $this->assertDatabaseHas('ordenes_servicio', [
            'cliente_nombre' => 'Juan Pérez',
            'sucursal_id' => null,
            'ruta' => 'Rancagua',
        ]);
    }

    public function test_ruta_exige_la_ciudad(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload([
                'sucursal_id' => 'ruta',
                // sin 'ruta' (ciudad)
            ]))
            ->assertSessionHasErrors('ruta');
    }

    public function test_editar_a_ruta_limpia_la_sucursal(): void
    {
        $orden = OrdenServicio::factory()->create(['ruta' => null]);

        $this->actingAs($this->admin())
            ->put('/admin/servicio-tecnico/'.$orden->id, $this->payload([
                'sucursal_id' => 'ruta',
                'ruta' => 'Los Andes',
                'estado' => 'recibido',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('ordenes_servicio', [
            'id' => $orden->id,
            'sucursal_id' => null,
            'ruta' => 'Los Andes',
        ]);
    }

    public function test_maquina_propia_no_exige_rut_ni_correo(): void
    {
        $payload = $this->payload(['cliente_nombre' => 'IMP. DALI']);
        unset($payload['cliente_rut'], $payload['cliente_email']);

        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'cliente_nombre' => 'IMP. DALI',
            'cliente_rut' => null,
            'cliente_email' => null,
        ]);
    }

    public function test_maquina_propia_detecta_variantes_del_nombre(): void
    {
        foreach (['importadora dali', 'IMP DALI', 'Imp. Dali', 'IMP.DALI', 'IMP, DALI', 'DALI'] as $nombre) {
            $payload = $this->payload(['cliente_nombre' => $nombre]);
            unset($payload['cliente_rut'], $payload['cliente_email']);

            $this->actingAs($this->admin())
                ->post('/admin/servicio-tecnico', $payload)
                ->assertSessionHasNoErrors();
        }
    }

    public function test_cliente_comun_sigue_exigiendo_rut_y_correo(): void
    {
        $payload = $this->payload(['cliente_nombre' => 'Juan Pérez']);
        unset($payload['cliente_rut'], $payload['cliente_email']);

        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $payload)
            ->assertSessionHasErrors(['cliente_rut', 'cliente_email']);
    }

    public function test_categoria_se_guarda_solo_para_maquina_propia(): void
    {
        // Propia: se guarda la categoría de cierre.
        $propia = OrdenServicio::factory()->create(['cliente_nombre' => 'IMP. DALI', 'facturacion' => 'reparacion']);
        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $propia), [
                'estado' => 'entregado', 'categoria' => 'segunda', 'repuestos' => [],
            ])->assertRedirect();
        $this->assertDatabaseHas('ordenes_servicio', ['id' => $propia->id, 'categoria' => 'segunda']);

        // Cliente común: aunque llegue 'categoria', se ignora (queda null).
        $normal = OrdenServicio::factory()->create(['cliente_nombre' => 'Juan Pérez', 'facturacion' => 'reparacion']);
        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $normal), [
                'estado' => 'entregado', 'categoria' => 'segunda', 'repuestos' => [],
            ])->assertRedirect();
        $this->assertDatabaseHas('ordenes_servicio', ['id' => $normal->id, 'categoria' => null]);
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
                // numero_serie NO va: es condicional al tipo (tests dedicados).
                'cliente_nombre', 'cliente_rut', 'cliente_email', 'fecha_ingreso',
                'tipo_equipo', 'producto_id', 'sucursal_id', 'falla_reportada', 'facturacion',
            ]);
    }

    /** El correo del cliente es obligatorio al registrar en el mostrador. */
    public function test_store_exige_correo(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['cliente_email' => '']))
            ->assertSessionHasErrors('cliente_email');
    }

    /** Al registrar en el mostrador se le envia el folio al cliente por correo. */
    public function test_store_envia_folio_por_correo(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['cliente_email' => 'ana@correo.cl']))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', ['cliente_email' => 'ana@correo.cl']);
        Mail::assertSent(IngresoTallerRecibido::class, fn ($mail) => $mail->hasTo('ana@correo.cl'));
    }

    public function test_producto_id_es_obligatorio(): void
    {
        // El codigo (producto Dali) es obligatorio en el mostrador y debe existir
        // en el catalogo.
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['producto_id' => '']))
            ->assertSessionHasErrors('producto_id');
    }

    public function test_registro_guarda_quien_recibio(): void
    {
        // Queda el nombre del encargado que registro el ingreso en el mostrador.
        $encargado = tap($this->admin())->update(['name' => 'Fernando St']);

        $this->actingAs($encargado)
            ->post('/admin/servicio-tecnico', $this->payload(['numero_serie' => 'SN-REC-1']))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'numero_serie' => 'SN-REC-1',
            'recibida_por' => 'Fernando St',
        ]);
    }

    public function test_falla_tecnico_se_guarda_aparte_de_la_del_cliente(): void
    {
        // La falla del cliente y la que agrega el tecnico se guardan por separado.
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload([
                'falla_reportada' => 'No enciende (cliente)',
                'falla_tecnico' => 'Ademas: abollado lateral derecho (tecnico)',
            ]))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', [
            'falla_reportada' => 'No enciende (cliente)',
            'falla_tecnico' => 'Ademas: abollado lateral derecho (tecnico)',
        ]);
    }

    /**
     * Al registrar, el staff (tecnico/admin) SI puede elegir el estado inicial
     * para ir informando el paso a paso; se respeta lo que envia. La fecha de
     * entrega, en cambio, la sigue calculando el servidor (dias habiles de la
     * sucursal, saltando fines de semana y feriados) e ignora lo que llegue.
     */
    public function test_store_respeta_estado_del_staff_y_calcula_fecha_del_servidor(): void
    {
        config(['feriados' => ['2026-07-07']]); // martes feriado: corre el estimado un dia

        // Sucursal con codigo fuera del mapa -> dias_reparacion_default (15).
        $sucursal = Sucursal::factory()->create();

        $this->actingAs($this->admin())->post('/admin/servicio-tecnico', $this->payload([
            'sucursal_id' => $sucursal->id,
            'fecha_ingreso' => '2026-07-06',      // lunes
            'estado' => 'en_revision',            // el staff elige el estado inicial
            'fecha_entrega' => '2026-01-01',      // intento de manipulacion -> ignorado
        ]))->assertSessionHasNoErrors();

        // 15 habiles desde el martes 7 (feriado): termina el martes 28.
        $this->assertDatabaseHas('ordenes_servicio', [
            'fecha_ingreso' => '2026-07-06 00:00:00',
            'estado' => 'en_revision',            // respetado
            'fecha_entrega' => '2026-07-28 00:00:00',
        ]);
    }

    /** Dispensador/lavadora: el N° de serie ES obligatorio (serie unica). */
    public function test_store_exige_serie_para_dispensador_y_lavadora(): void
    {
        foreach (['dispensador', 'lavadora'] as $tipo) {
            $this->actingAs($this->admin())
                ->post('/admin/servicio-tecnico', $this->payload([
                    'tipo_equipo' => $tipo,
                    'numero_serie' => '',
                ]))
                ->assertSessionHasErrors('numero_serie');
        }
    }

    /** Bombas/herramientas (herramienta/otro): el N° de serie es OPCIONAL. */
    public function test_store_serie_opcional_para_herramienta_y_otro(): void
    {
        foreach (['herramienta', 'otro'] as $tipo) {
            $this->actingAs($this->admin())
                ->post('/admin/servicio-tecnico', $this->payload([
                    'tipo_equipo' => $tipo,
                    'numero_serie' => '',
                ]))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('ordenes_servicio', [
                'tipo_equipo' => $tipo,
                'numero_serie' => null,
            ]);
        }
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

    public function test_bomba_es_un_tipo_valido(): void
    {
        // Bomba de agua: N° de serie opcional (no está en SERIE_OBLIGATORIA_TIPOS).
        $this->actingAs($this->admin())
            ->post('/admin/servicio-tecnico', $this->payload(['tipo_equipo' => 'bomba', 'numero_serie' => '']))
            ->assertRedirect(route('admin.servicio-tecnico.index'));

        $this->assertDatabaseHas('ordenes_servicio', ['tipo_equipo' => 'bomba', 'numero_serie' => null]);
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

    public function test_reparacion_ofrece_respuestas_fijas_de_trabajo(): void
    {
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            // rótulos de grupo (optgroup) y algunas respuestas del config
            ->assertSee('Reparada')
            ->assertSee('Sin solución (irreparable)')
            ->assertSee('Cambio de celda de peltier — funciona normal')
            ->assertSee('Motor/compresor trabado o pegado — irreparable');
    }

    public function test_guardar_reparacion_persiste_la_respuesta_de_trabajo(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion']);
        $respuesta = 'Cambio de caldera — funciona normal';

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'causa_falla' => 'uso_normal',
                'trabajo_realizado' => $respuesta,
            ])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

        $this->assertSame($respuesta, $orden->fresh()->trabajo_realizado);
    }

    public function test_reparacion_conserva_trabajo_historico_fuera_de_lista(): void
    {
        // Órdenes viejas con texto libre: no está en la lista fija pero se conserva.
        $historico = 'se reparo con un truco especial que no esta en la lista';
        $orden = OrdenServicio::factory()->create(['trabajo_realizado' => $historico]);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertSee($historico);
    }

    public function test_guardar_reparacion_registra_arreglo_y_repuestos(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'estado' => 'recibido']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'causa_falla' => 'uso_normal',   // obligatoria al cerrar como reparado
                'trabajo_realizado' => 'Cambio de motor y correa',
                'mano_obra' => 15000,
                'fecha_aviso' => now()->toDateString(),
                'repuestos' => [
                    ['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 30000],
                    ['nombre' => 'Correa', 'cantidad' => 2, 'precio_unitario' => 5000],
                    ['nombre' => '', 'cantidad' => 1, 'precio_unitario' => 0], // fila vacia => se ignora
                ],
            ])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

        $fresh = $orden->fresh()->load('repuestos');
        $this->assertSame('reparado', $fresh->estado);
        $this->assertSame('Cambio de motor y correa', $fresh->trabajo_realizado);
        $this->assertSame(15000, $fresh->mano_obra);
        $this->assertCount(2, $fresh->repuestos);                 // la vacia no se guarda
        $this->assertSame(55000, $fresh->costo_total);            // 30000 + (2*5000) + 15000
        $this->assertDatabaseHas('orden_servicio_repuestos', ['orden_servicio_id' => $orden->id, 'nombre' => 'Motor']);
    }

    public function test_guardar_reparacion_aplica_descuento_con_motivo(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'estado' => 'recibido']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'causa_falla' => 'uso_normal',
                'mano_obra' => 10000,
                'descuento_pct' => 20,
                'descuento_motivo' => 'cliente_grande',
                'repuestos' => [],
            ])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

        $fresh = $orden->fresh();
        $this->assertSame(20, $fresh->descuento_pct);
        $this->assertSame('cliente_grande', $fresh->descuento_motivo);
        $this->assertSame(2000, $fresh->descuento_monto);   // 20% de 10000
        $this->assertSame(8000, $fresh->costo_total);        // 10000 - 2000
    }

    public function test_descuento_exige_motivo(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'estado' => 'recibido']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'en_revision',   // no exige causa_falla; aisla el error del motivo
                'mano_obra' => 10000,
                'descuento_pct' => 15,       // con descuento pero SIN motivo
                'repuestos' => [],
            ])
            ->assertSessionHasErrors('descuento_motivo');
    }

    public function test_guardar_reparacion_registra_la_causa_de_falla(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'causa_falla' => null]);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'causa_falla' => 'mal_uso',
            ])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

        $this->assertSame('mal_uso', $orden->fresh()->causa_falla);
    }

    public function test_guardar_reparacion_rechaza_causa_de_falla_invalida(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'causa_falla' => 'inventada',
            ])
            ->assertSessionHasErrors('causa_falla');
    }

    public function test_guardar_reparacion_permite_causa_vacia_en_estado_intermedio(): void
    {
        // En estados intermedios (no terminales) la causa sigue siendo opcional:
        // "Sin determinar" (opcion vacia) -> null, sin error.
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'causa_falla' => 'mal_uso']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'en_revision',
                'causa_falla' => '',
            ])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

        $this->assertNull($orden->fresh()->causa_falla);
    }

    public function test_reparado_exige_diagnostico_final(): void
    {
        // Toda maquina cerrada como "reparado" debe llevar la causa de la falla.
        // estado fijo != 'reparado': la factory lo asigna ALEATORIO y este test
        // assertNotSame('reparado', ...) seria flaky si el azar cae en 'reparado' (I-06).
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'estado' => 'en_revision']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'causa_falla' => '',   // Sin determinar -> null -> rechazado
            ])
            ->assertSessionHasErrors('causa_falla');

        $this->assertNotSame('reparado', $orden->fresh()->estado);
    }

    public function test_sin_solucion_exige_diagnostico_final(): void
    {
        // "Sin solucion" tambien exige diagnostico (por que no se pudo reparar).
        // estado fijo (mismo flaky latente que el test hermano de arriba, I-06).
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'estado' => 'en_revision']);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'sin_solucion',
            ])
            ->assertSessionHasErrors('causa_falla');
    }

    public function test_guardar_reparacion_reemplaza_repuestos_anteriores(): void
    {
        $orden = OrdenServicio::factory()->create(['facturacion' => 'reparacion']);
        $orden->repuestos()->create(['nombre' => 'Viejo', 'cantidad' => 1, 'precio_unitario' => 1000]);

        $this->actingAs($this->admin())
            ->put(route('admin.servicio-tecnico.reparacion.guardar', $orden), [
                'estado' => 'reparado',
                'causa_falla' => 'uso_normal',
                'repuestos' => [
                    ['nombre' => 'Nuevo', 'cantidad' => 1, 'precio_unitario' => 2000],
                ],
            ])
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

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
                'causa_falla' => 'uso_normal',   // para llegar a la validación de repuestos
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

    public function test_index_muestra_recibido_por(): void
    {
        OrdenServicio::factory()->create(['cliente_nombre' => 'Epsilon SpA', 'recibida_por' => 'JefeBodega Test']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico')
            ->assertOk()->assertSee('Recibido por JefeBodega Test');
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

    public function test_index_busca_por_folio(): void
    {
        // El folio ahora es el codigo unico impredecible; buscar por ese codigo lo encuentra.
        $orden = OrdenServicio::factory()->create([
            'cliente_nombre' => 'Cliente Folio', 'cliente_rut' => null, 'numero_serie' => 'AAA', 'modelo' => null,
        ]);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?q='.$orden->codigo)
            ->assertOk()->assertSee('Cliente Folio');
    }

    public function test_cada_orden_recibe_un_codigo_unico_impredecible(): void
    {
        $a = OrdenServicio::factory()->create();
        $b = OrdenServicio::factory()->create();

        $this->assertNotEmpty($a->codigo);
        $this->assertNotSame($a->codigo, $b->codigo);
        $this->assertStringStartsWith('ST-', $a->codigo);
        // El folio visible ES el codigo (no el correlativo #id).
        $this->assertSame($a->codigo, $a->folio);
    }

    public function test_form_muestra_ayuda_del_numero_de_serie(): void
    {
        // El ícono "Ver ejemplo" y el modal de ayuda del N° de serie (dispensadores).
        $this->actingAs($this->admin())->get(route('admin.servicio-tecnico.create'))
            ->assertOk()
            ->assertSee('Ver ejemplo')
            ->assertSee('¿Dónde está el N° de serie?');
    }

    /**
     * El historial es COMPARTIDO por las 3 sucursales, pero se puede filtrar por
     * la sucursal de recepcion (donde se ingreso el equipo). La reparacion siempre
     * es en Mirador (casa matriz); Coquimbo y Abate Molina solo reciben.
     */
    public function test_index_filters_by_sucursal_de_recepcion(): void
    {
        $mirador = Sucursal::factory()->create(['nombre' => 'Mirador', 'es_central' => true]);
        $coquimbo = Sucursal::factory()->create(['nombre' => 'Coquimbo', 'es_central' => false]);
        OrdenServicio::factory()->create(['cliente_nombre' => 'Cliente Uno', 'sucursal_id' => $mirador->id]);
        OrdenServicio::factory()->create(['cliente_nombre' => 'Cliente Dos', 'sucursal_id' => $coquimbo->id]);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?sucursal_id='.$coquimbo->id)
            ->assertOk()->assertSee('Cliente Dos')->assertDontSee('Cliente Uno');
    }

    public function test_index_filtra_por_anio_y_mes(): void
    {
        OrdenServicio::factory()->create(['cliente_nombre' => 'Cliente Marzo', 'fecha_ingreso' => '2025-03-10']);
        OrdenServicio::factory()->create(['cliente_nombre' => 'Cliente Enero', 'fecha_ingreso' => '2026-01-05']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?anio=2025&mes=3')
            ->assertOk()->assertSee('Cliente Marzo')->assertDontSee('Cliente Enero');

        // Solo el año (sin mes) = el año completo.
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?anio=2026')
            ->assertOk()->assertSee('Cliente Enero')->assertDontSee('Cliente Marzo');
    }

    public function test_index_muestra_cards_de_anios_y_meses(): void
    {
        OrdenServicio::factory()->create(['fecha_ingreso' => '2025-03-10']);
        OrdenServicio::factory()->create(['fecha_ingreso' => '2025-03-20']);
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-01-05']);

        // Sin período: cards de años con sus conteos.
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico')
            ->assertOk()
            ->assertSee('Historial')
            ->assertSee('2 órdenes')   // card 2025
            ->assertSee('1 orden');    // card 2026

        // Dentro de un año: cards de los 12 meses (con y sin órdenes) + volver.
        $marzo = ucfirst(Carbon::create(2025, 3, 1)->translatedFormat('F'));
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?anio=2025')
            ->assertOk()
            ->assertSee('Todos los años')
            ->assertSee($marzo)
            ->assertSee('Sin órdenes');
    }

    public function test_index_muestra_separadores_de_mes_en_la_lista(): void
    {
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-03-10']);
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-01-05']);

        $sep = fn (int $m) => ucfirst(Carbon::create(2026, $m, 1)->translatedFormat('F Y'));

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico')
            ->assertOk()->assertSee($sep(3))->assertSee($sep(1));
    }

    public function test_index_rechaza_periodo_invalido(): void
    {
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?mes=13')
            ->assertSessionHasErrors('mes');

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico?anio=1999')
            ->assertSessionHasErrors('anio');
    }

    public function test_show_indica_reparacion_en_matriz_si_recepcion_no_central(): void
    {
        Sucursal::factory()->create(['nombre' => 'Mirador', 'es_central' => true]);
        $coquimbo = Sucursal::factory()->create(['nombre' => 'Coquimbo', 'es_central' => false]);
        $orden = OrdenServicio::factory()->create(['sucursal_id' => $coquimbo->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertSee('Coquimbo')
            ->assertSee('Se repara en Mirador');
    }

    public function test_show_no_deriva_si_recepcion_es_la_matriz(): void
    {
        $mirador = Sucursal::factory()->create(['nombre' => 'Mirador', 'es_central' => true]);
        $orden = OrdenServicio::factory()->create(['sucursal_id' => $mirador->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.show', $orden))
            ->assertOk()
            ->assertDontSee('Se repara en');
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

    /** El buscador de repuestos incluye el catalogo (por SKU) con precio con IVA. */
    public function test_buscar_repuesto_incluye_catalogo_con_precio(): void
    {
        $producto = Producto::factory()->create(['sku' => 'REP-001', 'nombre' => 'Caldera X']);
        Precio::factory()->create([
            'producto_id' => $producto->id,
            'precio_con_iva' => 4990,
        ]);

        // Buscando por el codigo (SKU) trae el nombre y el precio con IVA.
        $this->actingAs($this->admin())
            ->getJson('/admin/servicio-tecnico/buscar-repuesto?q=REP-001')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'REP-001', 'nombre' => 'Caldera X', 'precio' => 4990]);
    }

    public function test_reparacion_pasa_el_valor_hora_de_servicio(): void
    {
        // El producto SKU 9771001 (config) con precio con IVA es el valor hora.
        $hora = Producto::factory()->create(['sku' => '9771001', 'nombre' => 'Hora servicio técnico']);
        Precio::factory()->create(['producto_id' => $hora->id, 'precio_con_iva' => 4500]);

        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertViewHas('precioHoraServicio', 4500)
            ->assertSee('Horas de servicio técnico');
    }

    public function test_reparacion_sin_producto_hora_deja_mano_de_obra_manual(): void
    {
        // Sin el SKU de la hora, no hay valor hora (mano de obra manual).
        $orden = OrdenServicio::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertViewHas('precioHoraServicio', null)
            ->assertDontSee('Horas de servicio técnico');
    }

    /** Las fotos del equipo se ven tanto al EDITAR como en el DETALLE (staff). */
    public function test_edit_y_show_muestran_las_fotos_del_equipo(): void
    {
        $orden = OrdenServicio::factory()->create();
        $orden->fotos()->create(['ruta' => 'ordenes-servicio/fotos/'.$orden->id.'/abc.jpg']);

        foreach (['edit', 'show'] as $accion) {
            $this->actingAs($this->admin())
                ->get(route("admin.servicio-tecnico.{$accion}", $orden))
                ->assertOk()
                ->assertSee('Fotos del equipo (recepción)', false)
                ->assertSee(route('admin.servicio-tecnico.foto', $orden->fotos->first()), false);
        }
    }
}
