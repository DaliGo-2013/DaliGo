<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Notificacion;
use App\Models\PreferenciaCanal;
use App\Models\User;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PreferenciasCanalTest extends TestCase
{
    use RefreshDatabase;

    public function test_formulario_guarda_opt_out_y_el_dispatcher_lo_respeta(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        // El usuario desmarca Correo (no envía la clave mail) y deja WhatsApp on.
        $this->actingAs($user)
            ->put(route('perfil.notificaciones.update'), [
                'prefs' => ['sistema.prueba' => [Notificacion::CANAL_WHATSAPP => '1']],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('preferencias_canal', [
            'user_id' => $user->id, 'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_MAIL, 'habilitado' => false,
        ]);
        $this->assertDatabaseHas('preferencias_canal', [
            'user_id' => $user->id, 'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_WHATSAPP, 'habilitado' => true,
        ]);

        // El dispatcher respeta lo guardado: database (fijo) + whatsapp, NO mail.
        $creadas = app(NotificacionDispatcher::class)->despachar('sistema.prueba', null, $user);
        $canales = $creadas->pluck('canal')->all();

        $this->assertContains(Notificacion::CANAL_DATABASE, $canales);
        $this->assertContains(Notificacion::CANAL_WHATSAPP, $canales);
        $this->assertNotContains(Notificacion::CANAL_MAIL, $canales);
    }

    public function test_es_idempotente_updateorcreate_no_duplica(): void
    {
        $user = User::factory()->create();
        $payload = ['prefs' => ['sistema.prueba' => [Notificacion::CANAL_MAIL => '1']]];

        $this->actingAs($user)->put(route('perfil.notificaciones.update'), $payload);
        $this->actingAs($user)->put(route('perfil.notificaciones.update'), $payload);

        // Una fila por (user, evento, canal) para los 2 canales togglables.
        $this->assertSame(2, PreferenciaCanal::where('user_id', $user->id)->count());
    }

    public function test_requiere_autenticacion(): void
    {
        $this->put(route('perfil.notificaciones.update'), [])->assertRedirect(route('login'));
    }
}
