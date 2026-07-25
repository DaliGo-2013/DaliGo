<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detras de LiteSpeed/cPanel (SSL terminado en el proxy): confiar en los
        // encabezados X-Forwarded-* para detectar correctamente el esquema HTTPS.
        $middleware->trustProxies(at: '*');

        // Alias de middleware de spatie/laravel-permission.
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // CODIGO DE INCIDENTE: la pagina errors/500 le muestra al usuario 6
        // caracteres para que los dicte a TI, y con este callback el MISMO codigo
        // queda en la linea de log de la excepcion.
        //
        // Va en context() y no en report(): los contextCallbacks corren DENTRO de
        // buildExceptionContext(), o sea en el array de contexto de esa misma
        // linea de log (Handler.php:399 y 532-556). Un report() tambien correria
        // antes del logger, pero para meter el codigo en el log habria que
        // ensuciar el logger global con Log::withContext(), que ademas es
        // inasertable con Log::spy().
        //
        // Los HttpException estan en $internalDontReport y report() sale por
        // shouldntReport() antes de llegar aca: un abort(500) explicito no
        // genera codigo (ni linea de log). Es correcto — no hay excepcion que
        // buscar — y la vista degrada sola.
        $exceptions->context(fn (\Throwable $e) => [
            'incidente' => \App\Support\CodigoIncidente::deEstaPeticion(),
        ]);

        // 419 (token CSRF / sesion expirada): Laravel convierte
        // TokenMismatchException en HttpException(419) ANTES de los render
        // callbacks, por eso se intercepta por status 419 (no por la clase).
        // En peticiones web devolvemos al formulario con un mensaje claro y
        // conservando lo escrito (menos contrasenas); como red de seguridad
        // existe ademas resources/views/errors/419.blade.php.
        // 403 / 404: en vez de la pantalla generica de Symfony (sin logo, sin
        // menu, sin salida, y con el texto de spatie en INGLES), al usuario que
        // NAVEGA lo llevamos al Inicio con una mini-notificacion que explica que
        // paso. Todo lo demas (JSON, visitantes, acciones) conserva su status y
        // cae en resources/views/errors/{403,404}.blade.php.
        //
        // Un solo callback sobre HttpException y switch por status: cuando la
        // excepcion llega aca, prepareException() ya convirtio TODO a
        // HttpException (AuthorizationException -> AccessDeniedHttpException,
        // ModelNotFoundException -> NotFoundHttpException, abort() -> HttpException),
        // asi que no hace falta un callback por clase ni preocuparse del orden.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419 && ! $request->expectsJson()) {
                return redirect()->back()
                    ->withInput($request->except(['password', 'password_confirmation', '_token']))
                    ->with('status', 'Tu sesión expiró por seguridad. Por favor, vuelve a intentarlo.');
            }

            $status = $e->getStatusCode();

            if ($status !== 403 && $status !== 404) {
                return null; // 405, 429, 500...: su curso normal.
            }

            // (1) PETICION DE MAQUINA: jamas se convierte en redirect. Critico
            // para la cola offline del soplador: offline-queue.js clasifica el
            // 403 como rechazo PERMANENTE; con un 302 el fetch seguiria el
            // redirect, veria resp.ok del dashboard y BORRARIA la tanda sin
            // haberla registrado. ajax() es la red por si algun fetch futuro
            // olvida el Accept; Sec-Fetch-Mode lo manda el navegador real (en
            // tests no existe, de ahi el default 'navigate').
            if ($request->expectsJson() || $request->ajax()) {
                return null;
            }

            if ($request->headers->get('Sec-Fetch-Mode', 'navigate') !== 'navigate') {
                return null;
            }

            // (2) VISITANTE SIN LOGIN (link firmado caducado del QR): no hay
            // Inicio al que llevarlo ni layout donde pintar el aviso.
            if (! $request->user() || ! $request->hasSession()) {
                return null;
            }

            // (3) ANTI-BUCLE: si el 403/404 lo lanza el PROPIO destino, redirigir
            // ahi seria un bucle infinito. Hoy /dashboard solo lleva 'auth' y no
            // hace abort(), pero esta linea es la que salva el dia que alguien le
            // ponga un permission:.
            if ($request->routeIs('dashboard')) {
                return null;
            }

            $mensaje = $status === 404
                ? \App\Support\AvisosError::NO_ENCONTRADO
                : \App\Support\AvisosError::para403($e);

            // (4) NAVEGACION AUTENTICADA (GET) -> al Inicio con el aviso.
            // Se loguea porque Laravel NO reporta los HttpException: sin esto, un
            // enlace roto en un Blade desapareceria en silencio. Un 403 de motivo
            // 'permiso' con referer interno = bug nuestro (mostramos un enlace que
            // no correspondia); un 404 con referer interno = enlace roto.
            if ($request->isMethod('GET')) {
                // url() y el referer SIN query a proposito: un fullUrl() se llevaria
                // al log el ?signature=/?expires= de las rutas firmadas (existe una
                // autenticada: verification.verify) y el referer crudo podria traer
                // una capability valida de un link firmado sin expiracion. Para
                // diagnosticar un enlace roto basta la ruta (gate R-31).
                \Illuminate\Support\Facades\Log::warning("HTTP {$status} redirigido al Inicio", [
                    'url' => $request->url(),
                    'referer' => strtok((string) $request->headers->get('referer'), '?') ?: null,
                    'user' => $request->user()->id,
                    'motivo' => \App\Support\AvisosError::motivo($e),
                ]);

                // route('dashboard'), NUNCA back(): StartSession::storeCurrentUrl()
                // guarda como "previous" la URL que acaba de fallar, asi que sin
                // Referer un back() volveria ahi mismo = bucle.
                return redirect()->route('dashboard')->with('aviso', $mensaje);
            }

            // (5) ACCION rechazada por el ESTADO del recurso ("Este reporte ya no
            // se puede editar."): ese texto es copy de negocio y hay que leerlo
            // JUNTO al formulario, asi que volvemos atras con el aviso. Aca back()
            // es seguro: el destino es una pagina GET que si cargo.
            if ($status === 403 && \App\Support\AvisosError::tieneMensajePropio($e)) {
                return back()
                    ->withInput($request->except(['password', 'password_confirmation', '_token']))
                    ->with('aviso', $mensaje);
            }

            // (6) Resto (una accion rechazada por permiso: un boton que no debio
            // existir) -> se queda en su status, con la pagina de marca.
            return null;
        });
    })->create();
