<?php

namespace Tests\Unit;

use App\Support\CodigoIncidente;
use Tests\TestCase;

class CodigoIncidenteTest extends TestCase
{
    public function test_el_alfabeto_no_tiene_caracteres_que_se_confundan_al_dictar(): void
    {
        // El usuario dicta esto por telefono: nada de 0/O, 1/I/L ni U.
        for ($i = 0; $i < 200; $i++) {
            $this->app->forgetInstance(\Illuminate\Log\Context\Repository::class);
            $codigo = CodigoIncidente::deEstaPeticion();

            $this->assertMatchesRegularExpression(CodigoIncidente::patron(), $codigo);
            $this->assertSame(6, strlen($codigo));
            $this->assertSame(0, preg_match('/[0O1ILU]/', $codigo), "El codigo {$codigo} trae un caracter ambiguo.");
        }
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
}
