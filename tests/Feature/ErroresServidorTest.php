<?php

namespace Tests\Feature;

use App\Support\CodigoIncidente;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Paginas de error del servidor y de la familia que no cubria el lote de 403/404:
 * 500 (con codigo de incidente), 429, 503 y los comodines 4xx/5xx.
 *
 * Antes de esto, el vendor de Laravel servia sus propias vistas EN INGLES
 * ("500 | Server Error", "429 | Too Many Requests" — Title Case en el HTML) y los status que el vendor no
 * trae (405, 400, 502...) caian en la pantalla generica de Symfony.
 *
 * Cada test fija app.debug explicitamente: no se depende del .env de la maquina
 * (hoy la suite corre con debug=true, y de eso depende que camino toma un 500).
 * Log::spy() ademas evita escribir en el laravel.log de quien corre la suite.
 */
class ErroresServidorTest extends TestCase
{
    /** Ruta que revienta con una excepcion REAL (no un abort). */
    private function rutaQueRevienta(string $mensaje = 'SQLSTATE[42S02] la tabla_secreta no existe'): void
    {
        Route::middleware('web')->get('/zz-boom', fn () => throw new \RuntimeException($mensaje));
    }

    // --- 500: el camino de PRODUCCION ---------------------------------------

    public function test_una_excepcion_real_en_produccion_muestra_la_pagina_con_marca(): void
    {
        Log::spy();
        config(['app.debug' => false]); // en prod es false (verificado por sonda HTTP)
        $this->rutaQueRevienta();

        $this->get('/zz-boom')
            ->assertStatus(500)
            ->assertSee('Algo falló de nuestro lado')
            ->assertSee('Ir al Inicio')
            // Nada del error real: el mensaje del HttpException(500) ES el original.
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('tabla_secreta')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('Server Error');
    }

