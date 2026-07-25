<?php

namespace Tests\Unit;

use App\Support\CodigoIncidente;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

class CodigoIncidenteTest extends TestCase
{
    /**
     * Olvida el Context para forzar un codigo NUEVO.
     *
     * forgetInstance() por si solo NO alcanza: el Facade cachea la instancia ya
     * resuelta en su estatica, asi que se seguia midiendo el MISMO codigo (el gate
     * R-31 probo que el test de alfabeto detectaba una regresion solo en 6 de 12
     * corridas — una moneda al aire).
     */
    private function olvidarContexto(): void
    {
        Context::clearResolvedInstances();
        $this->app->forgetInstance(\Illuminate\Log\Context\Repository::class);
    }

    public function test_el_alfabeto_no_tiene_caracteres_que_se_confundan_al_dictar(): void
    {
        // El usuario dicta esto por telefono: nada de 0/O, 1/I/L ni U.
        $vistos = [];

        for ($i = 0; $i < 200; $i++) {
            $this->olvidarContexto();
            $codigo = CodigoIncidente::deEstaPeticion();
            $vistos[$codigo] = true;

            $this->assertSame(6, strlen($codigo));
            $this->assertSame(0, preg_match('/[0O1ILU]/', $codigo), "El codigo {$codigo} trae un caracter ambiguo.");
        }

        // Si el reset no funcionara, esto seria 1 y el test de arriba mediria una
        // sola muestra 200 veces (verde-engañoso que cazo el gate R-31).
        $this->assertGreaterThan(150, count($vistos),
            'Se generaron '.count($vistos).' codigos distintos en 200 vueltas: el Context no se esta reseteando.');
    }

    public function test_es_el_mismo_codigo_dentro_de_la_misma_peticion(): void
    {
        // Si una peticion reporta dos excepciones, las dos lineas de log deben
        // llevar el MISMO codigo para que TI lea la historia junta.
        $primero = CodigoIncidente::deEstaPeticion();

        $this->assertSame($primero, CodigoIncidente::deEstaPeticion());
        $this->assertSame($primero, CodigoIncidente::actual());
    }

    public function test_sin_excepcion_reportada_no_hay_codigo(): void
    {
        // La vista del 500 usa esto para degradar (un abort(500) no se reporta).
        $this->assertNull(CodigoIncidente::actual());
    }

    public function test_el_codigo_va_en_contexto_OCULTO_para_no_ensuciar_todo_el_log(): void
    {
        // Propiedad load-bearing sin candado hasta el gate R-31: el contexto
        // VISIBLE lo inyecta ContextLogProcessor en el `extra` de TODAS las lineas
        // de log de la peticion, y aca solo interesa la de la excepcion. Con
        // remember()+get() (contexto visible) la suite quedaba entera verde.
        CodigoIncidente::deEstaPeticion();

        $this->assertSame([], Context::all(), 'El codigo no debe ir en el contexto visible.');
        $this->assertArrayHasKey('incidente', Context::allHidden());
    }
}
