<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Services\Carga\RepartoPorEje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CUÁNTO DE LA CARGA CAE SOBRE CADA EJE (lote 5; los datos llegaron el 12-08-2026).
 *
 * Es una palanca de dos apoyos y la cuenta se puede hacer a mano — por eso los casos
 * de acá tienen números redondos y el resultado esperado escrito, no calculado por el
 * propio código. Lo que protegen:
 *
 *  1. que sin las DOS medidas no se muestre NADA (medio dato no da medio cálculo:
 *     da un número inventado con cara de medido);
 *  2. que la carga apoyada justo sobre un eje se la lleve entera ese eje;
 *  3. que correr la carga hacia atrás le pase peso al trasero — que es para lo que
 *     sirve la función;
 *  4. que la carga DETRÁS del eje trasero dé negativo en el delantero y se avise:
 *     no es un error de cuenta, es el camión levantando la trompa.
 */
class RepartoPorEjeTest extends TestCase
{
    use RefreshDatabase;

    /** Un camión con los ejes medidos: delantero en −50, trasero en +350 (400 entre ejes). */
    private function camion(?int $entreEjes = 400, ?int $ejeTrasero = 350): CamionSimulacion
    {
        // El nombre es unique en la tabla y varios casos crean más de un camión.
        return CamionSimulacion::create([
            'nombre' => 'Prueba '.CamionSimulacion::count(), 'largo_cm' => 600, 'ancho_cm' => 240, 'alto_cm' => 240,
            'peso_max_kg' => 10000, 'pasillo_cm' => 0, 'activo' => true,
            'entre_ejes_cm' => $entreEjes, 'eje_trasero_cm' => $ejeTrasero,
        ]);
    }

