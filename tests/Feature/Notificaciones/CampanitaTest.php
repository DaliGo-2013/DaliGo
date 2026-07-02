<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampanitaTest extends TestCase
{
    use RefreshDatabase;

    private function inApp(User $user, string $estado = Notificacion::ENVIADA): Notificacion
    {
        return Notificacion::create([
            'evento' => 'sistema.prueba',
            'user_id' => $user->id,
            'canal' => Notificacion::CANAL_DATABASE,
            'titulo' => 'Hola',
            'cuerpo' => 'C',
            'estado' => $estado,
        ]);
    }

    public function test_contador_cuenta_solo_database_enviada_del_usuario(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $this->inApp($user);                                   // cuenta
        $this->inApp($user, Notificacion::LEIDA);              // no (ya leída)
        $this->inApp($otro);                                   // no (de otro)
        Notificacion::create([                                 // no (canal mail)
            'evento' => 'sistema.prueba', 'user_id' => $user->id,
            'canal' => Notificacion::CANAL_MAIL, 'titulo' => 'T', 'cuerpo' => 'C',
            'estado' => Notificacion::ENVIADA,
        ]);

        $this->assertSame(1, Notificacion::campanitaDe($user->id)->count());
    }

    public function test_marcar_leida_baja_el_contador(): void
    {
        $user = User::factory()->create();
        $n = $this->inApp($user);

        $this->actingAs($user)
            ->post(route('notificaciones.leer', $n))
            ->assertRedirect();

        $this->assertSame(Notificacion::LEIDA, $n->fresh()->estado);
        $this->assertNotNull($n->fresh()->leida_at);
        $this->assertSame(0, Notificacion::campanitaDe($user->id)->count());
    }

    public function test_no_puedo_marcar_leida_la_de_otro(): void
    {
        $user = User::factory()->create();
        $ajena = $this->inApp(User::factory()->create());

        $this->actingAs($user)
            ->post(route('notificaciones.leer', $ajena))
            ->assertForbidden();

        $this->assertSame(Notificacion::ENVIADA, $ajena->fresh()->estado);
    }

    public function test_marcar_todas_leidas(): void
    {
        $user = User::factory()->create();
        $this->inApp($user);
        $this->inApp($user);

        $this->actingAs($user)
            ->post(route('notificaciones.leer-todas'))
            ->assertRedirect();

        $this->assertSame(0, Notificacion::campanitaDe($user->id)->count());
    }

    public function test_campanita_visible_en_el_nav(): void
    {
        $user = User::factory()->create();
        $this->inApp($user);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notificaciones', false);
    }
}
