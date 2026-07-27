<?php

namespace Tests;

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
     * Rechazo por el ESTADO del recurso (no por permiso): el handler de
     * bootstrap/app.php devuelve al formulario conservando el mensaje del
     * abort(403, '...'), que es copy de negocio (D-014).
     *
     * Nota de doctrina para los ~56 asserts de denegacion por PERMISO, escritos
     * inline como assertRedirect(route('dashboard')) + assertSessionHas('aviso'):
     * lo que prueba la denegacion es la CLAVE de sesion, porque `aviso` la
     * escribe UNICAMENTE ese handler — ninguna otra parte del repo la usa.
     */
    protected function assertRechazado(TestResponse $response, string $mensaje): void
    {
        $response->assertRedirect()->assertSessionHas('aviso', $mensaje);
    }
}