    /**
     * Un bloque de 100 cm de largo cuyo CENTRO cae en $centro, con $peso kg en total.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function bloque(int $centro, float $peso): array
    {
        $bloques = [[
            'linea' => 0, 'x' => $centro - 50, 'y' => 0, 'cantidad' => 1,
            'rejilla' => ['largo' => 1, 'ancho' => 1, 'alto' => 1],
            'orientacion' => ['largo' => 100, 'ancho' => 100, 'alto' => 100],
        ]];
        $lineas = [['bulto' => ['peso' => $peso]]];

        return [$bloques, $lineas];
    }

    public function test_sin_las_dos_medidas_no_se_calcula_nada(): void
    {
        [$b, $l] = $this->bloque(350, 1000);

        // Sin ninguna.
        $this->assertNull((new RepartoPorEje)->calcular($this->camion(null, null), $b, $l));
        // Con la distancia entre ejes pero sin saber dónde cae el trasero: no hay
        // contra qué medir el brazo de palanca.
        $this->assertNull((new RepartoPorEje)->calcular($this->camion(400, null), $b, $l));
        // Y al revés.
        $this->assertNull((new RepartoPorEje)->calcular($this->camion(null, 350), $b, $l));
    }

    public function test_la_carga_justo_sobre_un_eje_se_la_lleva_ese_eje(): void
    {
        // Centro en 350 = exactamente el eje trasero. Todo va ahí.
        [$b, $l] = $this->bloque(350, 1000);
        $r = (new RepartoPorEje)->calcular($this->camion(), $b, $l);

        $this->assertEqualsWithDelta(1000, $r['trasero_kg'], 0.1);
        $this->assertEqualsWithDelta(0, $r['delantero_kg'], 0.1);
        $this->assertSame(100, $r['trasero_pct']);
    }

    public function test_la_carga_en_el_medio_se_reparte_mitad_y_mitad(): void
    {
        // Los ejes están en −50 y 350: el punto medio es 150.
        [$b, $l] = $this->bloque(150, 1000);
        $r = (new RepartoPorEje)->calcular($this->camion(), $b, $l);

        $this->assertEqualsWithDelta(500, $r['delantero_kg'], 0.1);
        $this->assertEqualsWithDelta(500, $r['trasero_kg'], 0.1);
    }

    public function test_correr_la_carga_hacia_atras_le_pasa_peso_al_eje_trasero(): void
    {
        // Es LA razón de ser de la función: comparar dos formas de acomodar lo mismo.
        [$adelante, $l] = $this->bloque(50, 1000);
        [$atras] = $this->bloque(250, 1000);

        $a = (new RepartoPorEje)->calcular($this->camion(), $adelante, $l);
        $b = (new RepartoPorEje)->calcular($this->camion(), $atras, $l);

        $this->assertGreaterThan($a['trasero_kg'], $b['trasero_kg']);
        // Y el total no se mueve: es la misma carga, puesta en otro lado.
        $this->assertEqualsWithDelta($a['total_kg'], $b['total_kg'], 0.1);
        $this->assertEqualsWithDelta(1000, $b['total_kg'], 0.1);
    }

    public function test_la_carga_detras_del_eje_trasero_levanta_la_trompa_y_se_avisa(): void
    {
        // Centro en 450, o sea 100 cm DETRÁS del eje trasero (350). El delantero no
        // apoya: lo levantan. No se acota con un min() — el signo es la señal.
        [$b, $l] = $this->bloque(450, 1000);
        $r = (new RepartoPorEje)->calcular($this->camion(), $b, $l);

        $this->assertLessThan(0, $r['delantero_kg']);
        $this->assertGreaterThan(1000, $r['trasero_kg']);
        $this->assertTrue($r['aliviana_el_delantero']);
    }

    public function test_un_camion_vacio_no_devuelve_reparto(): void
    {
        // Sin bloques no hay nada que decir ni que explicar.
        $this->assertNull((new RepartoPorEje)->calcular($this->camion(), [], []));
    }

    /**
     * Un producto SIN PESO CARGADO no hace desaparecer la sección: la explica.
     *
     * La mitad del catálogo tiene `peso_kg` en null a propósito —no se inventan
     * números— así que un reparto puede salir incompleto o imposible. Antes eso
     * devolvía `null` y la sección desaparecía sin decir por qué: se veía en una carga
     * y no en la otra, y no había forma de saber la razón. Ahora vuelve con el nombre
     * del que falta pesar.
     */
    public function test_lo_que_no_tiene_peso_se_nombra_en_vez_de_esconder_la_seccion(): void
    {
        [$b, $l] = $this->bloque(150, 0);
        $r = (new RepartoPorEje)->calcular($this->camion(), $b, $l, [0 => 'Caja de tapas']);

        $this->assertNotNull($r, 'La sección no puede desaparecer sin explicación.');
        $this->assertSame(['Caja de tapas'], $r['sin_peso']);
        $this->assertSame(0.0, $r['total_kg']);
    }

    public function test_con_peso_conocido_no_se_reporta_nada_faltante(): void
    {
        [$b, $l] = $this->bloque(150, 1000);
        $r = (new RepartoPorEje)->calcular($this->camion(), $b, $l, [0 => 'Bolsa']);

        $this->assertSame([], $r['sin_peso']);
    }

    public function test_el_chevy_sembrado_es_el_unico_con_los_ejes_medidos(): void
    {
        // Los datos del 12-08 alcanzaron para UNO solo. Los otros tres quedaron sin
        // medir a propósito y sus notas dicen qué falta; este candado se pone rojo el
        // día que alguien los complete «para que funcione», que es justo lo que no hay
        // que hacer sin la medida.
        $this->seed(\Database\Seeders\CamionesSimulacionSeeder::class);

        $conEjes = CamionSimulacion::get()->filter(fn (CamionSimulacion $c) => $c->tieneEjes());

        $this->assertSame(['Chevy 3 (NQR 919 · H3)'], $conEjes->pluck('nombre')->all());
        $chevy = $conEjes->first();
        $this->assertSame(418, $chevy->entre_ejes_cm);
        $this->assertSame(360, $chevy->eje_trasero_cm);
        // El delantero cae ADELANTE del frente de la caja, o sea bajo la cabina.
        $this->assertSame(-58, $chevy->ejeDelanteroCm());
    }
}
