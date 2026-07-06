<?php

namespace Tests\Feature;

use App\Mail\IngresoTallerRecibido;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
            'tipo_equipo' => 'dispensador',
            'numero_serie' => 'SN-9090',
            'falla_reportada' => 'Gotea por abajo',
        ], $overrides);
    }

    // --- Acceso al formulario (link del QR) ---

    public function test_el_link_firmado_del_qr_muestra_el_formulario(): void
    {
        $sucursal = $this->sucursal();

        $this->get($this->linkCreate($sucursal))
            ->assertOk()
            ->assertSee('Ingreso a servicio técnico')
            ->assertSee('Mirador');
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
        $this->post(route('ingreso-taller.store'), [])
            ->assertSessionHasErrors([
                'sucursal_id', 'cliente_nombre', 'cliente_email',
                'tipo_equipo', 'numero_serie', 'falla_reportada',
            ]);
    }

    public function test_rut_es_opcional_pero_invalido_se_rechaza(): void
    {
        $sucursal = $this->sucursal();

        // Sin RUT: pasa.
        $this->post(route('ingreso-taller.store'), $this->payload($sucursal))
            ->assertSessionHasNoErrors();

        // RUT con DV incorrecto: se rechaza.
        $this->post(route('ingreso-taller.store'), $this->payload($sucursal, ['cliente_rut' => '12.345.678-9']))
            ->assertSessionHasErrors('cliente_rut');
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
            ->assertSee('ana@correo.cl');
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

        $this->actingAs($this->admin())
            ->post(route('admin.servicio-tecnico.confirmar', $orden))
            ->assertRedirect();

        $this->assertNotNull($orden->fresh()->confirmada_at);
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

    // --- Pagina de QR (admin) ---

    public function test_pagina_qr_lista_sucursales_activas_para_el_encargado(): void
    {
        $this->sucursal();

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.qr'))
            ->assertOk()
            ->assertSee('Mirador')
            ->assertSee('data-qr', false);
    }
}
