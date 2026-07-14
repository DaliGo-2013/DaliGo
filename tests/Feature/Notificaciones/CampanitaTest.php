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

    public function test_campanita_muestra_el_conteo_en_el_nav(): void
    {
        $user = User::factory()->create();
        $this->inApp($user);
        $this->inApp($user);
        $this->inApp($user);

        // El nav renderiza la campanita con el badge del conteo real (3), el
        // CONTENIDO del dropdown (título de la notificación) y sus acciones —
        // no solo el conteo (micro-backlog M15-c: endurecer el test de humo).
        $ultima = Notificacion::campanitaDe($user->id)->latest('id')->first();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notificaciones', false)
            ->assertSee('>3<', false)
            ->assertSee($ultima->titulo)
            ->assertSee('Marcar todas')
            ->assertSee('Ver todas');

        // Marcar todas → el badge desaparece (conteo 0).
        $this->actingAs($user)->post(route('notificaciones.leer-todas'));
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>3<', false);
    }

    public function test_campana_movil_siempre_visible_con_badge_y_destino(): void
    {
        // Hallazgo QA de celular 14-07: la campanita vivía solo al fondo del
        // hamburguesa y nadie la descubría. La cabecera móvil lleva ahora una
        // campana SIEMPRE visible (aria-label distintivo) con el conteo y link
        // directo a la bandeja personal.
        $user = User::factory()->create();
        $this->inApp($user);
        $this->inApp($user);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notificaciones (2 sin leer)"', false)
            ->assertSee(route('notificaciones.index'), false);

        // Sin no-leídas: la campana queda (aria-label sin conteo), el badge no.
        // Ojo: el sr-only del partial desktop siempre dice "(0 sin leer)", así
        // que se asserta el aria-label EXACTO de la campana móvil, no el texto suelto.
        $this->actingAs($user)->post(route('notificaciones.leer-todas'));
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notificaciones"', false)
            ->assertDontSee('aria-label="Notificaciones (2 sin leer)"', false);
    }

    public function test_pagina_personal_lista_solo_lo_propio(): void
    {
        $user = User::factory()->create();
        $this->inApp($user);
        $ajena = $this->inApp(User::factory()->create());
        $ajena->update(['titulo' => 'Ajena secreta']);

        $this->actingAs($user)->get(route('notificaciones.index'))
            ->assertOk()
            ->assertViewIs('notificaciones.index')
            ->assertDontSee('Ajena secreta');
    }

    public function test_no_se_puede_leer_una_no_database(): void
    {
        $user = User::factory()->create();
        $mail = Notificacion::create([
            'evento' => 'sistema.prueba', 'user_id' => $user->id,
            'canal' => Notificacion::CANAL_MAIL, 'titulo' => 'T', 'cuerpo' => 'C',
            'estado' => Notificacion::FALLIDA,
        ]);

        $this->actingAs($user)
            ->post(route('notificaciones.leer', $mail))
            ->assertNotFound();

        $this->assertSame(Notificacion::FALLIDA, $mail->fresh()->estado);
    }
}
