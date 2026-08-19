<?php

namespace Tests\Feature\Mensajes;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\Mensajes\Mensajeria;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * MSG-1 (PLAN-MENSAJES): el corazon del chat interno sin UI — par canonico,
 * contadores de no-leidos por lado, envio bajo lock y el anti-spam de RAFAGA
 * sobre el evento M15 `mensaje.recibido`.
 */
class MensajeriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(): User
    {
        // Cualquier rol trae 'usar mensajes' (todos con todos); member es el
        // rol minimo y basta.
        return tap(User::factory()->create())->assignRole('member');
    }

    private function mensajeria(): Mensajeria
    {
        return app(Mensajeria::class);
    }

    // ---------------------------------------------------------------
    // Par canonico
    // ---------------------------------------------------------------

    public function test_entre_es_canonico_en_ambos_sentidos(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();

        $ida = Conversacion::entre($ana, $beto);
        $vuelta = Conversacion::entre($beto, $ana);

        $this->assertSame($ida->id, $vuelta->id);
        $this->assertSame(min($ana->id, $beto->id), $ida->user_menor_id);
        $this->assertSame(max($ana->id, $beto->id), $ida->user_mayor_id);
        $this->assertDatabaseCount('conversaciones', 1);
    }

    public function test_el_unique_aguanta_el_duplicado_directo(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        Conversacion::entre($ana, $beto);

        // La red final: un insert que esquive entre() choca con el unique.
        $this->expectException(QueryException::class);
        Conversacion::create([
            'user_menor_id' => min($ana->id, $beto->id),
            'user_mayor_id' => max($ana->id, $beto->id),
        ]);
    }

    public function test_conversacion_conmigo_mismo_rechazada(): void
    {
        $ana = $this->usuario();

        $this->expectException(InvalidArgumentException::class);
        Conversacion::entre($ana, $ana);
    }

    // ---------------------------------------------------------------
    // Enviar
    // ---------------------------------------------------------------

    public function test_enviar_crea_el_mensaje_y_mueve_el_contador_del_otro(): void
    {
        Queue::fake();
        $ana = $this->usuario();
        $beto = $this->usuario();

        $mensaje = $this->mensajeria()->enviar($ana, $beto, 'Hola Beto, ¿llegaron las preformas?');

        $conversacion = $mensaje->conversacion->fresh();

        $this->assertDatabaseHas('mensajes', [
            'conversacion_id' => $conversacion->id,
            'emisor_id' => $ana->id,
            'texto' => 'Hola Beto, ¿llegaron las preformas?',
        ]);
        // El contador que se mueve es el del RECEPTOR; el mio queda intacto.
        $this->assertSame(1, $conversacion->noLeidosDe($beto));
        $this->assertSame(0, $conversacion->noLeidosDe($ana));
        $this->assertNotNull($conversacion->ultimo_mensaje_at);
    }

    public function test_texto_vacio_o_sobre_el_tope_rechazado(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();

        try {
            $this->mensajeria()->enviar($ana, $beto, '   ');
            $this->fail('El texto vacío debió rechazarse.');
        } catch (InvalidArgumentException) {
        }

        try {
            $this->mensajeria()->enviar($ana, $beto, str_repeat('a', Mensaje::TEXTO_MAX + 1));
            $this->fail('El texto sobre el tope debió rechazarse.');
        } catch (InvalidArgumentException) {
        }

        $this->assertDatabaseCount('mensajes', 0);
    }

    // ---------------------------------------------------------------
    // RAFAGA (anti-spam del anexo §5.3) — el candado con cifra del dictado
    // ---------------------------------------------------------------

    public function test_rafaga_tres_mensajes_avisan_una_sola_vez(): void
    {
        Queue::fake();
        $ana = $this->usuario();
        $beto = $this->usuario();

        $this->mensajeria()->enviar($ana, $beto, 'Mensaje 1');
        $this->mensajeria()->enviar($ana, $beto, 'Mensaje 2');
        $this->mensajeria()->enviar($ana, $beto, 'Mensaje 3');

        // UNA notificacion de campanita (y una de mail) para tres mensajes:
        // la 2ª y 3ª callan porque Beto ya tiene la campanita encendida.
        $this->assertSame(1, Notificacion::where('evento', 'mensaje.recibido')
            ->where('user_id', $beto->id)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->count());

        // Beto lee (contador a 0) → el 4º mensaje SI vuelve a avisar.
        Conversacion::entre($ana, $beto)->marcarLeida($beto);
        $this->mensajeria()->enviar($ana, $beto, 'Mensaje 4');

        $this->assertSame(2, Notificacion::where('evento', 'mensaje.recibido')
            ->where('user_id', $beto->id)
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->count());
    }

    public function test_la_rafaga_es_por_lado_no_por_hilo(): void
    {
        Queue::fake();
        $ana = $this->usuario();
        $beto = $this->usuario();

        // Ana escribe (aviso a Beto) y Beto responde SIN leer... al responder
        // el contador de ANA estaba en 0 → Ana recibe su propio aviso. Cada
        // lado tiene su rafaga independiente.
        $this->mensajeria()->enviar($ana, $beto, 'Hola');
        $this->mensajeria()->enviar($beto, $ana, 'Hola Ana');

        $this->assertSame(1, Notificacion::where('evento', 'mensaje.recibido')
            ->where('user_id', $beto->id)->where('canal', Notificacion::CANAL_DATABASE)->count());
        $this->assertSame(1, Notificacion::where('evento', 'mensaje.recibido')
            ->where('user_id', $ana->id)->where('canal', Notificacion::CANAL_DATABASE)->count());
    }

    public function test_la_plantilla_pinta_el_emisor_en_el_titulo(): void
    {
        Queue::fake();
        // La plantilla vive en configuracion (molde NotificacionConfigSeedTest);
        // sin sembrarla, el dispatcher cae al fallback nunca-mudo y el titulo
        // seria la etiqueta generica del evento.
        $this->seed(\Database\Seeders\ConfiguracionSeeder::class);
        $ana = tap($this->usuario())->update(['name' => 'Ana Riquelme']);
        $beto = $this->usuario();

        $this->mensajeria()->enviar($ana, $beto, 'Nos falta gas en la M2');

        $notificacion = Notificacion::where('evento', 'mensaje.recibido')
            ->where('canal', Notificacion::CANAL_DATABASE)->first();

        $this->assertNotNull($notificacion);
        $this->assertStringContainsString('Ana Riquelme', $notificacion->titulo);
        $this->assertStringContainsString('Nos falta gas en la M2', $notificacion->cuerpo);
    }

    // ---------------------------------------------------------------
    // Leer
    // ---------------------------------------------------------------

    public function test_marcar_leida_es_idempotente(): void
    {
        Queue::fake();
        $ana = $this->usuario();
        $beto = $this->usuario();
        $this->mensajeria()->enviar($ana, $beto, 'Hola');

        $conversacion = Conversacion::entre($ana, $beto);
        $conversacion->marcarLeida($beto);
        $conversacion->fresh()->marcarLeida($beto);

        $this->assertSame(0, $conversacion->fresh()->noLeidosDe($beto));
    }

    // ---------------------------------------------------------------
    // Aislamiento
    // ---------------------------------------------------------------

    public function test_para_usuario_solo_trae_mis_conversaciones(): void
    {
        $ana = $this->usuario();
        $beto = $this->usuario();
        $carla = $this->usuario();

        Conversacion::entre($ana, $beto);
        Conversacion::entre($beto, $carla);

        $deAna = Conversacion::paraUsuario($ana->id)->get();

        $this->assertCount(1, $deAna);
        $this->assertTrue($deAna->first()->esParticipante($ana));
    }

    // ---------------------------------------------------------------
    // Destino M15 (los DOS match + el guard Route::has)
    // ---------------------------------------------------------------

    private function notificacionDe(User $emisor, User $receptor): Notificacion
    {
        Queue::fake();
        $this->mensajeria()->enviar($emisor, $receptor, 'Hola');

        return Notificacion::where('evento', 'mensaje.recibido')
            ->where('canal', Notificacion::CANAL_DATABASE)->firstOrFail();
    }

    public function test_sin_la_ruta_de_msg2_el_destino_es_nulo_y_no_revienta(): void
    {
        // MSG-1 no registra rutas de pantalla: el guard Route::has evita el
        // RouteNotFoundException en la bandeja (la rama vive, apagada).
        $ana = $this->usuario();
        $beto = $this->usuario();

        $notificacion = $this->notificacionDe($ana, $beto);

        $this->assertNull($notificacion->urlDestino());
        $this->assertNull($notificacion->urlDestinoPara($beto));
    }

    public function test_con_la_ruta_el_participante_navega_y_el_tercero_no(): void
    {
        // La ruta que MSG-2 registrara, simulada aqui para probar la logica
        // real. El refresh es obligatorio: una ruta nombrada en runtime no
        // entra sola al lookup de nombres y Route::has() no la veria.
        Route::get('/mensajes/{conversacion}', fn () => 'ok')->name('mensajes.show');
        Route::getRoutes()->refreshNameLookups();

        $ana = $this->usuario();
        $beto = $this->usuario();
        $tercero = $this->usuario();
        $sinPermiso = User::factory()->create(); // sin rol → sin 'usar mensajes'

        $notificacion = $this->notificacionDe($ana, $beto);

        $this->assertNotNull($notificacion->urlDestino());
        $this->assertStringContainsString('/mensajes/', (string) $notificacion->urlDestinoPara($beto));
        $this->assertNull($notificacion->urlDestinoPara($tercero));
        $this->assertNull($notificacion->urlDestinoPara($sinPermiso));
    }

    // ---------------------------------------------------------------
    // Ciclo de vida de usuarios
    // ---------------------------------------------------------------

    public function test_eliminar_un_participante_se_lleva_su_hilo_y_no_los_ajenos(): void
    {
        // Comportamiento DECLARADO del anexo §5.1: el cascade se lleva el hilo
        // completo del usuario eliminado (en 1-a-1 el emisor siempre es
        // participante, asi que el nullOnDelete de emisor_id es solo cinturon
        // — el cascade de la conversacion gana). Los hilos ajenos, intactos.
        Queue::fake();
        $ana = $this->usuario();
        $beto = $this->usuario();
        $carla = $this->usuario();

        $this->mensajeria()->enviar($ana, $beto, 'Hola');
        $this->mensajeria()->enviar($carla, $beto, 'Hola Beto');

        $ana->delete();

        $this->assertDatabaseCount('conversaciones', 1);
        $this->assertDatabaseCount('mensajes', 1);
        $this->assertDatabaseHas('mensajes', ['emisor_id' => $carla->id]);
    }
}
