<?php

namespace Tests\Feature\Admin;

use App\Mail\AgendaTrabajoAviso;
use App\Models\AgendaTrabajo;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * EL BOTÓN QUE CIERRA LA CONFIRMACIÓN DE LA VISITA.
 *
 * Pedido del dueño (21-08-2026): «cuando se hace un ingreso para coordinar le llega al jefe de
 * ventas, este cliquea en coordinar, pero adentro no hay un botón de confirmar para que se le
 * envíe una confirmación al cliente… hasta ahora no entiendo que aparezca algo que cierre esa
 * confirmación».
 *
 * Y era exacto: el correo al cliente SÍ salía, pero solo si el jefe sabía que confirmar era
 * «cambiar el estado a Agendado y guardar» —un cartel ámbar lo explicaba— y el mensaje de vuelta
 * decía «Trabajo actualizado», que no cierra nada. La cara NO del mismo flujo («Rechazar y
 * avisar», con su motivo) tenía su botón desde el principio: la asimetría era el defecto.
 *
 * El estado lo fija el SERVIDOR con `confirmar`, no el select: confirmar no puede depender de
 * que alguien acierte una opción.
 */
class ConfirmarVisitaAlClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Mail::fake();
    }

    private function jefeVentas(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_ventas');
    }

    /** Una solicitud del QR: sin fecha, esperando que ventas la coordine. */
    private function solicitud(array $extra = []): AgendaTrabajo
    {
        return AgendaTrabajo::factory()->create(array_merge([
            'tipo' => 'visita_tecnica',
            'estado' => 'solicitado',
            'fecha' => null,
            'hora' => null,
            'cliente_nombre' => 'AGUAS RAE',
            'cliente_rut' => '77255113-4',
            'cliente_email' => 'cliente@example.com',
            'cliente_telefono' => '966440561',
            'direccion' => 'AV. MIRADOR 150',
            'ciudad' => 'SANTIAGO',
            'descripcion' => 'Reparación llenadora',
            'fecha_preferida' => '2026-09-10',
        ], $extra));
    }

    /** Lo que manda el formulario de coordinar. */
    private function coordinar(AgendaTrabajo $trabajo, array $extra = [])
    {
        return $this->actingAs($this->jefeVentas())->put(
            route('admin.agenda-terreno.update', $trabajo),
            array_merge([
                'tipo' => 'visita_tecnica',
                'fecha' => '2026-09-10',
                'hora' => '08:00',
                'cliente_nombre' => $trabajo->cliente_nombre,
                'cliente_rut' => '77255113-4',
                'cliente_email' => $trabajo->cliente_email,
                'cliente_telefono' => $trabajo->cliente_telefono,
                'direccion' => $trabajo->direccion,
                'ciudad' => $trabajo->ciudad,
                'descripcion' => $trabajo->descripcion,
                'estado' => 'solicitado',
            ], $extra),
        );
    }

    // ─────────────────────────────────────────── el botón

    public function test_confirmar_agenda_la_visita_y_le_manda_el_correo_al_cliente(): void
    {
        $trabajo = $this->solicitud();

        $this->coordinar($trabajo, ['confirmar' => '1'])->assertRedirect();

        // El estado lo pone el botón, no el select (que llegó en 'solicitado').
        $this->assertSame('agendado', $trabajo->fresh()->estado);
        Mail::assertSent(AgendaTrabajoAviso::class, fn ($mail) => $mail->hasTo('cliente@example.com'));
    }

    /** Y el mensaje de vuelta DICE que salió el correo, y a qué dirección. */
    public function test_el_mensaje_dice_que_se_le_aviso_y_a_donde(): void
    {
        $trabajo = $this->solicitud();

        $this->coordinar($trabajo, ['confirmar' => '1'])
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Visita confirmada')
                && str_contains($status, 'cliente@example.com'));
    }

    /** Guardar SIN confirmar sigue existiendo, y no le manda nada al cliente. */
    public function test_guardar_sin_avisar_no_le_manda_nada(): void
    {
        $trabajo = $this->solicitud();

        $this->coordinar($trabajo, ['descripcion' => 'Reparación llenadora y mantención'])->assertRedirect();

        $this->assertSame('solicitado', $trabajo->fresh()->estado);
        $this->assertSame('Reparación llenadora y mantención', $trabajo->fresh()->descripcion);
        Mail::assertNothingSent();
    }

    /**
     * SIN FECHA NO SE CONFIRMA: antes «confirmar» sin fecha guardaba en silencio y el cliente no
     * recibía nada — el mismo agujero de siempre, pero con un botón que promete lo contrario.
     */
    public function test_confirmar_sin_fecha_es_un_error_y_no_manda_nada(): void
    {
        $trabajo = $this->solicitud();

        $this->coordinar($trabajo, ['confirmar' => '1', 'fecha' => ''])
            ->assertSessionHasErrors('fecha');

        $this->assertSame('solicitado', $trabajo->fresh()->estado);
        Mail::assertNothingSent();
    }

    /**
     * NO SE PUEDE CONFIRMAR SIN CORREO, y el formulario lo exige antes de llegar al mensaje: si
     * la confirmación se manda por correo, el correo es parte de coordinar. (El controlador tiene
     * igual su rama defensiva —«no tiene correo: hay que llamarlo»— para las citas que se crearon
     * por otros caminos.)
     */
    public function test_no_se_puede_confirmar_sin_el_correo_del_cliente(): void
    {
        $trabajo = $this->solicitud();

        $this->coordinar($trabajo, ['confirmar' => '1', 'cliente_email' => ''])
            ->assertSessionHasErrors('cliente_email');

        $this->assertSame('solicitado', $trabajo->fresh()->estado);
        Mail::assertNothingSent();
    }

    // ─────────────────────────────────────────── la pantalla

    public function test_la_pantalla_de_coordinar_ofrece_el_boton(): void
    {
        $trabajo = $this->solicitud();

        $html = $this->actingAs($this->jefeVentas())
            ->get(route('admin.agenda-terreno.edit', $trabajo))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Confirmar y avisar al cliente', $html);
        $this->assertStringContainsString('name="confirmar"', $html);
        $this->assertStringContainsString('Guardar sin avisar', $html);
        // Y el cartel ya no enseña el baile manual del select.
        $this->assertStringNotContainsString('cambia el <span class="font-medium">estado a «Agendado»</span> y guarda', $html);
    }

    /** En una cita YA agendada la acción principal vuelve a ser guardar (no re-confirmar). */
    public function test_una_cita_ya_agendada_no_ofrece_confirmar_de_nuevo(): void
    {
        $trabajo = $this->solicitud(['estado' => 'agendado', 'fecha' => '2026-09-10']);

        $html = $this->actingAs($this->jefeVentas())
            ->get(route('admin.agenda-terreno.edit', $trabajo))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="confirmar"', $html);
        $this->assertStringContainsString('Guardar cambios', $html);
    }

    // ─────────────────────────────────────────── el otro agujero del mismo flujo

    /**
     * CANCELAR UNA SOLICITUD TAMBIÉN LE AVISA. Antes el aviso exigía venir de «agendado», así
     * que una solicitud del QR cancelada desde el formulario se apagaba en silencio: el cliente
     * que la pidió no se enteraba nunca — y el cartel de la pantalla ya prometía el aviso.
     */
    public function test_cancelar_una_solicitud_le_avisa_al_cliente(): void
    {
        $trabajo = $this->solicitud();

        $this->coordinar($trabajo, ['estado' => 'cancelado'])->assertRedirect();

        $this->assertSame('cancelado', $trabajo->fresh()->estado);
        Mail::assertSent(AgendaTrabajoAviso::class, fn ($mail) => $mail->hasTo('cliente@example.com'));
    }

    /** Lo que ya estaba cancelado no re-avisa al volver a guardar. */
    public function test_no_se_reavisa_una_cancelacion_ya_avisada(): void
    {
        $trabajo = $this->solicitud(['estado' => 'cancelado']);

        $this->coordinar($trabajo, ['estado' => 'cancelado']);

        Mail::assertNothingSent();
    }
}
