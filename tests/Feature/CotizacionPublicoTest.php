<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioCotizacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function responder(OrdenServicioCotizacion $c, string $respuesta)
    {
        return $this->post(
            URL::signedRoute('cotizacion.responder', ['cotizacion' => $c->token]),
            ['respuesta' => $respuesta]
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
            ->assertDontSee('<textarea', false);  // SIN campo de comentario (decisión del dueño)
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
