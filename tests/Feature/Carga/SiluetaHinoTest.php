<?php

namespace Tests\Feature\Carga;

use Tests\TestCase;

/**
 * La silueta del HINO 500 FC 1118, moldeada sobre las fotos de la flota.
 *
 * El dueño las va mandando de a una y pidiendo piezas: el 05-08 la cabina entera y el
 * 11-08 «ese techo arriba de la cabina, me imagino que es como un rompeviento» más
 * detalles en las puertas y los espejos. Cada pieza que llega es una decisión tomada
 * MIRANDO una foto, y el riesgo no es que se rompa —el lienzo no tiene tests de
 * píxeles— sino que alguien la borre o la generalice «para simplificar» y nadie se
 * entere hasta que el dibujo vuelva a no parecerse al camión.
 *
 * Son candados de FUENTE, no de dibujo: no hay Node en la suite, así que no se puede
 * ejecutar el visor. Sirven para lo que sirven —que la pieza siga estando y siga
 * midiéndose como se decidió— y no para juzgar si se ve bien; eso lo dice el dueño
 * mirando la pantalla.
 */
class SiluetaHinoTest extends TestCase
{
    private string $js;

    protected function setUp(): void
    {
        parent::setUp();
        $this->js = file_get_contents(resource_path('js/carga3d.js'));
    }

    /** El cuerpo de UNA función del visor: afirmar sobre todo el archivo daría falsos
     *  verdes con cualquier otra cabina que use la misma primitiva. */
    private function cuerpoDeFuncion(string $nombre): string
    {
        $desde = strpos($this->js, "function {$nombre}(");
        $this->assertNotFalse($desde, "No existe la función [{$nombre}] en el visor.");

        return substr($this->js, $desde, strpos($this->js, "\n    }\n", $desde) - $desde);
    }

    public function test_el_hino_lleva_el_rompeviento_del_techo(): void
    {
        $cabina = $this->cuerpoDeFuncion('cabinaHino');

        // Es una RAMPA y no un prisma parejo: un cajón sobre el techo se lee como el
        // dormitorio de un tracto, que este camión no tiene.
        $this->assertStringContainsString('rampa(', $cabina,
            'El rompeviento del techo dejó de ser una rampa (o desapareció).');
        $this->assertStringContainsString('const rampa =', $this->js,
            'Se perdió la primitiva `rampa`: `cuna` corre el techo en x pero lo deja horizontal.');
    }

    public function test_el_rompeviento_se_mide_contra_el_techo_del_furgon(): void
    {
        // La pieza NO puede asomar por encima de la caja: en las fotos muere justo abajo
        // del techo del furgón, y es lo que la hace leer como rompeviento. Por eso su
        // alto sale de `veh.alto` y no de un número escrito a mano — con un alto fijo,
        // una caja más baja dejaría el deflector flotando por arriba, que sería dibujar
        // un camión que no existe.
        $cabina = $this->cuerpoDeFuncion('cabinaHino');

        $this->assertMatchesRegularExpression('/altoDef\s*=\s*Math\.max\([^;]*veh\.alto/s', $cabina,
            'El alto del rompeviento dejó de medirse contra el techo de la caja.');
    }

    public function test_el_rompeviento_es_solo_del_hino(): void
    {
        // El HD35 y el NQR tienen el techo LISO en sus fotos (una ceja mínima el HD35,
        // nada el NQR). El tracto sí lleva deflector, pero el suyo es fino y plano —otra
        // pieza, otra foto—, así que tampoco usa esta rampa. Inventarle un rompeviento a
        // un camión que no lo tiene es el mismo error que sacó la puerta lateral del
        // furgón el 07-08.
        foreach (['cabinaLiviana', 'cabinaNqr', 'cabinaTracto'] as $sinRampa) {
            $this->assertStringNotContainsString('rampa(', $this->cuerpoDeFuncion($sinRampa),
                "La cabina [{$sinRampa}] no lleva el rompeviento del HINO: sus fotos no lo muestran.");
        }
    }

    public function test_los_espejos_del_hino_van_sobre_dos_tubos_y_con_convexo(): void
    {
        // Lo que se ve en las tres fotos: el soporte es de DOS tubos —uno casi al ras del
        // techo y otro que sale del parante de la puerta— y cuelga un convexo chico
        // debajo de la paleta grande. Con un solo brazo la paleta parecía flotar al
        // costado de la cabina, sobre todo en la vista de costado.
        $cabina = $this->cuerpoDeFuncion('cabinaHino');

        $this->assertStringContainsString('zChapa', $cabina,
            'Se perdió el tubo de abajo del espejo: sin él la paleta flota al costado.');

        // Cuatro piezas por lado dentro del mismo bucle: brazo de arriba, paleta grande,
        // tubo de abajo y convexo chico. Se cuentan los `prisma(` del bloque en vez de
        // los colores —que es lo primero que uno hace y da falsos verdes— para que
        // borrar una pieza se note.
        $desde = strpos($cabina, 'for (const z of [z0 - 0.30');
        $this->assertNotFalse($desde, 'Se movió el bucle de los espejos.');
        $bloque = substr($cabina, $desde, strpos($cabina, "\n        }\n", $desde) - $desde);

        $this->assertSame(4, preg_match_all('/prisma\(/', $bloque),
            'El conjunto del espejo dejó de tener sus cuatro piezas (brazo, paleta, tubo y convexo).');
    }

    public function test_las_cuatro_cabinas_siguen_teniendo_su_propia_funcion(): void
    {
        // La regla que sostiene todo lo de arriba: una cabina por camión, sin banderas.
        // Cada pieza que se agrega es «según la foto de ESE camión», y una función
        // compartida con `if (hino)` adentro convierte cada foto nueva en un parámetro
        // más hasta que ningún camión se parece a ninguno.
        foreach (['cabinaHino', 'cabinaLiviana', 'cabinaNqr', 'cabinaTracto'] as $cabina) {
            $this->assertStringContainsString("function {$cabina}(", $this->js);
        }
    }
}