    public function test_el_codigo_que_ve_el_usuario_es_el_que_quedo_en_el_log(): void
    {
        // EL candado del feature: si el codigo mostrado y el logueado no son el
        // mismo, TI no encuentra nada y la pagina miente.
        Log::spy();
        config(['app.debug' => false]);
        $this->rutaQueRevienta();

        $html = $this->get('/zz-boom')->assertStatus(500)->getContent();

        $this->assertSame(1, preg_match('/data-incidente>([^<]+)</', $html, $m),
            'La pagina del 500 no mostro un codigo de incidente.');
        $this->assertMatchesRegularExpression(CodigoIncidente::patron(), $m[1]);

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn ($mensaje, $contexto = []) => ($contexto['incidente'] ?? null) === $m[1]
        );
    }

    public function test_un_abort_500_no_inventa_un_codigo_de_incidente(): void
    {
        // Asimetria deliberada: los HttpException estan en $internalDontReport, no
        // se reportan y por lo tanto NO hay linea de log que buscar. Mostrar un
        // codigo mandaria a TI a buscar algo inexistente.
        // UNA sola peticion en el test: dentro del mismo test el Context se
        // comparte entre requests y un codigo previo contaminaria el assert.
        Log::spy();
        Route::middleware('web')->get('/zz-abort-500', fn () => abort(500));

        $this->get('/zz-abort-500')
            ->assertStatus(500)
            ->assertSee('Algo falló de nuestro lado')
            ->assertDontSee('data-incidente', false)
            ->assertDontSee('Código del incidente');

        Log::shouldNotHaveReceived('error');
    }

    public function test_ninguna_pagina_de_error_depende_de_route_o_de_vite(): void
    {
        // Candado ESTRUCTURAL sobre TODAS las vistas de error y el shell (antes
        // cubria solo la 500: el gate R-31 probo que cambiar url() por route() en
        // los comodines 4xx/5xx dejaba la suite verde, y que con la ruta dashboard
        // ausente la vista LANZA y en produccion vuelve la pantalla generica).
        //
        // @auth NO esta en la lista general: el 403 y el 429 lo usan a proposito
        // (ahi la app esta sana y hay sesion). En la 500 si esta prohibido, porque
        // la sesion vive en MySQL, que es la causa mas probable de un 500.
        $vistas = array_merge(
            glob(resource_path('views/errors/*.blade.php')),
            [resource_path('views/components/errors/shell.blade.php')],
        );
        $this->assertGreaterThanOrEqual(10, count($vistas));

        foreach ($vistas as $archivo) {
            $codigo = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($archivo));
            $nombre = basename($archivo);

            foreach (['route(', '@vite', 'getMessage()'] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $codigo,
                    "errors/{$nombre} no puede usar {$prohibido}: una pagina de error no puede depender de que la app este sana.");
            }

            if ($nombre === '500.blade.php') {
                foreach (['@auth', 'Auth::'] as $prohibido) {
                    $this->assertStringNotContainsString($prohibido, $codigo,
                        "errors/500 no puede usar {$prohibido}: la sesion vive en MySQL, la causa mas probable de un 500.");
                }
            }
        }
    }

    public function test_el_429_sin_retry_after_no_dice_un_numero_inventado(): void
    {
        // El @else de la vista (un abort(429) a mano, sin header) no tenia candado:
        // el gate R-31 mostro que reescribirlo en ingles, o quitar la cota de 300,
        // dejaba la suite verde (y con la cota fuera, un abort(429) mostraba
        // "Espera 0 segundos").
        Route::middleware('web')->get('/zz-429-pelado', fn () => abort(429));

        $this->get('/zz-429-pelado')
            ->assertStatus(429)
            ->assertSee('Espera un minuto')
            ->assertDontSee('0 segundos')
            ->assertDontSee('Too Many');
    }

    public function test_el_429_con_una_espera_larga_no_promete_un_minuto(): void
    {
        // Hoy todos los throttle son de 1 minuto, asi que esto es preventivo: si
        // alguien agrega uno con decay mayor, la pagina no debe mentir hacia el
        // lado peligroso ("espera un minuto" cuando faltan horas).
        Route::middleware('web')->get('/zz-429-largo', function () {
            throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(3600);
        });

        $this->get('/zz-429-largo')
            ->assertStatus(429)
            ->assertSee('más tarde')
            ->assertDontSee('un minuto')
            ->assertDontSee('3600 segundos');
    }

    // --- 429: el cliente del QR --------------------------------------------

    public function test_el_429_habla_en_espanol_y_dice_cuanto_esperar(): void
    {
        // El reloj congelado del TestCase hace deterministico el Retry-After: el
        // limitador guarda timer = now+60 y availableIn() devuelve 60 exacto.
        Route::middleware(['web', 'throttle:1,1'])->get('/zz-lento', fn () => 'ok');

        $this->get('/zz-lento')->assertOk();

        $this->get('/zz-lento')
            ->assertStatus(429)
            ->assertSee('Vas muy rápido')
            ->assertSee('60 segundos')
            // Advertencia anti-duplicado: el bucket del throttle NO distingue la
            // ruta, asi que se puede caer en 429 DESPUES de enviar bien (las
            // paginas de "listo" estan en el mismo bucket). La pagina no puede
            // prometer que no se envio nada ni invitar a reenviar (gate R-31).
            ->assertSee('no lo envíes de nuevo', false)
            ->assertDontSee('tus datos siguen en el formulario')
            ->assertDontSee('envía el formulario otra vez')
            ->assertDontSee('Too Many')
            // Cliente del QR (invitado): ninguna salida a la app.
            ->assertDontSee('Ir al Inicio')
            ->assertDontSee(url('/dashboard'), false)
            ->assertDontSee(url('/login'), false);
    }

    public function test_la_ruta_publica_del_qr_devuelve_el_429_con_marca(): void
    {
        // Integracion sobre la ruta real (throttle:6,1). El throttle corre ANTES
        // del signed, asi que los primeros 6 dan 403 y el 7º el 429.
        $url = route('ingreso-taller.create');

        for ($i = 0; $i < 6; $i++) {
            $this->get($url)->assertForbidden();
        }

        $this->get($url)->assertStatus(429)->assertSee('Vas muy rápido');
    }

    // --- Comodines y 503 ----------------------------------------------------

    public function test_un_405_cae_en_el_comodin_4xx_con_marca(): void
    {
        // POST a /up: lo rechaza el ROUTER (esta registrada como GET por
        // withRouting(health:)), sin CSRF, sin controlador y sin efectos.
        $this->post('/up')
            ->assertStatus(405)
            ->assertSee('No pudimos procesar eso')
            // Ademas del <title> del shell: una cadena que SOLO vive en el cuerpo
            // (sin esto el test pasa aunque la pagina quede sin <h1> ni <p> —
            // mutacion probada en el gate del Director 2026-07-27).
            ->assertSee('La página o la acción no llegó como esperábamos')
            ->assertSee('Ir al Inicio')
            ->assertSee(url('/dashboard'), false)
            ->assertDontSee('MethodNotAllowed');
    }

    public function test_el_503_de_mantencion_no_ofrece_enlaces(): void
    {
        Route::middleware('web')->get('/zz-mantencion', fn () => abort(503));

        $this->get('/zz-mantencion')
            ->assertStatus(503)
            ->assertSee('Estamos en mantención')
            ->assertDontSee('Ir al Inicio')
            ->assertDontSee('Service Unavailable');
    }

    public function test_ninguna_pagina_de_error_filtra_el_mensaje_de_la_excepcion(): void
    {
        // Distinto del 403, donde AvisosError::tieneMensajePropio() preserva el
        // mensaje a proposito (es copy de negocio). Aca no hay nada que preservar.
        Route::middleware('web')->get('/zz-405-msg', fn () => abort(405, 'App\Models\Secreto linea 42'));
        Route::middleware('web')->get('/zz-502-msg', fn () => abort(502, 'upstream 10.0.0.7:3306 caido'));

        $this->get('/zz-405-msg')->assertStatus(405)
            ->assertDontSee('Secreto')->assertDontSee('linea 42');

        $this->get('/zz-502-msg')->assertStatus(502)
            ->assertDontSee('upstream')->assertDontSee('10.0.0.7');
    }

    // --- Invariante anti-ingles --------------------------------------------

    public function test_la_app_tapa_todas_las_vistas_de_error_del_vendor(): void
    {
        // Se auto-actualiza: si un composer update agrega una vista al vendor, este
        // test exige la nuestra. getHttpExceptionView() prueba errors::{status} y
        // PREFIERE la del vendor al comodin, asi que un archivo faltante = pagina
        // en ingles, sin que nada mas lo note.
        $vendor = glob(base_path('vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/*.blade.php'));
        $this->assertNotEmpty($vendor);

        // El namespace errors:: lo registra el Handler al renderizar; aca se hace lo
        // mismo para poder RENDERIZAR cada vista, no solo comprobar que el archivo
        // exista (el gate R-31 probo que con el assertFileExists solo, copiar las
        // vistas EN INGLES del vendor encima de 401/402/5xx dejaba la suite verde:
        // el defecto que este lote existe para cerrar podia reaparecer sin que nada
        // se pusiera rojo).
        (new \Illuminate\Foundation\Exceptions\RegisterErrorViewPaths)();

        foreach ($vendor as $archivo) {
            $status = basename($archivo, '.blade.php');

            if (! ctype_digit($status)) {
                continue; // layout, minimal
            }

            $this->assertFileExists(resource_path("views/errors/{$status}.blade.php"),
                "El vendor trae errors/{$status} EN INGLES y Laravel lo prefiere al comodin.");

            $html = view("errors::{$status}", [
                'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException((int) $status),
            ])->render();

            // Las del vendor heredan errors::minimal, que imprime el status y el
            // texto en ingles en Title Case (el MAYUSCULAS que se ve es CSS:
            // text-transform, no el HTML). Comparar contra MAYUSCULAS nunca
            // fallaba — hallazgo del gate del Director 2026-07-27.
            foreach (['Server Error', 'Too Many Requests', 'Not Found', 'Forbidden', 'Unauthorized', 'Service Unavailable', 'Payment Required'] as $ingles) {
                $this->assertStringNotContainsString($ingles, $html,
                    "errors/{$status} esta sirviendo la vista EN INGLES del vendor.");
            }

            $this->assertStringContainsString('DaliGo', $html, "errors/{$status} no lleva la marca DaliGo.");
        }

        // Los comodines: mismo trato (no los cubre el loop del vendor).
        foreach (['4xx' => 405, '5xx' => 502] as $comodin => $status) {
            $html = view("errors::{$comodin}", [
                'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException($status),
            ])->render();

            $this->assertStringContainsString('DaliGo', $html);
            $this->assertStringNotContainsString('Error', $html, "errors/{$comodin} parece estar en ingles.");
        }

        // El namespace errors:: lo registra el propio Handler al renderizar
        // (registerErrorViewPaths); aca se hace lo mismo para comprobar la
        // resolucion REAL de los comodines, no solo que el archivo exista.
        (new \Illuminate\Foundation\Exceptions\RegisterErrorViewPaths)();

        $this->assertTrue(view()->exists('errors::4xx'), 'Falta el comodin 4xx (405, 400, 410...).');
        $this->assertTrue(view()->exists('errors::5xx'), 'Falta el comodin 5xx (502, 504...).');
    }
}
