<?php

namespace Tests;

use App\Support\AvisosError;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reloj determinista para TODA la suite: mediodía UTC de la fecha real
        // (= 08:00/09:00 en Chile, misma fecha en ambas zonas). Sin esto, una
        // corrida entre 00:00 y 04:00 UTC (la noche chilena) cae en días
        // distintos entre now() y el día de negocio (P-TZ-01) y los fixtures
        // "de hoy" fallan — flaky por reloj, la clase de bug de la bitácora
        // [2026-07-13]. Un test que necesite otro instante viaja encima con
        // travelTo() (p. ej. FechaNegocioTest congela la frontera nocturna).
        $this->travelTo(now('UTC')->setTime(12, 0));
    }

    /**
     * Denegacion de una NAVEGACION (GET) por permiso o por propiedad: el handler
     * de bootstrap/app.php la manda al Inicio con la mini-notificacion.
     *
     * El candado sigue siendo la DENEGACION, no el redirect: session('aviso') la
     * escribe UNICAMENTE ese handler (ninguna otra parte del repo usa esa clave),
     * asi que asertarla prueba que se denego — y ademas por que.
     */
    protected function assertSinPermiso(TestResponse $response): void
    {
        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', AvisosError::SIN_PERMISO);
    }

    /**
     * Rechazo por el ESTADO del recurso (no por permiso): conserva el mensaje
     * del abort(403, '...'), que es copy de negocio.
     */
    protected function assertRechazado(TestResponse $response, string $mensaje): void
    {
        $response->assertRedirect()->assertSessionHas('aviso', $mensaje);
    }
}
