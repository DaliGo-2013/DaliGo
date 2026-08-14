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

    /**
     * Responde como responde un cliente real: desde el 14-08 el motivo es
     * OBLIGATORIO en las dos respuestas, así que el helper lo manda por defecto y
     * los tests que no van sobre esa regla no tienen que repetirlo.
     *
     * Para probar la regla se pasa explícitamente `null` (campo ausente), `''`
     * (vacío) o `'   '` (solo espacios) — los tres casos son distintos y los tres
     * tienen que rebotar.
     */
    private function responder(
        OrdenServicioCotizacion $c,
        string $respuesta,
        ?string $motivo = 'Lo conversé con mi equipo y seguimos adelante.'
    ) {
        $datos = ['respuesta' => $respuesta];

        // Con null la clave NO viaja (campo ausente); con '' o '   ' viaja tal cual,
        // que es lo que hace falta para probar el trim del servidor.
        if ($motivo !== null) {
            $datos['motivo'] = $motivo;
        }

        return $this->post(
            URL::signedRoute('cotizacion.responder', ['cotizacion' => $c->token]),
            $datos
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
            // CON campo «¿por qué?», y desde el 14-08 OBLIGATORIO: marcado con el
            // `*` rojo y con el `required` del navegador, para que el cliente no
            // haga el viaje al servidor para enterarse.
            ->assertSee('name="motivo"', false)
            ->assertSee('required', false)
            ->assertDontSee('(opcional)');
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

    /**
     * EL MOTIVO ES OBLIGATORIO EN LAS DOS RESPUESTAS (dueño 14-08-2026). Este test
     * REEMPLAZA a `test_sin_motivo_el_aviso_dice_que_no_lo_indico`, que fijaba la
     * regla anterior (responder sin motivo era válido y el aviso decía «no lo
     * indicó»): esa conducta ya no existe y dejar el test habría sido conservar en
     * verde justo lo que se quiso prohibir.
     *
     * Los TRES casos importan y son distintos: campo ausente (un POST armado a
     * mano), campo vacío, y campo con solo espacios — el último es el que `required`
     * por sí solo dejaría pasar, y el que haría que el campo fuera obligatorio solo
     * de apariencia.
     */
    public function test_no_se_puede_responder_sin_decir_por_que(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');

        foreach (['aceptada', 'rechazada'] as $respuesta) {
            foreach ([null, '', '   '] as $motivo) {
                $c = $this->cotizacion();

                $this->responder($c, $respuesta, $motivo)
                    ->assertSessionHasErrors('motivo');

                // Y la cotización queda INTACTA: sigue respondible, sin fecha de
                // respuesta y sin avisarle a nadie. Un rechazo a medio aplicar sería
                // peor que el campo opcional.
                $c->refresh();
                $this->assertSame('enviada', $c->estado);
                $this->assertNull($c->respondida_at);
                $this->assertNull($c->respuesta_motivo);
            }
        }

        $this->assertSame(
            0,
            Notificacion::where('evento', 'cotizacion.respondida')->count(),
            'Una respuesta rechazada por falta de motivo no puede avisar nada.'
        );
        $this->assertNotNull($jefe);
    }

    /** Y con motivo, el ACEPTO también lo guarda (antes solo se probaba el rechazo). */
    public function test_el_motivo_tambien_se_guarda_al_aceptar(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $c = $this->cotizacion();

        $this->responder($c, 'aceptada', 'Autorizo, pero factúrenlo a nombre de la empresa.')
            ->assertRedirect();

        $c->refresh();
        $this->assertSame('aceptada', $c->estado);
        $this->assertSame('Autorizo, pero factúrenlo a nombre de la empresa.', $c->respuesta_motivo);

        $notif = Notificacion::where('user_id', $jefe->id)
            ->where('evento', 'cotizacion.respondida')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();
        $this->assertStringContainsString('factúrenlo a nombre de la empresa', $notif->cuerpo);
        // El placeholder nunca queda crudo (la mitad que sobrevive del test viejo).
        $this->assertStringNotContainsString('{motivo}', $notif->cuerpo);
    }

    /** El motivo se guarda TRIMEADO: sin esto entraría con los espacios del textarea. */
    public function test_el_motivo_se_guarda_sin_espacios_de_sobra(): void
    {
        $c = $this->cotizacion();

        $this->responder($c, 'rechazada', "   Muy caro para mí.\n  ")->assertRedirect();

        $this->assertSame('Muy caro para mí.', $c->fresh()->respuesta_motivo);
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
