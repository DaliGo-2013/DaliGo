<?php

namespace Tests\Feature\Admin;

use App\Mail\EquipoListoParaRetiro;
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
 * Cierre del taller (dueño 07-08): el TÉCNICO le avisa al cliente que su equipo
 * está listo y que pague en SALA DE VENTAS al retirar. El taller no coordina
 * plata; este botón es lo último que hace con la orden.
 */
class ListoParaRetiroTest extends TestCase
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

    /** Orden reparada con la cotización que el cliente aceptó. */
    private function reparada(array $overrides = []): OrdenServicio
    {
        $orden = OrdenServicio::factory()->create(array_merge([
            'estado' => 'reparado',
            'facturacion' => 'reparacion',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_email' => 'cliente@example.com',
            'mano_obra' => 10000,
            'trabajo_realizado' => 'Cambio de caldera — funciona normal',
            'causa_falla' => 'desgaste',
        ], $overrides));
        $orden->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 4000]);

        $c = OrdenServicioCotizacion::crearDesde($orden->load('repuestos'), $this->tecnico());
        $c->update(['estado' => 'aceptada', 'respondida_at' => now()]);

        return $orden->fresh();
    }

    private function avisar(OrdenServicio $orden, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->tecnico())
            ->post(route('admin.servicio-tecnico.listo-para-retiro', $orden));
    }

    // --- Acceso ---

    public function test_sin_manage_no_puede_avisar(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->avisar($this->reparada(), $member)->assertForbidden();
        Mail::assertNotSent(EquipoListoParaRetiro::class);
    }

    // --- Aviso ---

    public function test_avisa_al_cliente_estampa_quien_y_llena_la_fecha_de_aviso(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $tecnico = $this->tecnico();
        $orden = $this->reparada();

        $this->avisar($orden, $tecnico)
            ->assertRedirect(route('admin.servicio-tecnico.cotizacion', $orden));

        Mail::assertSent(EquipoListoParaRetiro::class, fn ($m) => $m->hasTo('cliente@example.com'));

        $orden->refresh();
        $this->assertNotNull($orden->listo_avisado_at);
        $this->assertSame($tecnico->id, $orden->listo_avisado_por);
        // «Fecha de aviso al cliente» del parte queda llena sola.
        $this->assertSame(now()->toDateString(), $orden->fecha_aviso->toDateString());

        // Ventas se enteran: el cliente va a llegar al mostrador a pagar.
        $notif = Notificacion::where('user_id', $vendedor->id)
            ->where('evento', 'taller.listo_para_retiro')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();
        $this->assertNotNull($notif, 'Falta la campanita de «listo para retirar».');
        $this->assertStringContainsString('sala de ventas', $notif->cuerpo);
        $this->assertStringContainsString('14.000', $notif->cuerpo); // lo que el cliente aceptó
        $this->assertStringNotContainsString('{cobro}', $notif->cuerpo);
    }

    public function test_la_carta_lleva_el_monto_que_el_cliente_acepto(): void
    {
        $orden = $this->reparada();
        // El técnico toca la orden DESPUÉS de que el cliente aceptó: se cobra lo
        // aceptado (snapshot), no lo que quedó en la orden viva.
        $orden->update(['mano_obra' => 99999]);

        $this->avisar($orden);

        Mail::assertSent(EquipoListoParaRetiro::class, fn ($m) => (int) $m->cotizacion->costo_total === 14000);
    }

    public function test_en_garantia_avisa_sin_cobro(): void
    {
        $orden = $this->reparada([
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => '123',
            'garantia_doc_fecha' => now()->subMonths(2)->toDateString(),
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $this->avisar($orden)->assertRedirect();

        Mail::assertSent(EquipoListoParaRetiro::class);
        $cuerpo = Notificacion::where('evento', 'taller.listo_para_retiro')->first()->cuerpo;
        $this->assertStringContainsString('Sin costo (garantía)', $cuerpo);
    }

    // --- Guardas ---

    public function test_exige_la_orden_reparada(): void
    {
        // «Está listo» = trabajo cerrado con su causa de la falla (eso lo garantiza
        // el parte del técnico), no una orden a medio camino.
        $orden = $this->reparada(['estado' => 'cotizacion']);

        $this->avisar($orden)
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'Reparado'));

        Mail::assertNotSent(EquipoListoParaRetiro::class);
        $this->assertNull($orden->fresh()->listo_avisado_at);
    }

    public function test_no_avisa_dos_veces(): void
    {
        $orden = $this->reparada(['listo_avisado_at' => now()]);

        $this->avisar($orden)->assertRedirect();

        Mail::assertNotSent(EquipoListoParaRetiro::class);
        $this->assertSame(0, Notificacion::where('evento', 'taller.listo_para_retiro')->count());
    }

    public function test_sin_correo_del_cliente_no_avisa(): void
    {
        // Sin correo no hay cotización posible (el snapshot lo exige), así que la
        // orden va pelada: reparada, pero sin a quién avisarle.
        $orden = OrdenServicio::factory()->create([
            'estado' => 'reparado', 'facturacion' => 'reparacion',
            'cliente_email' => null, 'trabajo_realizado' => 'Cambio de caldera', 'causa_falla' => 'desgaste',
        ]);

        $this->avisar($orden)->assertRedirect();

        Mail::assertNotSent(EquipoListoParaRetiro::class);
        $this->assertNull($orden->fresh()->listo_avisado_at);
    }

    public function test_si_el_correo_falla_no_se_estampa_nada(): void
    {
        $orden = $this->reparada();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->avisar($orden)->assertRedirect();

        $this->assertNull($orden->fresh()->listo_avisado_at); // se puede reintentar
        $this->assertSame(0, Notificacion::where('evento', 'taller.listo_para_retiro')->count());
    }

    // --- Pantalla ---

    public function test_la_pestana_cotizacion_ofrece_el_boton_y_luego_la_constancia(): void
    {
        $tecnico = $this->tecnico();
        $orden = $this->reparada();

        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertSee('Avisar que está listo para retirar')
            ->assertSee('sala de ventas');

        $this->avisar($orden, $tecnico);

        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertDontSee('Avisar que está listo para retirar')
            ->assertSee('Ya se le avisó al cliente');
    }

    public function test_antes_de_reparar_la_pantalla_explica_que_falta(): void
    {
        $orden = $this->reparada(['estado' => 'cotizacion']);

        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertDontSee('Avisar que está listo para retirar')
            ->assertSee('marca la orden como «Reparado» en Parte del técnico');
    }
}
