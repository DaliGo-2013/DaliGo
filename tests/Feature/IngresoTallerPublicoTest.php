<?php

namespace Tests\Feature;

use App\Mail\IngresoTallerRecibido;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ingreso PUBLICO a servicio tecnico por QR (P-M12-01, piloto).
 * Cubre: link firmado, creacion sin confirmar/sin correo, honeypot, validacion,
 * y la confirmacion del encargado que dispara el correo (con lock anti doble-envio).
 */
class IngresoTallerPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Las fotos van al disco privado 'local'; lo falseamos para no escribir en disco real.
        Storage::fake('local');
    }

    /** @return array<int, UploadedFile> 2 fotos de prueba (JPEG reales via GD). */
    private function fotos(): array
    {
        return [
            UploadedFile::fake()->image('foto1.jpg', 800, 600),
            UploadedFile::fake()->image('foto2.jpg', 800, 600),
        ];
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    private function sucursal(): Sucursal
    {
        return Sucursal::factory()->create(['activa' => true, 'nombre' => 'Mirador']);
    }

    private function linkCreate(Sucursal $sucursal): string
    {
        return URL::signedRoute('ingreso-taller.create', ['sucursal' => $sucursal->id]);
    }

    private function payload(Sucursal $sucursal, array $overrides = []): array
    {
        return array_merge([
            'sucursal_id' => $sucursal->id,
            'cliente_nombre' => 'Ana Cliente',
            'cliente_email' => 'ana@correo.cl',
            'cliente_telefono' => '+56 9 8888 7777',
            'cliente_rut' => '12.345.678-5',
            'tipo_equipo' => 'dispensador',
            'numero_serie' => 'SN-9090',
            'facturacion' => 'reparacion',
            'falla_reportada' => 'Gotea por abajo',
            'fotos' => $this->fotos(),
        ], $overrides);
    }

    // --- Acceso al formulario (link del QR) ---

    public function test_el_link_firmado_del_qr_muestra_el_formulario(): void
    {
        $sucursal = $this->sucursal();

        $this->get($this->linkCreate($sucursal))
            ->assertOk()
            ->assertSee('Ingreso a servicio técnico')
            ->assertSee('Mirador')
            ->assertSee('Ver ejemplo');   // ícono de ayuda del N° de serie
    }

    public function test_sin_firma_valida_el_formulario_es_rechazado(): void
    {
        $sucursal = $this->sucursal();

        // Sin firma (URL cruda) -> 403 del middleware 'signed'.
        $this->get(route('ingreso-taller.create', ['sucursal' => $sucursal->id]))
            ->assertForbidden();
    }

    // --- Envio (crea la orden por QR) ---

    public function test_envio_publico_crea_orden_qr_sin_confirmar_y_sin_correo(): void
    {
        Mail::fake();
        $sucursal = $this->sucursal();

        $response = $this->post(route('ingreso-taller.store'), $this->payload($sucursal));

        $response->assertRedirectContains('/ingreso-taller/listo');

        $orden = OrdenServicio::first();
        $this->assertNotNull($orden);
        $this->assertSame('qr', $orden->fuente);
        $this->assertSame('recibido', $orden->estado);
        $this->assertNull($orden->confirmada_at);
        $this->assertSame('ana@correo.cl', $orden->cliente_email);
        $this->assertNotNull($orden->fecha_entrega);         // la calcula el servidor
        $this->assertTrue($orden->por_confirmar);

        // El correo NO sale en el envio: sale cuando el encargado confirma.
        Mail::assertNothingSent();
    }

    public function test_formulario_ofrece_elegir_modo_de_ingreso(): void
    {
        $sucursal = $this->sucursal();

        // Paso previo: elegir cómo ingresar (código de barras "Pronto" |
        // por unidad | por cantidad).
        $this->get($this->linkCreate($sucursal))
            ->assertOk()
            ->assertSee('¿Cómo desea ingresar su producto?')
            ->assertSee('Con código de barras')
            ->assertSee('Pronto')
            ->assertSee('Ingreso por unidad')
            ->assertSee('Ingreso por cantidad');
    }

    public function test_formulario_muestra_codigo_y_fecha_de_hoy(): void
    {
        $sucursal = $this->sucursal();

        $this->get($this->linkCreate($sucursal))
            ->assertOk()
            ->assertSee('Código del equipo')
            ->assertSee('Fecha de ingreso')
            ->assertSee(now()->format('Y-m-d'));   // fecha de hoy prellenada
    }

    public function test_buscar_producto_publico_devuelve_coincidencias(): void
    {
        $producto = Producto::factory()->create([
            'sku' => 'LB-07', 'nombre' => 'Dispensador Silver Black',
            'categoria' => 'AGUA DISP. SOBREMESA COMPRESOR',
        ]);

        // Sin login (es público): matchea por SKU y por nombre.
        $this->getJson(route('ingreso-taller.buscar-producto', ['q' => 'LB-07']))
            ->assertOk()
            ->assertJsonFragment(['id' => $producto->id]);

        $this->getJson(route('ingreso-taller.buscar-producto', ['q' => 'Silver']))
            ->assertOk()
            ->assertJsonFragment(['sku' => 'LB-07']);
    }

    public function test_buscar_producto_publico_solo_muestra_equipos(): void
    {
        // Equipo (categoría de taller) vs accesorio (otra categoría). El cliente
        // solo debe ver el equipo, aunque ambos matcheen la búsqueda por nombre.
        $equipo = Producto::factory()->create([
            'sku' => '1040001', 'nombre' => 'Dispensador Rosa LB-16',
            'categoria' => 'AGUA DISP. PEDESTAL VENTILADOR',
        ]);
        $accesorio = Producto::factory()->create([
            'sku' => '1020104', 'nombre' => 'Soporte Rosa Nacional',
            'categoria' => 'Accesorios',
        ]);

        $this->getJson(route('ingreso-taller.buscar-producto', ['q' => 'Rosa']))
            ->assertOk()
            ->assertJsonFragment(['id' => $equipo->id])
            ->assertJsonMissing(['id' => $accesorio->id]);
    }

    public function test_buscar_producto_publico_filtra_categoria_sin_importar_mayusculas(): void
    {
        // La categoría que manda Bsale puede venir en otra capitalización que la
        // configurada; el filtro compara en minúsculas.
        $producto = Producto::factory()->create([
            'sku' => 'BX-500', 'nombre' => 'Bomba de agua USB portátil',
            'categoria' => 'AGUA BOMBA USB',
        ]);

        $this->getJson(route('ingreso-taller.buscar-producto', ['q' => 'BX-500']))
            ->assertOk()
            ->assertJsonFragment(['id' => $producto->id]);
    }

    public function test_buscar_producto_publico_excluye_sin_categoria(): void
    {
        // Un producto sin categoría (no calza con ningún equipo) no se sugiere.
        Producto::factory()->create(['sku' => 'SC-01', 'nombre' => 'Cosa sin categoria', 'categoria' => null]);

        $this->getJson(route('ingreso-taller.buscar-producto', ['q' => 'SC-01']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_buscar_producto_publico_muestra_dispensadores(): void
    {
        // Categoría real de Bsale (mayúsculas, con punto): debe calzar con el
        // config vía el match tolerante y mostrar el dispensador en el buscador.
        $disp = Producto::factory()->create([
            'sku' => '1040001', 'nombre' => 'DISP. LB-16 L/D BLUE/SILVER',
            'categoria' => 'AGUA DISP. SOBREMESA COMPRESOR',
        ]);

        $this->getJson(route('ingreso-taller.buscar-producto', ['q' => 'LB-16']))
            ->assertOk()
            ->assertJsonFragment(['id' => $disp->id]);
    }

    public function test_buscar_producto_publico_exige_dos_caracteres(): void
    {
        Producto::factory()->create(['sku' => 'X1']);

        $this->getJson(route('ingreso-taller.buscar-producto', ['q' => 'X']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_envio_publico_guarda_producto_id(): void
    {
        Mail::fake();
        $sucursal = $this->sucursal();
        $producto = Producto::factory()->create();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['producto_id' => $producto->id]))
            ->assertRedirectContains('/ingreso-taller/listo');

        $this->assertDatabaseHas('ordenes_servicio', [
            'producto_id' => $producto->id,
            'fuente' => 'qr',
        ]);
    }

    public function test_producto_inexistente_es_rechazado(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['producto_id' => 999999]))
            ->assertSessionHasErrors('producto_id');
    }

    public function test_honeypot_lleno_no_crea_orden(): void
    {
        Mail::fake();
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['sitio_web' => 'http://spam.example']))
            ->assertStatus(302);

        $this->assertDatabaseCount('ordenes_servicio', 0);
        Mail::assertNothingSent();
    }

    public function test_envio_publico_valida_obligatorios(): void
    {
        // numero_serie NO va aqui: es condicional al tipo (ver tests dedicados).
        $this->post(route('ingreso-taller.store'), [])
            ->assertSessionHasErrors([
                'sucursal_id', 'cliente_nombre', 'cliente_email', 'cliente_telefono',
                'cliente_rut', 'tipo_equipo', 'facturacion', 'falla_reportada', 'fotos',
            ]);
    }

    /** Dispensador/lavadora: el N° de serie sigue siendo obligatorio en lo publico. */
    public function test_envio_publico_exige_serie_para_dispensador(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, [
            'tipo_equipo' => 'dispensador',
            'numero_serie' => '',
        ]))->assertSessionHasErrors('numero_serie');
    }

    /** Bombas/herramientas: el N° de serie es OPCIONAL tambien en el flujo publico. */
    public function test_envio_publico_serie_opcional_para_herramienta(): void
    {
        Mail::fake();
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, [
            'tipo_equipo' => 'herramienta',
            'numero_serie' => '',
        ]))->assertRedirectContains('/ingreso-taller/listo');

        $this->assertDatabaseHas('ordenes_servicio', [
            'tipo_equipo' => 'herramienta',
            'numero_serie' => null,
            'fuente' => 'qr',
        ]);
    }

    public function test_rut_obligatorio_y_dv_invalido_se_rechaza(): void
    {
        $sucursal = $this->sucursal();

        // Sin RUT: ahora es obligatorio en el flujo publico -> error.
        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['cliente_rut' => '']))
            ->assertSessionHasErrors('cliente_rut');

        // RUT con DV incorrecto: se rechaza.
        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['cliente_rut' => '12.345.678-9']))
            ->assertSessionHasErrors('cliente_rut');
    }

    /** Telefono ahora obligatorio en el flujo publico. */
    public function test_telefono_obligatorio(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['cliente_telefono' => '']))
            ->assertSessionHasErrors('cliente_telefono');
    }

    /** La condicion (reparacion) elegida por el cliente se guarda. */
    public function test_envio_publico_guarda_condicion(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['facturacion' => 'reparacion']))
            ->assertRedirectContains('/ingreso-taller/listo');

        $this->assertDatabaseHas('ordenes_servicio', [
            'facturacion' => 'reparacion',
            'fuente' => 'qr',
        ]);
    }

    /** Si el cliente marca Garantia, el documento de compra es obligatorio. */
    public function test_garantia_exige_documento_de_compra(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['facturacion' => 'garantia']))
            ->assertSessionHasErrors(['garantia_doc_tipo', 'garantia_doc_numero', 'garantia_doc_fecha']);
    }

    /** Garantia con documento completo se guarda. */
    public function test_garantia_con_documento_se_guarda(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, [
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => 'B-12345',
            'garantia_doc_fecha' => now()->subMonth()->toDateString(),
        ]))->assertRedirectContains('/ingreso-taller/listo');

        $this->assertDatabaseHas('ordenes_servicio', [
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => 'B-12345',
            'fuente' => 'qr',
        ]);
    }

    public function test_sucursal_inactiva_es_rechazada(): void
    {
        $inactiva = Sucursal::factory()->create(['activa' => false]);

        $this->post(route('ingreso-taller.store'), $this->payload($inactiva))
            ->assertSessionHasErrors('sucursal_id');
    }

    public function test_gracias_sin_firma_es_rechazada(): void
    {
        $orden = OrdenServicio::factory()->create(['fuente' => 'qr', 'confirmada_at' => null]);

        $this->get(route('ingreso-taller.gracias', ['orden' => $orden->id]))
            ->assertForbidden();
    }

    public function test_gracias_firmada_muestra_el_folio(): void
    {
        $orden = OrdenServicio::factory()->create([
            'fuente' => 'qr',
            'confirmada_at' => null,
            'cliente_email' => 'ana@correo.cl',
        ]);

        $this->get(URL::signedRoute('ingreso-taller.gracias', ['orden' => $orden->id]))
            ->assertOk()
            ->assertSee($orden->folio)
            ->assertSee('ana@correo.cl')
            ->assertSee('Volver al inicio'); // botón de regreso a la pantalla del QR
    }

    // --- Confirmacion del encargado ---

    public function test_encargado_confirma_setea_fecha_y_manda_correo(): void
    {
        Mail::fake();
        $orden = OrdenServicio::factory()->create([
            'fuente' => 'qr',
            'confirmada_at' => null,
            'cliente_email' => 'ana@correo.cl',
            'estado' => 'recibido',
        ]);

        $encargado = tap($this->admin())->update(['name' => 'Fernando St']);

        $this->actingAs($encargado)
            ->post(route('admin.servicio-tecnico.confirmar', $orden))
            ->assertRedirect();

        $fresh = $orden->fresh();
        $this->assertNotNull($fresh->confirmada_at);
        $this->assertSame('Fernando St', $fresh->recibida_por);   // queda quién recibió
        Mail::assertSent(IngresoTallerRecibido::class, fn ($mail) => $mail->hasTo('ana@correo.cl'));
    }

    public function test_confirmar_dos_veces_no_reenvia_correo(): void
    {
        Mail::fake();
        $orden = OrdenServicio::factory()->create([
            'fuente' => 'qr',
            'confirmada_at' => null,
            'cliente_email' => 'ana@correo.cl',
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.servicio-tecnico.confirmar', $orden));
        $this->actingAs($admin)->post(route('admin.servicio-tecnico.confirmar', $orden));

        // El lock + chequeo de confirmada_at evitan un segundo correo.
        Mail::assertSent(IngresoTallerRecibido::class, 1);
    }

    public function test_correo_renderiza_folio_y_detalle(): void
    {
        $sucursal = $this->sucursal();
        $orden = OrdenServicio::factory()->create([
            'fuente' => 'qr',
            'sucursal_id' => $sucursal->id,
            'cliente_nombre' => 'Ana Cliente',
            'numero_serie' => 'SN-9090',
            'falla_reportada' => 'Gotea por abajo',
        ]);

        $html = (new IngresoTallerRecibido($orden))->render();

        $this->assertStringContainsString($orden->folio, $html);
        $this->assertStringContainsString('Ana Cliente', $html);
        $this->assertStringContainsString('SN-9090', $html);
        $this->assertStringContainsString('Mirador', $html);
    }

    public function test_confirmar_sin_permiso_es_forbidden(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');
        $orden = OrdenServicio::factory()->create(['fuente' => 'qr', 'confirmada_at' => null]);

        $this->actingAs($member)
            ->post(route('admin.servicio-tecnico.confirmar', $orden))
            ->assertForbidden();
    }

    public function test_jefe_bodega_autoriza_la_recepcion_y_manda_correo(): void
    {
        // El jefe de bodega AUTORIZA la recepcion (permiso 'confirmar servicio
        // tecnico') aunque NO tenga 'manage'. Al confirmar, sale el correo.
        Mail::fake();
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $orden = OrdenServicio::factory()->create([
            'fuente' => 'qr',
            'confirmada_at' => null,
            'cliente_email' => 'ana@correo.cl',
        ]);

        $this->actingAs($jefe)
            ->post(route('admin.servicio-tecnico.confirmar', $orden))
            ->assertRedirect();

        $this->assertNotNull($orden->fresh()->confirmada_at);
        Mail::assertSent(IngresoTallerRecibido::class);
    }

    public function test_vendedor_solo_lectura_no_puede_confirmar(): void
    {
        // El vendedor tiene 'view servicio tecnico' pero NO 'confirmar' -> 403.
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $orden = OrdenServicio::factory()->create(['fuente' => 'qr', 'confirmada_at' => null]);

        $this->actingAs($vendedor)
            ->post(route('admin.servicio-tecnico.confirmar', $orden))
            ->assertForbidden();
    }

    public function test_conteo_por_confirmar_devuelve_solo_qr_sin_confirmar(): void
    {
        OrdenServicio::factory()->count(2)->create(['fuente' => 'qr', 'confirmada_at' => null]);
        OrdenServicio::factory()->create(['fuente' => 'mostrador', 'confirmada_at' => null]);  // no cuenta
        OrdenServicio::factory()->create(['fuente' => 'qr', 'confirmada_at' => now()]);         // ya confirmada, no cuenta

        $this->actingAs($this->admin())
            ->getJson(route('admin.servicio-tecnico.por-confirmar.conteo'))
            ->assertOk()
            ->assertExactJson(['total' => 2]);
    }

    public function test_conteo_por_confirmar_requiere_permiso(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)
            ->getJson(route('admin.servicio-tecnico.por-confirmar.conteo'))
            ->assertForbidden();
    }

    // --- Pagina de QR (admin) ---

    public function test_pagina_qr_lista_solo_sucursales_de_servicio_tecnico(): void
    {
        config(['servicio_tecnico.sucursales_recepcion' => ['MIRADOR', 'COQUIMBO', 'ABATE-MOLINA']]);
        Sucursal::factory()->create(['codigo' => 'MIRADOR', 'nombre' => 'El Mirador', 'activa' => true]);
        Sucursal::factory()->create(['codigo' => 'BUZETA', 'nombre' => 'Buzeta', 'activa' => true]);

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.qr'))
            ->assertOk()
            ->assertSee('El Mirador')
            ->assertSee('data-qr', false)
            ->assertDontSee('Buzeta');   // Buzeta no recibe servicio técnico
    }

    // --- Portada: NO expone el ingreso a servicio técnico (por seguridad) ---

    public function test_portada_no_expone_entrada_a_servicio_tecnico(): void
    {
        // El ingreso por QR NO se anuncia en la home (se llega solo por el QR físico
        // del mostrador). La portada no debe mostrar la pregunta ni dibujar QRs.
        config(['servicio_tecnico.sucursales_recepcion' => ['MIRADOR', 'COQUIMBO', 'ABATE-MOLINA']]);
        Sucursal::factory()->create(['codigo' => 'MIRADOR', 'nombre' => 'El Mirador', 'activa' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('DaliGo')
            ->assertDontSee('¿Vas a ingresar un producto a servicio técnico?', false)
            ->assertDontSee('data-qr', false);
    }

    // --- Fotos de respaldo del estado del equipo ---

    public function test_fotos_son_obligatorias(): void
    {
        $sucursal = $this->sucursal();
        $data = $this->payload($sucursal);
        unset($data['fotos']);

        $this->post(route('ingreso-taller.store'), $data)
            ->assertSessionHasErrors('fotos');
    }

    public function test_exige_exactamente_dos_fotos(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, [
            'fotos' => [UploadedFile::fake()->image('una.jpg')],
        ]))->assertSessionHasErrors('fotos');
    }

    public function test_dos_fotos_se_guardan_comprimidas(): void
    {
        $sucursal = $this->sucursal();

        $this->post(route('ingreso-taller.store'), $this->payload($sucursal))
            ->assertRedirectContains('/ingreso-taller/listo');

        $orden = OrdenServicio::latest('id')->firstOrFail();
        $this->assertCount(2, $orden->fotos);
        foreach ($orden->fotos as $foto) {
            $this->assertStringEndsWith('.jpg', $foto->ruta);
            Storage::disk('local')->assertExists($foto->ruta);
        }
    }

    public function test_la_foto_se_sirve_solo_con_sesion(): void
    {
        $sucursal = $this->sucursal();
        $this->post(route('ingreso-taller.store'), $this->payload($sucursal));
        $foto = \App\Models\OrdenServicioFoto::firstOrFail();

        // Sin sesión → no se sirve (redirige al login).
        $this->get(route('admin.servicio-tecnico.foto', $foto))
            ->assertRedirect(route('login'));

        // Con sesión (admin) → 200 e imagen.
        $resp = $this->actingAs($this->admin())->get(route('admin.servicio-tecnico.foto', $foto));
        $resp->assertOk();
        $this->assertStringStartsWith('image/', (string) $resp->headers->get('Content-Type'));
    }
}
