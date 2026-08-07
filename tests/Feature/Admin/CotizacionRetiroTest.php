<?php

namespace Tests\Feature\Admin;

use App\Mail\RetiroSinReparacion;
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
 * «Pase a retirar su equipo» (dueño 06-08): cuando el cliente NO acepta la
 * cotización, alguien con 'autorizar reparacion' le avisa por correo que puede
 * retirar su máquina sin reparar. Un solo aviso por cotización, solo si el
 * rechazo sigue siendo la última palabra, y con campanita interna al salir.
 */
class CotizacionRetiroTest extends TestCase
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

    /** Cotización rechazada por el cliente, lista para avisar el retiro. */
    private function rechazada(array $overrides = []): OrdenServicioCotizacion
    {
        $orden = OrdenServicio::factory()->create([
            'estado' => 'cotizacion',
            'facturacion' => 'reparacion',
            'cliente_email' => 'cliente@example.com',
            'mano_obra' => 10000,
        ]);

        $c = OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $this->tecnico());
        $c->update(array_merge([
            'estado' => 'rechazada',
            'respondida_at' => now(),
            'respuesta_motivo' => 'Muy caro',
        ], $overrides));

        return $c->fresh();
    }

    private function avisar(OrdenServicioCotizacion $c, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->tecnico())
            ->post(route('admin.servicio-tecnico.cotizacion.avisar-retiro', [$c->orden_servicio_id, $c->id]));
    }

    // --- Acceso ---

    public function test_sin_permiso_autorizar_no_puede_avisar(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->avisar($this->rechazada(), $member)->assertForbidden();
        Mail::assertNotSent(RetiroSinReparacion::class);
    }

    // --- Aviso ---

    public function test_avisa_por_correo_estampa_quien_y_suena_la_campanita(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $tecnico = $this->tecnico();
        $c = $this->rechazada();

        $this->avisar($c, $tecnico)->assertRedirect();

        Mail::assertSent(RetiroSinReparacion::class, fn ($m) => $m->hasTo('cliente@example.com'));

        $c->refresh();
        $this->assertNotNull($c->retiro_avisado_at);
        $this->assertSame($tecnico->id, $c->retiro_avisado_por);

        // Campanita interna: el equipo sabe que al cliente ya se le avisó.
        $notif = Notificacion::where('user_id', $jefe->id)
            ->where('evento', 'cotizacion.retiro_avisado')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();
        $this->assertNotNull($notif, 'Falta la campanita del retiro avisado.');
        $this->assertStringContainsString($tecnico->name, $notif->cuerpo);
    }

    public function test_no_avisa_dos_veces(): void
    {
        $c = $this->rechazada(['retiro_avisado_at' => now(), 'retiro_avisado_por' => $this->tecnico()->id]);

        $this->avisar($c)->assertRedirect();

        Mail::assertNotSent(RetiroSinReparacion::class);
        $this->assertSame(0, Notificacion::where('evento', 'cotizacion.retiro_avisado')->count());
    }

    public function test_solo_aplica_a_cotizaciones_rechazadas(): void
    {
        $c = $this->rechazada(['estado' => 'aceptada']);

        $this->avisar($c)->assertRedirect();

        Mail::assertNotSent(RetiroSinReparacion::class);
        $this->assertNull($c->fresh()->retiro_avisado_at);
    }

    public function test_no_avisa_si_hay_una_cotizacion_mas_reciente(): void
    {
        // El rechazo dejó de ser la última palabra: se re-cotizó después.
        $c = $this->rechazada();
        OrdenServicioCotizacion::crearDesde($c->orden->load('repuestos'), $this->tecnico());

        $this->avisar($c)->assertRedirect();

        Mail::assertNotSent(RetiroSinReparacion::class);
        $this->assertNull($c->fresh()->retiro_avisado_at);
    }

    public function test_si_el_correo_falla_no_se_estampa_nada(): void
    {
        $c = $this->rechazada();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->avisar($c)->assertRedirect();

        $c->refresh();
        $this->assertNull($c->retiro_avisado_at); // podrá reintentarse
        $this->assertSame(0, Notificacion::where('evento', 'cotizacion.retiro_avisado')->count());
    }

    public function test_la_ficha_muestra_el_boton_solo_con_la_ultima_rechazada(): void
    {
        $tecnico = $this->tecnico();
        $c = $this->rechazada();

        // Última rechazada y sin aviso → botón visible.
        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.show', $c->orden_servicio_id))
            ->assertOk()
            ->assertSee('Avisar: pasar a retirar')
            ->assertSee('Muy caro'); // el «¿por qué?» del cliente, a la vista

        // Ya avisado → constancia en vez de botón.
        $c->update(['retiro_avisado_at' => now(), 'retiro_avisado_por' => $tecnico->id]);
        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.show', $c->orden_servicio_id))
            ->assertOk()
            ->assertDontSee('Avisar: pasar a retirar')
            ->assertSee('Retiro avisado');
    }
}
