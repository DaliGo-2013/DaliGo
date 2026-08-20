<?php

namespace Tests\Feature\Mensajes;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\Mensajes\Mensajeria;
use App\Support\AvisosError;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * MSG-2 (PLAN-MENSAJES §5.2): las pantallas del chat — lista de conversaciones
 * (molde bandeja), hilo con burbujas y nuevo mensaje. El gate de las rutas es
 * el permiso 'usar mensajes'; adentro, ser participante.
 */
class PantallasMensajesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(string $nombre = null): User
    {
        $user = User::factory()->create($nombre ? ['name' => $nombre] : []);

        return tap($user)->assignRole('member');
    }

    private function enviar(User $de, User $a, string $texto): Mensaje
    {
        Queue::fake();

        return app(Mensajeria::class)->enviar($de, $a, $texto);
    }

    // ---------------------------------------------------------------
    // Lista de conversaciones
    // ---------------------------------------------------------------

    public function test_la_lista_muestra_solo_mis_conversaciones_ordenadas(): void
    {
        $ana = $this->usuario('Ana Riquelme');
        $beto = $this->usuario('Beto Soto');
        $carla = $this->usuario('Carla Muñoz');

        $this->enviar($beto, $ana, 'Hola Ana, tema bodega');
        $this->enviar($carla, $ana, 'Ana, lo del camion');
        $this->enviar($beto, $carla, 'Secreto entre Beto y Carla');

        $html = $this->actingAs($ana)->get(route('mensajes.index'))
            ->assertOk()
            ->assertSee('Beto Soto')
            ->assertSee('Carla Muñoz')
            ->assertSee('tema bodega')
            ->assertDontSee('Secreto entre Beto y Carla')
            ->getContent();

        // Orden por ultimo mensaje: la de Carla (mas nueva) va ARRIBA.
        $this->assertLessThan(
            strpos($html, 'Beto Soto'),
            strpos($html, 'Carla Muñoz'),
            'La conversación con el último mensaje debe ir primero.',
        );
    }

    public function test_los_no_leidos_se_anuncian_por_marcador_accesible(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario('Beto Soto');
        $this->enviar($beto, $ana, 'Uno');
        $this->enviar($beto, $ana, 'Dos');

        // Doctrina CampanitaTest: marcador accesible, jamas la clase CSS. El
        // marcador es «mensajes sin leer» (distinto del «(N sin leer)» de la
        // campanita del shell, que aqui SIEMPRE esta encendida por la rafaga).
        $this->actingAs($ana)->get(route('mensajes.index'))
            ->assertOk()
            ->assertSee('mensajes sin leer');

        Conversacion::entre($ana, $beto)->marcarLeida($ana);

        $this->actingAs($ana)->get(route('mensajes.index'))
            ->assertOk()
            ->assertDontSee('mensajes sin leer');
    }

    public function test_la_lista_ya_no_lleva_volver(): void
    {
        // INVERTIDO en MSG-4: la huerfana temporal de MSG-2 dejo de serlo —
        // «Mensajes» es item del menu y el menu ES el camino (P-NAV-06/08).
        // VolverTest::test_ningun_item_del_menu_lleva_volver ahora la cubre
        // solo (deriva de MenuPrincipal::items()); este assert es el gemelo
        // directo. Las hijas (hilo, nuevo) CONSERVAN su Volver a la lista.
        $this->actingAs($this->usuario())->get(route('mensajes.index'))
            ->assertOk()
            ->assertDontSee('data-dg-volver', false);
    }

    // ---------------------------------------------------------------
    // El item del menu con su badge (MSG-4 — cierra el chat)
    // ---------------------------------------------------------------

    public function test_el_item_del_menu_aparece_solo_con_permiso(): void
    {
        // «33 con chat, 32 sin»: presencia de la route en la sidebar, no un
        // conteo magico (los tests del menu no hardcodean totales).
        $this->actingAs($this->usuario())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('mensajes.index'));

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('mensajes.index'));
    }

    public function test_el_badge_del_menu_cuenta_mis_no_leidos(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Uno');
        $this->enviar($beto, $ana, 'Dos');

        // Contrato del title (molde SidebarTest): el badge declara su porque.
        $this->actingAs($ana)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('title="2 mensaje(s) sin leer"', false);

        // En cero, el badge desaparece (y con el, su title).
        Conversacion::entre($ana, $beto)->marcarLeida($ana);
        \Illuminate\Support\Facades\Cache::flush(); // el badge cachea 10s (TTL de la casa)

        $this->actingAs($ana)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('mensaje(s) sin leer');
    }

    // ---------------------------------------------------------------
    // Gates
    // ---------------------------------------------------------------

    public function test_sin_permiso_no_hay_chat(): void
    {
        $sinRol = User::factory()->create();

        // GET navegable = redirect + aviso; POST = 403 crudo (contrato de la casa).
        $this->actingAs($sinRol)->get(route('mensajes.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', AvisosError::SIN_PERMISO);

        $this->actingAs($sinRol)->post(route('mensajes.store'), ['destinatario_id' => 1, 'texto' => 'X'])
            ->assertForbidden();
    }

    public function test_el_hilo_ajeno_es_403(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $tercero = $this->usuario();
        $mensaje = $this->enviar($ana, $beto, 'Privado');

        // GET navegable = redirect + aviso (el handler D-014 convierte el 403
        // de un GET en vuelta amable al Inicio); POST = 403 crudo.
        $this->actingAs($tercero)->get(route('mensajes.show', $mensaje->conversacion_id))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($tercero)->post(route('mensajes.responder', $mensaje->conversacion_id), ['texto' => 'Intruso'])
            ->assertForbidden();
        $this->assertDatabaseMissing('mensajes', ['texto' => 'Intruso']);
    }

    // ---------------------------------------------------------------
    // Hilo
    // ---------------------------------------------------------------

    public function test_abrir_el_hilo_muestra_y_marca_leido(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $mensaje = $this->enviar($beto, $ana, 'Revisa la M2 cuando puedas');

        $conversacion = $mensaje->conversacion;
        $this->assertSame(1, $conversacion->fresh()->noLeidosDe($ana));

        $this->actingAs($ana)->get(route('mensajes.show', $conversacion))
            ->assertOk()
            ->assertSee('Revisa la M2 cuando puedas');

        $this->assertSame(0, $conversacion->fresh()->noLeidosDe($ana));
    }

    public function test_responder_crea_el_mensaje_y_avisa_al_otro(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $mensaje = $this->enviar($beto, $ana, 'Hola');
        $conversacion = $mensaje->conversacion;

        Queue::fake();
        $this->actingAs($ana)->post(route('mensajes.responder', $conversacion), ['texto' => 'Hola Beto, voy'])
            ->assertRedirect(route('mensajes.show', $conversacion))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('mensajes', [
            'conversacion_id' => $conversacion->id,
            'emisor_id' => $ana->id,
            'texto' => 'Hola Beto, voy',
        ]);
        // La rafaga de MSG-1 sigue viva ENCIMA de la pantalla real.
        $this->assertSame(1, $conversacion->fresh()->noLeidosDe($beto));
        $this->assertSame(1, Notificacion::where('evento', 'mensaje.recibido')
            ->where('user_id', $beto->id)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->count());
    }

    public function test_el_texto_del_mensaje_va_escapado(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $mensaje = $this->enviar($beto, $ana, '<script>alert(1)</script>');

        // XSS (candado del dictado): burbujas por {{ }}, jamas {!! !!}.
        $this->actingAs($ana)->get(route('mensajes.show', $mensaje->conversacion_id))
            ->assertOk()
            ->assertSee('<script>alert(1)</script>')          // escapado por e()
            ->assertDontSee('<script>alert(1)</script>', false); // crudo, jamas
    }

    public function test_el_hilo_pagina_de_a_cincuenta(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();

        for ($i = 1; $i <= 51; $i++) {
            $this->enviar($beto, $ana, 'Mensaje numero '.$i);
        }

        // Pagina 1 = los 50 mas recientes; el mensaje 1 quedo en la pagina 2.
        // La exclusion se verifica en el PAGINADOR (viewData), no en el HTML:
        // la campanita del shell muestra el extracto del mensaje 1 (la rafaga
        // disparo con el) y un assertDontSee del texto pasaria/fallaria por
        // la razon equivocada (doctrina verde-engañoso).
        $this->actingAs($ana)->get(route('mensajes.show', Conversacion::entre($ana, $beto)))
            ->assertOk()
            ->assertSee('Mensaje numero 51')
            ->assertViewHas('mensajes', fn ($p) => $p->total() === 51
                && $p->count() === 50
                && collect($p->items())->pluck('texto')->doesntContain('Mensaje numero 1'));
    }

    public function test_texto_sobre_el_tope_es_error_de_validacion(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $mensaje = $this->enviar($beto, $ana, 'Hola');

        $this->actingAs($ana)
            ->from(route('mensajes.show', $mensaje->conversacion_id))
            ->post(route('mensajes.responder', $mensaje->conversacion_id), ['texto' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('texto');
    }

    // ---------------------------------------------------------------
    // Nuevo mensaje
    // ---------------------------------------------------------------

    public function test_nuevo_mensaje_excluye_al_propio_usuario_y_crea_el_hilo(): void
    {
        $ana = $this->usuario('Ana Riquelme');
        $beto = $this->usuario('Beto Soto');

        // El selector no me ofrece a mi mismo. Por viewData y no por
        // assertDontSee del nombre: la sidebar del shell pinta el nombre del
        // usuario logueado en toda pagina (verde-engañoso al reves).
        $this->actingAs($ana)->get(route('mensajes.create'))
            ->assertOk()
            ->assertSee('Beto Soto')
            ->assertViewHas('destinatarios', fn ($d) => $d->contains('id', $beto->id)
                && $d->doesntContain('id', $ana->id));

        // POST a mi mismo: rechazado por validacion.
        $this->actingAs($ana)
            ->from(route('mensajes.create'))
            ->post(route('mensajes.store'), ['destinatario_id' => $ana->id, 'texto' => 'Hola yo'])
            ->assertSessionHasErrors('destinatario_id');

        // POST valido: crea hilo + mensaje y redirige al hilo.
        Queue::fake();
        $this->actingAs($ana)->post(route('mensajes.store'), ['destinatario_id' => $beto->id, 'texto' => 'Hola Beto'])
            ->assertRedirect(route('mensajes.show', Conversacion::entre($ana, $beto)))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('mensajes', ['emisor_id' => $ana->id, 'texto' => 'Hola Beto']);
    }

    // ---------------------------------------------------------------
    // El guard de MSG-1 encendido solo (candado estrella del dictado)
    // ---------------------------------------------------------------

    public function test_la_campanita_de_un_mensaje_ahora_navega_al_hilo(): void
    {
        // En MSG-1 el destino era null (guard Route::has, ruta inexistente).
        // Registrar mensajes.show lo enciende SOLO — sin tocar Notificacion.
        $ana = $this->usuario();
        $beto = $this->usuario();
        $mensaje = $this->enviar($ana, $beto, 'Hola');

        $notificacion = Notificacion::where('evento', 'mensaje.recibido')
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->firstOrFail();

        $this->assertSame(
            route('mensajes.show', $mensaje->conversacion_id),
            $notificacion->urlDestinoPara($beto),
        );
    }
}
