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

    /**
     * El `<div>` que ENVUELVE a un título en el HTML, para poder preguntarle si es
     * una tarjeta (`rounded-2xl`) o una sección separada por una línea
     * (`border-t`). Se mira la estructura porque «unificar dos cards» es un cambio
     * de marco: los textos son idénticos antes y después.
     */
    private function envoltorioDe(string $html, string $titulo): string
    {
        $pos = strpos($html, $titulo);
        $this->assertNotFalse($pos, "No está en la página: {$titulo}");
        $antes = substr($html, 0, $pos);

        return substr($antes, strrpos($antes, '<div'));
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

        // Vuelve a la pantalla donde está la tarjeta que acaba de cambiar: el parte
        // del técnico (dueño 20-08: la constancia se mudó ahí desde la cotización).
        $this->avisar($orden, $tecnico)
            ->assertRedirect(route('admin.servicio-tecnico.reparacion', $orden));

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

    /**
     * EL BOTÓN Y SU CONSTANCIA VIVEN EN EL PARTE DEL TÉCNICO (dueño 20-08-2026: los
     * sacó de la pestaña Cotización porque estaban repetidos —«ya aparece abajo en la
     * vista de parte del técnico»—). El test mira las DOS pantallas: si volvieran a
     * aparecer en la cotización, se pone rojo.
     */
    public function test_el_boton_y_la_constancia_estan_en_el_parte_y_no_en_la_cotizacion(): void
    {
        $tecnico = $this->tecnico();
        $orden = $this->reparada();

        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertSee('Avisar que está listo para retirar')
            ->assertSee('sala de ventas');

        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertDontSee('Avisar que está listo para retirar');

        $this->avisar($orden, $tecnico);

        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertDontSee('Avisar que está listo para retirar')
            ->assertSee('Ya se le avisó al cliente');

        // Y la vista previa sigue sin la constancia: es solo lo que paga el cliente.
        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertDontSee('Ya se le avisó al cliente')
            ->assertDontSee('Enviada al cliente');
    }

    /**
     * LAS DOS TARJETAS SON UNA (dueño 20-08-2026: «quiero unificar estas dos partes
     * que parecen cards»). Se verifica por ESTRUCTURA y no por texto: los textos son
     * los mismos antes y después del cambio, lo que cambia es el marco. «Listo para
     * retirar» tiene que quedar como sección de la tarjeta de la cotización enviada
     * —separada por una línea— y no abrir un marco propio.
     */
    public function test_la_constancia_y_el_retiro_van_en_una_sola_tarjeta(): void
    {
        $orden = $this->reparada();   // ya trae la cotización enviada y aceptada

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->getContent();

        $envio = strpos($html, 'Enviada al cliente');
        $retiro = strpos($html, 'Listo para retirar');
        $this->assertNotFalse($envio);
        $this->assertNotFalse($retiro);
        $this->assertGreaterThan($envio, $retiro, '«Listo para retirar» va después de la constancia del envío.');

        // Entre las dos NO se cierra ni se abre una tarjeta.
        $tramo = substr($html, $envio, $retiro - $envio);
        $this->assertStringNotContainsString('rounded-2xl', $tramo, 'Volvieron a abrir una tarjeta aparte para «Listo para retirar».');

        // Y su envoltorio es una sección con línea divisoria, no un marco.
        $envoltorio = $this->envoltorioDe($html, 'Listo para retirar');
        $this->assertStringContainsString('border-t', $envoltorio);
        $this->assertStringNotContainsString('rounded-2xl', $envoltorio);
    }

    /**
     * SIN NADA ENVIADO no hay tarjeta con la que unificarse, así que conserva la
     * suya: si no, quedaría un bloque flotando sin marco en medio de la pantalla.
     */
    public function test_sin_cotizacion_enviada_el_retiro_conserva_su_tarjeta(): void
    {
        $orden = $this->reparada();
        $orden->cotizaciones()->delete();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertDontSee('Enviada al cliente')
            ->getContent();

        $envoltorio = $this->envoltorioDe($html, 'Listo para retirar');
        $this->assertStringContainsString('rounded-2xl', $envoltorio);
    }

    /**
     * EN GARANTÍA NO SE TOCA: ahí el parte no incluye estas tarjetas (no hay
     * cotización que enviar) y la pestaña Cotización es la única pantalla donde
     * existe el botón. Sacarlas de ahí no sería quitar una repetición: sería borrar
     * la función.
     */
    public function test_en_garantia_el_boton_sigue_en_la_pestana_cotizacion(): void
    {
        $orden = $this->reparada([
            'facturacion' => 'garantia',
            'garantia_doc_tipo' => 'boleta',
            'garantia_doc_numero' => '123',
            'garantia_doc_fecha' => now()->subMonths(2)->toDateString(),
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $this->assertSame('garantia', $orden->condicion_efectiva, 'La garantía tiene que estar vigente para que el caso pruebe algo.');

        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.cotizacion', $orden))
            ->assertOk()
            ->assertSee('Avisar que está listo para retirar');
    }

    public function test_antes_de_reparar_la_pantalla_explica_que_falta(): void
    {
        $orden = $this->reparada(['estado' => 'cotizacion']);

        $this->actingAs($this->tecnico())
            ->get(route('admin.servicio-tecnico.reparacion', $orden))
            ->assertOk()
            ->assertDontSee('Avisar que está listo para retirar')
            ->assertSee('marca la orden como «Reparado» en Parte del técnico');
    }
}
