<?php

namespace Tests\Feature\Admin;

use App\Mail\CotizacionCliente;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Envío de la cotización al cliente desde la pantalla de reparación (P-M12-02):
 * snapshot congelado, carta por correo (acción secundaria), reemplazo de la
 * anterior al re-enviar y aviso interno a los roles del taller/ventas.
 */
class CotizacionEnviarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Mail::fake();
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico');
    }

    /** Orden cotizable: reparación (se cobra), en etapa cotización, con email y costo. */
    private function ordenCotizable(array $overrides = []): OrdenServicio
    {
        $orden = OrdenServicio::factory()->create(array_merge([
            'estado' => 'cotizacion',
            'facturacion' => 'reparacion',
            'cliente_email' => 'cliente@example.com',
            'mano_obra' => 10000,
            'causa_falla' => 'Filtración interna',
            'trabajo_realizado' => 'Cambio de caldera — funciona normal',
        ], $overrides));
        $orden->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 4000]);

        return $orden;
    }

    private function enviar(OrdenServicio $orden, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $orden));
    }

    // --- Acceso ---

    public function test_vendedor_sin_manage_no_puede_enviar(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->actingAs($vendedor)
            ->post(route('admin.servicio-tecnico.cotizacion.enviar', $this->ordenCotizable()))
            ->assertForbidden();
    }

    // --- Envío ---

    public function test_envia_cotizacion_con_snapshot_y_correo(): void
    {
        $orden = $this->ordenCotizable();

        $this->enviar($orden)->assertRedirect();

        $c = OrdenServicioCotizacion::first();
        $this->assertSame('enviada', $c->estado);
        $this->assertSame('cliente@example.com', $c->cliente_email);
        $this->assertSame(14000, $c->costo_total);           // 4000 repuesto + 10000 mano de obra
        $this->assertSame('Filtración interna', $c->causa_falla);
        $this->assertNotNull($c->correo_enviado_at);
        $this->assertNotNull($c->vence_at);                   // vigencia por Configuracion
        $this->assertSame(64, strlen($c->token));
        $this->assertSame([['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 4000, 'subtotal' => 4000]], $c->repuestos);

        Mail::assertSent(CotizacionCliente::class, fn ($m) => $m->hasTo('cliente@example.com'));
    }

    public function test_snapshot_queda_congelado_si_editan_la_orden(): void
    {
        $orden = $this->ordenCotizable();
        $this->enviar($orden);

        // El técnico renegocia DESPUÉS: el snapshot enviado no cambia.
        $orden->update(['mano_obra' => 99999]);

        $this->assertSame(14000, OrdenServicioCotizacion::first()->costo_total);
    }

    public function test_reenvio_reemplaza_la_anterior_pero_no_las_respondidas(): void
    {
        $orden = $this->ordenCotizable();
        $this->enviar($orden);
        OrdenServicioCotizacion::first()->update(['estado' => 'aceptada', 'respondida_at' => now()]);

        $this->enviar($orden); // 2ª (queda enviada)
        $this->enviar($orden); // 3ª: reemplaza a la 2ª, no toca la aceptada

        $estados = OrdenServicioCotizacion::orderBy('id')->pluck('estado')->all();
        $this->assertSame(['aceptada', 'reemplazada', 'enviada'], $estados);
    }

    // --- Bloqueos (validación server-side del botón) ---

    public function test_no_envia_sin_email_ni_costo_ni_etapa_ni_garantia(): void
    {
        // Sin email.
        $this->enviar($this->ordenCotizable(['cliente_email' => null]));
        // Etapa distinta de cotización.
        $this->enviar($this->ordenCotizable(['estado' => 'en_revision']));
        // Garantía vigente (no se cobra).
        $this->enviar($this->ordenCotizable([
            'facturacion' => 'garantia', 'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => '123', 'garantia_doc_fecha' => now()->subMonth()->toDateString(),
        ]));
        // Costo $0.
        $sinCosto = OrdenServicio::factory()->create([
            'estado' => 'cotizacion', 'facturacion' => 'reparacion',
            'cliente_email' => 'x@example.com', 'mano_obra' => 0,
        ]);
        $this->enviar($sinCosto);

        $this->assertSame(0, OrdenServicioCotizacion::count());
        Mail::assertNothingSent();
    }

    // --- Fallo SMTP ---

    public function test_si_el_correo_falla_la_cotizacion_queda_registrada(): void
    {
        $orden = $this->ordenCotizable();

        // Sin ->once(): el aviso interno también pasa por Mail (cola sync en tests).
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->enviar($orden)->assertRedirect();

        $c = OrdenServicioCotizacion::first();
        $this->assertNotNull($c);                    // el registro NO se pierde
        $this->assertNull($c->correo_enviado_at);    // pero el correo no salió
    }

    public function test_reintentar_reenvia_el_mismo_snapshot_sin_fila_nueva(): void
    {
        $orden = $this->ordenCotizable();
        // Cotización cuyo correo falló (correo_enviado_at null, aún vigente).
        $c = OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $this->tecnico());

        $this->actingAs($this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.reintentar', [$orden, $c->id]))
            ->assertRedirect();

        Mail::assertSent(CotizacionCliente::class, fn ($m) => $m->hasTo('cliente@example.com'));
        $this->assertNotNull($c->fresh()->correo_enviado_at);
        $this->assertSame(1, OrdenServicioCotizacion::count()); // sin fila nueva
    }

    // --- Aviso interno ---

    public function test_avisa_a_los_roles_internos_sin_duplicar(): void
    {
        $tecnico = $this->tecnico();
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->enviar($this->ordenCotizable(), $tecnico);

        // Campanita (canal database) para cada rol avisado.
        foreach ([$tecnico, $jefe, $vendedor] as $u) {
            $this->assertSame(
                1,
                Notificacion::where('user_id', $u->id)->where('evento', 'cotizacion.enviada')
                    ->where('canal', Notificacion::CANAL_DATABASE)->count(),
                "Falta la campanita de {$u->name}"
            );
        }
    }
}
