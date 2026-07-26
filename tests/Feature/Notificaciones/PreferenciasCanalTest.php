<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Notificacion;
use App\Models\PreferenciaCanal;
use App\Models\User;
use App\Services\Notificaciones\NotificacionDispatcher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PreferenciasCanalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Las preferencias las gestiona SOLO Luis + TI (rol admin): se necesita
        // el rol/permiso sembrado para actuar como admin en los tests de edición.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_formulario_guarda_opt_out_y_el_dispatcher_lo_respeta(): void
    {
        Queue::fake();
        $admin = tap(User::factory()->create())->assignRole('admin');

        // El admin desmarca Correo (no envía la clave mail) y deja WhatsApp on.
        $this->actingAs($admin)
            ->put(route('perfil.notificaciones.update'), [
                'prefs' => ['sistema.prueba' => [Notificacion::CANAL_WHATSAPP => '1']],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('preferencias_canal', [
            'user_id' => $admin->id, 'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_MAIL, 'habilitado' => false,
        ]);
        $this->assertDatabaseHas('preferencias_canal', [
            'user_id' => $admin->id, 'evento' => 'sistema.prueba',
            'canal' => Notificacion::CANAL_WHATSAPP, 'habilitado' => true,
        ]);

        // El dispatcher respeta lo guardado: database (fijo) + whatsapp, NO mail.
        $creadas = app(NotificacionDispatcher::class)->despachar('sistema.prueba', null, $admin);
        $canales = $creadas->pluck('canal')->all();

        $this->assertContains(Notificacion::CANAL_DATABASE, $canales);
        $this->assertContains(Notificacion::CANAL_WHATSAPP, $canales);
        $this->assertNotContains(Notificacion::CANAL_MAIL, $canales);
    }

    public function test_es_idempotente_updateorcreate_no_duplica(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');
        $payload = ['prefs' => ['sistema.prueba' => [Notificacion::CANAL_MAIL => '1']]];

        $this->actingAs($admin)->put(route('perfil.notificaciones.update'), $payload);
        $this->actingAs($admin)->put(route('perfil.notificaciones.update'), $payload);

        // Una fila por (user, evento, canal) para los 2 canales togglables —
        // derivado del catalogo (EVENTOS crece cuando un modulo se integra,
        // p.ej. M14 sumo los suyos; hardcodear el conteo rompia con cada alta).
        $esperadas = count(Notificacion::EVENTOS) * 2;
        $this->assertSame($esperadas, PreferenciaCanal::where('user_id', $admin->id)->count());
    }

    public function test_requiere_autenticacion(): void
    {
        $this->put(route('perfil.notificaciones.update'), [])->assertRedirect(route('login'));
    }

    public function test_usuario_normal_no_puede_editar_las_preferencias(): void
    {
        // Sin el permiso 'gestionar notificaciones' (pedido del jefe: solo Luis + TI).
        $normal = tap(User::factory()->create())->assignRole('soplador');

        $this->actingAs($normal)
            ->put(route('perfil.notificaciones.update'), [
                'prefs' => ['sistema.prueba' => [Notificacion::CANAL_MAIL => '1']],
            ])
            ->assertForbidden();

        // No se creó ninguna preferencia para él.
        $this->assertSame(0, PreferenciaCanal::where('user_id', $normal->id)->count());
    }

    public function test_la_seccion_de_notificaciones_solo_la_ve_el_admin(): void
    {
        // Par positivo+negativo: si se quitara el @can, el usuario normal vería
        // la sección y el assertDontSee se pondría rojo (mutation-proof).
        $marcador = 'Elige por qué canal';

        $admin = tap(User::factory()->create())->assignRole('admin');
        $this->actingAs($admin)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($marcador);

        $normal = tap(User::factory()->create())->assignRole('soplador');
        $this->actingAs($normal)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee($marcador);
    }
}
