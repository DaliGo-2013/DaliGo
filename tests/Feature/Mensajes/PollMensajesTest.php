<?php

namespace Tests\Feature\Mensajes;

use App\Models\Conversacion;
use App\Models\User;
use App\Services\Mensajes\Mensajeria;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * MSG-3 (PLAN-MENSAJES §5.4): el refresco automático del chat — endpoint
 * `mensajes.conteo` con la firma barata del estado + poll de 20s SOLO en las
 * pantallas del chat, sobre el componente <x-poll-recarga> (extraído en este
 * lote; vivo y cola de bodega migraron a él con cero cambio de conducta).
 */
class PollMensajesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(): User
    {
        return tap(User::factory()->create())->assignRole('member');
    }

    private function enviar(User $de, User $a, string $texto): void
    {
        Queue::fake();
        app(Mensajeria::class)->enviar($de, $a, $texto);
    }

    // ---------------------------------------------------------------
    // Gates del endpoint nuevo
    // ---------------------------------------------------------------

    public function test_conteo_exige_sesion_y_permiso(): void
    {
        // Guest: a login.
        $this->get(route('mensajes.conteo'))->assertRedirect(route('login'));

        // Sin permiso, pidiendo JSON (como el poll): 403 crudo.
        $this->actingAs(User::factory()->create())
            ->getJson(route('mensajes.conteo'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // La firma: misma función para vista y endpoint
    // ---------------------------------------------------------------

    public function test_la_firma_de_la_lista_coincide_con_la_del_conteo(): void
    {
        // AJUSTADO en MSG-5: el hilo migro de firma-reload a fetch-append y ya
        // no hornea firma — el contrato misma-función queda entre la LISTA y
        // el conteo (los dos consumidores que siguen recargando por firma).
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');

        $firmaLista = $this->actingAs($ana)->get(route('mensajes.index'))->viewData('firmaChat');

        $this->actingAs($ana)->getJson(route('mensajes.conteo'))
            ->assertOk()
            ->assertJson(['firma' => $firmaLista]);
    }

    public function test_la_firma_cambia_con_mensaje_nuevo_y_no_sin_actividad(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');

        $antes = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');
        $quieta = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');
        $this->assertSame($antes, $quieta, 'Sin actividad la firma no puede moverse (el monitor recargaría en loop).');

        $this->enviar($beto, $ana, 'Otro mensaje');

        $despues = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');
        $this->assertNotSame($antes, $despues, 'Un mensaje nuevo debe mover la firma.');
    }

    public function test_la_firma_cambia_al_marcar_leido(): void
    {
        // El contador propio es PARTE de la firma: leer en otra pestaña (o
        // dispositivo) también refresca la lista abierta.
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');

        $antes = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');

        Conversacion::entre($ana, $beto)->marcarLeida($ana);

        $despues = $this->actingAs($ana)->getJson(route('mensajes.conteo'))->json('firma');
        $this->assertNotSame($antes, $despues, 'Marcar leído baja el contador y debe mover la firma.');
    }

    // ---------------------------------------------------------------
    // El poll vive SOLO donde corresponde
    // ---------------------------------------------------------------

    public function test_la_lista_recarga_por_firma_y_el_hilo_appendea(): void
    {
        // MSG-5: la lista sigue firma-reload (a 4 s) y el hilo emite su script
        // de append apuntando a `nuevos` — y YA NO el poll-recarga (un reload
        // le perderia el composer al que escribe). La URL viaja por @js(), que
        // escapa las barras — se des-escapa antes de comparar (bitacora
        // 2026-08-14) o el assert seria un candado inerte.
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');
        $conversacion = Conversacion::entre($ana, $beto);

        $lista = str_replace('\\/', '/', $this->actingAs($ana)->get(route('mensajes.index'))->assertOk()->getContent());
        $hilo = str_replace('\\/', '/', $this->actingAs($ana)->get(route('mensajes.show', $conversacion))->assertOk()->getContent());

        $this->assertStringContainsString(route('mensajes.conteo'), $lista);
        $this->assertStringContainsString('setInterval(comprobar, 4000)', $lista, 'La lista debe pollear a 4 s (QA del dueño).');
        $this->assertStringContainsString(route('mensajes.nuevos', $conversacion), $hilo);
        $this->assertStringNotContainsString(route('mensajes.conteo'), $hilo, 'El hilo ya no recarga por firma.');
    }

    public function test_el_script_de_append_solo_va_en_la_primera_pagina(): void
    {
        // En una pagina historica se LEE, no se conversa: sin append ahi.
        $ana = $this->usuario();
        $beto = $this->usuario();
        for ($i = 1; $i <= 51; $i++) {
            $this->enviar($beto, $ana, 'Mensaje '.$i);
        }
        $conversacion = Conversacion::entre($ana, $beto);

        $pagina2 = str_replace('\\/', '/', $this->actingAs($ana)
            ->get(route('mensajes.show', ['conversacion' => $conversacion, 'page' => 2]))
            ->assertOk()->getContent());

        $this->assertStringNotContainsString(route('mensajes.nuevos', $conversacion), $pagina2);
    }

    public function test_el_componente_de_poll_vive_exactamente_en_sus_cuatro_consumidores(): void
    {
        // Estructural: el poll firma-reload no corre fuera de sus pantallas y
        // la migración de vivo/cola no dejó copias inline del script. El poll
        // de ST (banner con delta, sin recarga) es OTRA conducta a propósito
        // y queda FUERA del componente (coexistencia declarada, precedente
        // del _tabs de ST con x-tab-nav).
        // MSG-5: el hilo salio del componente (append sin reload) — quedan 3.
        $esperados = [
            'admin/despachos/cola.blade.php',
            'admin/produccion/vivo.blade.php',
            'mensajes/index.blade.php',
        ];

        $conComponente = [];
        $conScriptInline = [];
        foreach (File::allFiles(resource_path('views')) as $archivo) {
            $ruta = str_replace('\\', '/', $archivo->getRelativePathname());
            $contenido = $archivo->getContents();
            if (str_contains($contenido, 'x-poll-recarga') && ! str_starts_with($ruta, 'components/')) {
                $conComponente[] = $ruta;
            }
            // 'document.visibilityState' y no 'visibilityState' a secas: el
            // comentario de cabecera de cola.blade nombra la palabra en PROSA.
            if (str_contains($contenido, 'document.visibilityState') && ! str_starts_with($ruta, 'components/')) {
                $conScriptInline[] = $ruta;
            }
        }
        sort($conComponente);

        $this->assertSame($esperados, $conComponente);
        // Scripts inline con visibilityState: el de ST (conducta banner-delta)
        // y el append del hilo (MSG-5) — cada uno OTRA conducta a proposito.
        $this->assertSame(
            ['admin/servicio-tecnico/index.blade.php', 'mensajes/show.blade.php'],
            $conScriptInline,
        );

        // Vivo y cola SIGUEN a 20 s (default del componente): cero :intervalo.
        foreach (['admin/despachos/cola.blade.php', 'admin/produccion/vivo.blade.php'] as $vista) {
            $this->assertStringNotContainsString(
                'intervalo',
                file_get_contents(resource_path('views/'.$vista)),
                "{$vista} debe seguir con el intervalo default de 20 s (conducta intacta).",
            );
        }
    }

    // ---------------------------------------------------------------
    // El endpoint de nuevos (MSG-5 — el chat vivo)
    // ---------------------------------------------------------------

    public function test_nuevos_exige_sesion_permiso_y_ser_participante(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');
        $conversacion = Conversacion::entre($ana, $beto);

        $this->getJson(route('mensajes.nuevos', $conversacion).'?desde=0')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(route('mensajes.nuevos', $conversacion).'?desde=0')
            ->assertForbidden();

        $this->actingAs($this->usuario())
            ->getJson(route('mensajes.nuevos', $conversacion).'?desde=0')
            ->assertForbidden();
    }

    public function test_nuevos_devuelve_solo_lo_posterior_pintado(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        Queue::fake();
        $mensajeria = app(\App\Services\Mensajes\Mensajeria::class);
        $primero = $mensajeria->enviar($beto, $ana, 'Mensaje viejo del hilo');
        $segundo = $mensajeria->enviar($beto, $ana, 'Mensaje nuevo que llega');
        $conversacion = Conversacion::entre($ana, $beto);

        $respuesta = $this->actingAs($ana)
            ->getJson(route('mensajes.nuevos', $conversacion).'?desde='.$primero->id)
            ->assertOk()
            ->assertJson(['ultimo' => $segundo->id]);

        $html = $respuesta->json('html');
        $this->assertStringContainsString('Mensaje nuevo que llega', $html);
        $this->assertStringNotContainsString('Mensaje viejo del hilo', $html);

        // Sin novedad: html vacio y el mismo puntero (el tick no hace nada).
        $this->actingAs($ana)
            ->getJson(route('mensajes.nuevos', $conversacion).'?desde='.$segundo->id)
            ->assertOk()
            ->assertJson(['ultimo' => $segundo->id, 'html' => '']);
    }

    public function test_nuevos_marca_leido_solo_cuando_trae(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, 'Hola');
        $conversacion = Conversacion::entre($ana, $beto);
        $ultimoId = (int) $conversacion->mensajes()->max('id');
        $this->assertSame(1, $conversacion->fresh()->noLeidosDe($ana));

        // Trae el nuevo → estas mirandolo → contador a 0 en el mismo request.
        $this->actingAs($ana)->getJson(route('mensajes.nuevos', $conversacion).'?desde=0')->assertOk();
        $this->assertSame(0, $conversacion->fresh()->noLeidosDe($ana));

        // Sin novedad no escribe (cero writes cada 4 s) — observado con un
        // contador reconstruido a mano que el tick vacio no debe tocar.
        $conversacion->update(['no_leidos_menor' => 5, 'no_leidos_mayor' => 5]);
        $this->actingAs($ana)->getJson(route('mensajes.nuevos', $conversacion).'?desde='.$ultimoId)->assertOk();
        $this->assertSame(5, $conversacion->fresh()->noLeidosDe($ana));
    }

    public function test_nuevos_escapa_el_texto(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->enviar($beto, $ana, '<script>alert(1)</script>');
        $conversacion = Conversacion::entre($ana, $beto);

        $html = $this->actingAs($ana)
            ->getJson(route('mensajes.nuevos', $conversacion).'?desde=0')
            ->assertOk()
            ->json('html');

        // El partial es el MISMO del render inicial: {{ }} escapa tambien aqui.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
