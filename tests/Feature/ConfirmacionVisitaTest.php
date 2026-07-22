<?php

namespace Tests\Feature;

use App\Mail\AgendaTrabajoAviso;
use App\Models\AgendaTrabajo;
use App\Models\Notificacion;
use App\Models\ServicioTerreno;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Ciclo de vuelta al cliente en la agenda de terreno: al coordinar (agendar) se
 * le envía una confirmación por correo; el cliente responde Confirmo / No puedo
 * + comentario corto por un link firmado; reprogramar reenvía y anular avisa.
 */
class ConfirmacionVisitaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
    }

    private function vendedor(): User
    {
        return tap(User::factory()->create())->assignRole('vendedor');
    }

    /** Solicitud QR pendiente de coordinar (sin fecha), con correo del cliente. */
    private function solicitud(): AgendaTrabajo
    {
        return AgendaTrabajo::factory()->create([
            'estado' => 'solicitado', 'fecha' => null,
            'tipo' => 'visita_tecnica',
            'cliente_nombre' => 'Aguas Claras SpA', 'cliente_email' => 'cliente@example.com',
        ]);
    }

    private function coordinarPayload(array $overrides = []): array
    {
        return array_merge([
            'tipo' => 'visita_tecnica',
            'estado' => 'agendado',
            'fecha' => now()->addDays(3)->toDateString(),
            'hora' => '10:00',
            'cliente_nombre' => 'Aguas Claras SpA',
            'cliente_rut' => '12.345.678-5',
            'cliente_telefono' => '+56 9 1111 2222',
            'cliente_email' => 'cliente@example.com',
            'direccion' => 'Camino Industrial 500',
            'ciudad' => 'Talca',
            'descripcion' => 'Revisión de planta',
        ], $overrides);
    }

    // --- Envío automático al coordinar ---

    public function test_coordinar_envia_confirmacion_y_genera_link(): void
    {
        Mail::fake();
        $s = $this->solicitud();

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $s), $this->coordinarPayload())
            ->assertRedirect();

        $s->refresh();
        $this->assertSame('agendado', $s->estado);
        $this->assertNotNull($s->confirmacion_token);
        $this->assertNotNull($s->confirmacion_enviada_at);
        Mail::assertSent(AgendaTrabajoAviso::class, fn ($m) => $m->motivo === 'agendada' && $m->hasTo('cliente@example.com'));
    }

    public function test_coordinar_guia_como_confirmarle_al_cliente(): void
    {
        // Al abrir "Coordinar" una solicitud (sin agendar), la pantalla explica
        // cómo avisarle al cliente (poner fecha + estado Agendado + guardar).
        $s = $this->solicitud();

        $this->actingAs($this->vendedor())
            ->get(route('admin.agenda-terreno.edit', $s))
            ->assertOk()
            ->assertSee('¿Confirmarle al cliente?')
            ->assertSee('estado a «Agendado»', false);
    }

    public function test_coordinar_muestra_que_la_confirmacion_ya_se_envio(): void
    {
        $t = $this->agendada(); // agendada + confirmación enviada

        $this->actingAs($this->vendedor())
            ->get(route('admin.agenda-terreno.edit', $t))
            ->assertOk()
            ->assertSee('Confirmación enviada al cliente');
    }

    // --- Respuesta del cliente ---

    private function agendada(array $overrides = []): AgendaTrabajo
    {
        $t = AgendaTrabajo::factory()->create(array_merge([
            'estado' => 'agendado',
            'fecha' => now()->addDays(3)->toDateString(),
            'hora' => '10:00',
            'cliente_nombre' => 'Aguas Claras SpA', 'cliente_email' => 'cliente@example.com',
        ], $overrides));
        $t->prepararConfirmacionCliente();

        return $t->fresh();
    }

    public function test_cliente_confirma_con_nota_y_avisa_a_ventas(): void
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $t = $this->agendada();

        $url = URL::signedRoute('confirmacion-visita.responder', ['token' => $t->confirmacion_token]);
        $this->post($url, ['respuesta' => 'confirmada', 'nota' => 'Sí puedo, llego a las 15:00'])
            ->assertRedirect();

        $t->refresh();
        $this->assertSame('confirmada', $t->cliente_confirmacion);
        $this->assertSame('Sí puedo, llego a las 15:00', $t->cliente_confirmacion_nota);
        $this->assertNotNull($t->cliente_confirmacion_at);
        // La cita NO cambia de estado operativo.
        $this->assertSame('agendado', $t->estado);
        // Aviso a ventas.
        $this->assertSame(1, Notificacion::where('user_id', $jefe->id)
            ->where('evento', 'terreno.confirmada')
            ->where('canal', Notificacion::CANAL_DATABASE)->count());
    }

    public function test_get_y_post_sin_firma_son_rechazados(): void
    {
        $t = $this->agendada();

        $this->get('/confirmacion-visita/'.$t->confirmacion_token)->assertForbidden();
        $this->post('/confirmacion-visita/'.$t->confirmacion_token.'/respuesta', ['respuesta' => 'confirmada'])->assertForbidden();
        $this->assertNull($t->fresh()->cliente_confirmacion);
    }

    public function test_muestra_botones_y_sin_comentario_extenso_pasa_el_tope(): void
    {
        $t = $this->agendada();

        $this->get(URL::signedRoute('confirmacion-visita.mostrar', ['token' => $t->confirmacion_token]))
            ->assertOk()
            ->assertSee('Confirmo, puedo ese día')
            ->assertSee('No puedo ese día');

        // La nota tiene tope (~150 palabras ≈ 1000 chars): más largo se rechaza.
        $url = URL::signedRoute('confirmacion-visita.responder', ['token' => $t->confirmacion_token]);
        $this->post($url, ['respuesta' => 'confirmada', 'nota' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('nota');
    }

    public function test_la_primera_respuesta_gana(): void
    {
        $t = $this->agendada();
        $url = fn () => URL::signedRoute('confirmacion-visita.responder', ['token' => $t->confirmacion_token]);

        $this->post($url(), ['respuesta' => 'confirmada']);
        $this->post($url(), ['respuesta' => 'no_puede']); // ya no es confirmable

        $this->assertSame('confirmada', $t->fresh()->cliente_confirmacion);
    }

    // --- Reprogramar / anular ---

    public function test_reprogramar_reenvia_y_resetea_la_confirmacion(): void
    {
        Mail::fake();
        $t = $this->agendada();
        $t->update(['cliente_confirmacion' => 'confirmada', 'cliente_confirmacion_at' => now()]);

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), $this->coordinarPayload([
                'fecha' => now()->addDays(9)->toDateString(),
            ]));

        $t->refresh();
        $this->assertNull($t->cliente_confirmacion); // se reseteó: la cita cambió
        Mail::assertSent(AgendaTrabajoAviso::class, fn ($m) => $m->motivo === 'reprogramada');
    }

    public function test_anular_avisa_al_cliente_sin_link(): void
    {
        Mail::fake();
        $t = $this->agendada();

        $this->actingAs($this->vendedor())
            ->put(route('admin.agenda-terreno.update', $t), $this->coordinarPayload(['estado' => 'cancelado']));

        Mail::assertSent(AgendaTrabajoAviso::class, fn ($m) => $m->motivo === 'anulada');
    }

    // --- Rechazo de una solicitud con motivo (la cara "no" de coordinar) ---

    public function test_rechazar_una_solicitud_la_cancela_y_avisa_al_cliente(): void
    {
        Mail::fake();
        $s = $this->solicitud();

        $this->actingAs($this->vendedor())
            ->post(route('admin.agenda-terreno.rechazar', $s), ['motivo' => 'tecnico_viaje'])
            ->assertRedirect();

        $s->refresh();
        $this->assertSame('cancelado', $s->estado);
        $this->assertSame('Técnico de viaje / fuera de zona', $s->motivo_cancelacion);
        Mail::assertSent(AgendaTrabajoAviso::class, fn ($m) => $m->motivo === 'anulada' && $m->hasTo('cliente@example.com'));
    }

    public function test_rechazar_con_motivo_otro_exige_detalle(): void
    {
        $s = $this->solicitud();

        $this->actingAs($this->vendedor())
            ->post(route('admin.agenda-terreno.rechazar', $s), ['motivo' => 'otro'])
            ->assertSessionHasErrors('motivo_otro');

        $this->assertSame('solicitado', $s->fresh()->estado); // no se canceló
    }

    public function test_rechazar_guarda_el_detalle_libre_de_otro(): void
    {
        Mail::fake();
        $s = $this->solicitud();

        $this->actingAs($this->vendedor())
            ->post(route('admin.agenda-terreno.rechazar', $s), ['motivo' => 'otro', 'motivo_otro' => 'Cliente en otra región']);

        $this->assertSame('Cliente en otra región', $s->fresh()->motivo_cancelacion);
    }

    public function test_rechazar_avisa_a_ventas(): void
    {
        Mail::fake();
        $jefe = tap(User::factory()->create())->assignRole('jefe_ventas');
        $s = $this->solicitud();

        $this->actingAs($this->vendedor())
            ->post(route('admin.agenda-terreno.rechazar', $s), ['motivo' => 'atraso_pagos']);

        $this->assertSame(1, Notificacion::where('user_id', $jefe->id)
            ->where('evento', 'terreno.rechazada')
            ->where('canal', Notificacion::CANAL_DATABASE)->count());
    }
}
