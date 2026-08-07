<?php

namespace Tests\Feature;

use App\Mail\RetiroSinReparacion;
use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Página pública de respuesta a la cotización (link firmado del correo): el
 * cliente ve la carta desde el snapshot y responde SOLO ACEPTO / NO ACEPTO.
 * La respuesta se registra (primera gana), avisa a los roles internos y NO
 * cambia el estado de la orden.
 */
class CotizacionPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Mail::fake(); // el NO ACEPTO dispara la cita de retiro por correo (07-08)
    }

    private function cotizacion(array $overrides = []): OrdenServicioCotizacion
    {
        $orden = OrdenServicio::factory()->create([
            'estado' => 'cotizacion',
            'facturacion' => 'reparacion',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_email' => 'cliente@example.com',
            'mano_obra' => 10000,
            'causa_falla' => 'Filtración interna',
        ]);
        $orden->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 1, 'precio_unitario' => 4000]);

        $c = OrdenServicioCotizacion::crearDesde(
            $orden->load('repuestos'),
            tap(User::factory()->create())->assignRole('tecnico')
        );
        if ($overrides) {
            $c->update($overrides);
        }

        return $c->fresh();
    }

    private function urlMostrar(OrdenServicioCotizacion $c): string
    {
        return URL::signedRoute('cotizacion.mostrar', ['cotizacion' => $c->token]);
    }

    private function responder(OrdenServicioCotizacion $c, string $respuesta, ?string $motivo = null)
    {
        return $this->post(
            URL::signedRoute('cotizacion.responder', ['cotizacion' => $c->token]),
            array_filter(['respuesta' => $respuesta, 'motivo' => $motivo])
        );
    }

    // --- Firma obligatoria ---

    public function test_get_y_post_sin_firma_son_rechazados(): void
    {
        $c = $this->cotizacion();

        $this->get('/cotizacion/'.$c->token)->assertForbidden();
        $this->post('/cotizacion/'.$c->token.'/respuesta', ['respuesta' => 'aceptada'])->assertForbidden();
        $this->assertSame('enviada', $c->fresh()->estado);
    }

    public function test_con_firma_muestra_la_carta_desde_el_snapshot(): void
    {
        $c = $this->cotizacion();

        $this->get($this->urlMostrar($c))
            ->assertOk()
            ->assertSee('Aguas Claras SpA')
            ->assertSee('Filtración interna')     // el porqué (diagnóstico)
            ->assertSee('1× Caldera')             // detalle
            ->assertSee('$14.000')                // total del snapshot
            ->assertSee('ACEPTO')
            ->assertSee('NO ACEPTO')
            // CON campo «¿por qué?» opcional: el 06-08 el dueño dio vuelta su
            // decisión del 30-07 (antes este test exigía que NO hubiera textarea).
            ->assertSee('name="motivo"', false)
            ->assertSee('(opcional)');
    }

    // --- Respuesta ---

    public function test_acepta_y_queda_registrado_sin_tocar_la_orden(): void
    {
        $c = $this->cotizacion();

        $this->responder($c, 'aceptada')->assertRedirect();

        $c->refresh();
        $this->assertSame('aceptada', $c->estado);
        $this->assertNotNull($c->respondida_at);
        $this->assertNotNull($c->respuesta_ip);
        // La orden NO cambia de etapa: el técnico decide el siguiente paso.
        $this->assertSame('cotizacion', $c->orden->fresh()->estado);
    }

    public function test_rechaza_y_avisa_a_los_roles_internos(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $c = $this->cotizacion();

        $this->responder($c, 'rechazada')->assertRedirect();

        $this->assertSame('rechazada', $c->fresh()->estado);
        foreach ([$tecnico, $jefe] as $u) {
            $this->assertSame(1, Notificacion::where('user_id', $u->id)
                ->where('evento', 'cotizacion.respondida')
                ->where('canal', Notificacion::CANAL_DATABASE)->count());
        }
    }

    public function test_el_motivo_del_cliente_queda_guardado_y_viaja_en_el_aviso(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $c = $this->cotizacion();

        $this->responder($c, 'rechazada', 'Muy caro, prefiero comprar uno nuevo')->assertRedirect();

        $this->assertSame('Muy caro, prefiero comprar uno nuevo', $c->fresh()->respuesta_motivo);

        // El «¿por qué?» llega DENTRO de la campanita (no hay que abrir la orden).
        $notif = Notificacion::where('user_id', $jefe->id)
            ->where('evento', 'cotizacion.respondida')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();
        $this->assertStringContainsString('Muy caro, prefiero comprar uno nuevo', $notif->cuerpo);
    }

    public function test_sin_motivo_el_aviso_dice_que_no_lo_indico(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $c = $this->cotizacion();

        $this->responder($c, 'aceptada')->assertRedirect();

        $this->assertNull($c->fresh()->respuesta_motivo);
        $notif = Notificacion::where('user_id', $jefe->id)
            ->where('evento', 'cotizacion.respondida')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();
        // Placeholder SIEMPRE relleno: sin motivo no queda «{motivo}» crudo.
        $this->assertStringContainsString('no indicó el motivo', $notif->cuerpo);
        $this->assertStringNotContainsString('{motivo}', $notif->cuerpo);
    }

    public function test_la_primera_respuesta_gana_y_no_se_renotifica(): void
    {
        $c = $this->cotizacion();

        $this->responder($c, 'aceptada');
        $antes = Notificacion::where('evento', 'cotizacion.respondida')->count();

        $this->responder($c, 'rechazada'); // segunda respuesta: no pisa ni re-avisa

        $this->assertSame('aceptada', $c->fresh()->estado);
        $this->assertSame($antes, Notificacion::where('evento', 'cotizacion.respondida')->count());
    }

    public function test_respuesta_invalida_es_rechazada(): void
    {
        $c = $this->cotizacion();

        $this->responder($c, 'quizas')->assertSessionHasErrors('respuesta');
        $this->assertSame('enviada', $c->fresh()->estado);
    }

    public function test_honeypot_no_registra_nada(): void
    {
        $c = $this->cotizacion();

        $this->post(URL::signedRoute('cotizacion.responder', ['cotizacion' => $c->token]), [
            'respuesta' => 'aceptada',
            'sitio_web' => 'http://spam.example',
        ])->assertRedirect();

        $this->assertSame('enviada', $c->fresh()->estado);
    }

    // --- Cita de retiro automática tras el NO ACEPTO (dueño 07-08) ---

    public function test_al_rechazar_la_cita_de_retiro_sale_sola_para_el_dia_habil_siguiente(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');
        $c = $this->cotizacion();

        // Rechaza un VIERNES por la tarde → la cita salta el fin de semana.
        $this->travelTo(Carbon::parse('2026-08-07 16:04'));
        $this->responder($c, 'rechazada')->assertRedirect();

        // El correo salió SOLO (nadie del taller tocó nada) citando al lunes.
        Mail::assertSent(RetiroSinReparacion::class, fn ($m) => $m->hasTo('cliente@example.com')
            && $m->retiroDesde->toDateString() === '2026-08-10');

        // Queda cerrado el ciclo: estampado como enviado por el sistema.
        $c->refresh();
        $this->assertNotNull($c->retiro_avisado_at);
        $this->assertNull($c->retiro_avisado_por); // null = automático, no una persona

        // Y el técnico se entera por la campanita, con el día citado.
        $notif = Notificacion::where('user_id', $tecnico->id)
            ->where('evento', 'cotizacion.retiro_avisado')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();
        $this->assertNotNull($notif, 'Falta la campanita del ciclo cerrado para el técnico.');
        $this->assertStringContainsString('lunes 10-08-2026', $notif->cuerpo);
        $this->assertStringContainsString('automáticamente', $notif->cuerpo);
        $this->assertStringContainsString('Ciclo cerrado', $notif->cuerpo);
        $this->assertStringNotContainsString('{retiro_dia}', $notif->cuerpo);
    }

    public function test_al_aceptar_no_sale_ninguna_cita_de_retiro(): void
    {
        $c = $this->cotizacion();

        $this->responder($c, 'aceptada')->assertRedirect();

        Mail::assertNotSent(RetiroSinReparacion::class);
        $this->assertNull($c->fresh()->retiro_avisado_at);
    }

    public function test_la_carta_de_la_cita_es_automatica_y_no_invita_a_responder(): void
    {
        // Estilo banco (dueño 07-08): se genera sola y el cliente no puede
        // responderla — antes decía «si cambias de opinión, responde este correo».
        $c = $this->cotizacion();

        $html = (new RetiroSinReparacion($c->load('orden'), Carbon::parse('2026-08-10')))->render();

        $this->assertStringContainsString('lunes 10-08-2026', $html);
        $this->assertStringContainsString('generó automáticamente', $html);
        $this->assertStringContainsString('no lo respondas', $html);
        $this->assertStringNotContainsString('responde este correo', $html);
    }

    public function test_si_la_cita_automatica_falla_la_respuesta_queda_y_el_respaldo_manual_sigue(): void
    {
        $c = $this->cotizacion();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->responder($c, 'rechazada')->assertRedirect();

        $c->refresh();
        $this->assertSame('rechazada', $c->estado);      // la respuesta nunca se pierde
        $this->assertNull($c->retiro_avisado_at);         // sin estampar → botón manual visible
        $this->assertSame(0, Notificacion::where('evento', 'cotizacion.retiro_avisado')->count());
    }

    public function test_la_pagina_de_gracias_del_rechazo_dice_que_la_cita_ya_viajo(): void
    {
        $c = $this->cotizacion();

        $this->responder($c, 'rechazada');

        $this->get(URL::signedRoute('cotizacion.gracias', ['cotizacion' => $c->token]))
            ->assertOk()
            ->assertSee('Te enviamos un correo con el día y el lugar para retirar tu equipo');
    }

    // --- No respondibles ---

    public function test_reemplazada_y_vencida_no_muestran_botones_ni_aceptan_post(): void
    {
        $reemplazada = $this->cotizacion(['estado' => 'reemplazada']);
        $this->get($this->urlMostrar($reemplazada))
            ->assertOk()
            ->assertSee('más reciente')
            ->assertDontSee('>ACEPTO<', false);
        $this->responder($reemplazada, 'aceptada');
        $this->assertSame('reemplazada', $reemplazada->fresh()->estado);

        $vencida = $this->cotizacion(['vence_at' => now()->subDay()]);
        $this->get($this->urlMostrar($vencida))
            ->assertOk()
            ->assertSee('venció');
        $this->responder($vencida, 'aceptada');
        $this->assertSame('enviada', $vencida->fresh()->estado); // sigue sin respuesta
    }
}
