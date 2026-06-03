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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 (token CSRF / sesion expirada): en lugar de la pagina cruda
        // "Page Expired", devolver al formulario con un mensaje claro y
        // conservando lo escrito (menos las contrasenas).
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('status', 'Tu sesión expiró por seguridad. Por favor, vuelve a intentarlo.');
        });
    })->create();
