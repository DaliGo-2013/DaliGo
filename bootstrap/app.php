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
        // 419 (token CSRF / sesion expirada): Laravel convierte
        // TokenMismatchException en HttpException(419) ANTES de los render
        // callbacks, por eso se intercepta por status 419 (no por la clase).
        // En peticiones web devolvemos al formulario con un mensaje claro y
        // conservando lo escrito (menos contrasenas); como red de seguridad
        // existe ademas resources/views/errors/419.blade.php.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419 && ! $request->expectsJson()) {
                return redirect()->back()
                    ->withInput($request->except(['password', 'password_confirmation', '_token']))
                    ->with('status', 'Tu sesión expiró por seguridad. Por favor, vuelve a intentarlo.');
            }
        });
    })->create();
