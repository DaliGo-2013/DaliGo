<?php

namespace Tests\Feature\Carga;

use App\Services\Carga\CalculoDeCarga;
use PHPUnit\Framework\TestCase;

/**
 * Candados del motor del simulador de carga.
 *
 * El error que estos tests existen para impedir es UNO: prometer carga que no
 * cabe. Un simulador que exagera manda al vendedor a comprometerse con un cliente
 * y la carga queda en el andén — peor que no tener la herramienta.
 *
 * Los números de referencia salen de las medidas reales que entregó el dueño el
 * 04-08-2026 y se cruzaron con un cálculo independiente en Python antes de
 * escribir el servicio.
 */
class CalculoDeCargaTest extends TestCase
{
    private CalculoDeCarga $calc;

    /** Hyundai HD35: 4,30 × 2,00 × 2,20 m (medidas del dueño). */
    private const HD35 = ['largo' => 430, 'ancho' => 200, 'alto' => 220, 'peso_max_kg' => null, 'pasillo' => 0];

    /** HINO 500: 7,97 × 2,60 × 2,66 m. */
    private const HINO = ['largo' => 797, 'ancho' => 260, 'alto' => 266, 'peso_max_kg' => null, 'pasillo' => 0];

    /** Bolsa de 5 botellones de 20 L, acostada: 130 × 26 × 51 cm. */
    private const BOLSA20 = ['largo' => 130, 'ancho' => 26, 'alto' => 51, 'unidades' => 5, 'apilable_max' => 6];

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CalculoDeCarga;
    }

    /**
     * EL CANDADO PRINCIPAL: nunca dividir volúmenes.
     *
     * El volumen del HD35 (18,92 m³) dividido por el de la bolsa (0,1724 m³) da
     * 109 bolsas. La realidad son 84: los centímetros que sobran en cada
     * dimensión no sirven para nada. Si alguien "simplifica" el servicio a una
     * división de volúmenes, este test se pone rojo — y con razón, porque esas 25
     * bolsas de diferencia son 125 botellones prometidos que no entran.
     */
    public function test_no_divide_volumenes_la_rejilla_da_menos_y_es_la_correcta(): void
    {
        $r = $this->calc->cupo(self::HD35, self::BOLSA20);

        $porVolumen = (int) floor((4.30 * 2.00 * 2.20) / (1.30 * 0.26 * 0.51));

        $this->assertSame(84, $r['bultos']);
        $this->assertSame(420, $r['unidades']);
        $this->assertGreaterThan($r['bultos'], $porVolumen, 'La división de volúmenes SIEMPRE exagera.');
        $this->assertSame(['largo' => 3, 'ancho' => 7, 'alto' => 4], $r['rejilla']);
    }

    /** Segundo caso real, para que el primero no pase por casualidad. */
    public function test_capacidad_del_hino_500(): void
    {
        $r = $this->calc->cupo(self::HINO, self::BOLSA20);

        $this->assertSame(300, $r['bultos']);
        $this->assertSame(1500, $r['unidades']);
    }

    /**
     * La orientación fija nunca puede AUMENTAR el cupo, y cuando la posición
     * cargada no es la óptima tiene que recortarlo.
     *
     * Hallazgo del 04-08-2026: para la bolsa de botellones la orientación de la
     * práctica (largo 130 a lo largo de la caja, pico a la puerta) resulta ser
     * TAMBIÉN la mejor de las seis, así que ahí fija y libre empatan en 84. Es
     * buena noticia y conviene dejarla fijada: si mañana alguien invierte el orden
     * de las medidas al cargarlas, este test lo caza.
     */
    public function test_la_orientacion_fija_nunca_aumenta_el_cupo(): void
    {
        $libre = $this->calc->cupo(self::HD35, self::BOLSA20);
        $fija = $this->calc->cupo(self::HD35, self::BOLSA20 + ['orientacion_fija' => true]);

        $this->assertLessThanOrEqual($libre['bultos'], $fija['bultos']);
        $this->assertSame(84, $fija['bultos'], 'La posición de estiba real es la óptima para este bulto.');

        // Un bulto cargado en una posición mala SÍ debe perder capacidad.
        $malPuesto = ['largo' => 51, 'ancho' => 130, 'alto' => 26, 'unidades' => 5, 'apilable_max' => 6];
        $r = $this->calc->cupo(self::HD35, $malPuesto + ['orientacion_fija' => true]);
        $this->assertLessThan($libre['bultos'], $r['bultos']);
    }

    /** El tope de apilado manda sobre lo que la altura permitiría. */
    public function test_el_tope_de_apilado_recorta_aunque_sobre_altura(): void
    {
        $sinTope = $this->calc->cupo(self::HD35, self::BOLSA20 + ['orientacion_fija' => true]);
        $conTope = $this->calc->cupo(self::HD35, ['apilable_max' => 1] + self::BOLSA20 + ['orientacion_fija' => true]);

        $this->assertSame(4, $sinTope['rejilla']['alto']);
        $this->assertSame(1, $conTope['rejilla']['alto']);
        $this->assertSame(intdiv($sinTope['bultos'], 4), $conTope['bultos']);
    }

    /** Un bulto más grande que la caja no entra, y se dice qué dimensión lo impide. */
    public function test_un_bulto_mas_alto_que_la_caja_no_entra(): void
    {
        $jaula = ['largo' => 200, 'ancho' => 120, 'alto' => 260, 'orientacion_fija' => true];

        $r = $this->calc->cupo(self::HD35, $jaula);

        $this->assertSame(0, $r['bultos']);
        $this->assertSame(CalculoDeCarga::LIMITE_ALTO, $r['limite']);
    }

    /** El peso puede recortar el cupo aunque el espacio sobre. */
    public function test_el_peso_maximo_manda_cuando_el_bulto_es_pesado(): void
    {
        $veh = ['largo' => 430, 'ancho' => 200, 'alto' => 220, 'peso_max_kg' => 1000, 'pasillo' => 0];
        $pesado = ['largo' => 40, 'ancho' => 40, 'alto' => 40, 'peso' => 120.0, 'apilable_max' => 5];

        $r = $this->calc->cupo($veh, $pesado);

        $this->assertSame(8, $r['bultos'], '1000 kg / 120 kg = 8 bultos, aunque quepan muchos más.');
        $this->assertSame(CalculoDeCarga::LIMITE_PESO, $r['limite']);
    }

    /**
     * Con botellones VACÍOS el peso no debe ganar nunca: son ~1 kg cada uno. Es el
     * hallazgo que ordena todo el módulo y conviene que quede fijado.
     */
    public function test_con_botellones_vacios_el_limite_es_espacio_y_no_peso(): void
    {
        $veh = self::HD35;
        $veh['peso_max_kg'] = 1400;
        $bolsa = self::BOLSA20 + ['peso' => 5.0];   // 5 botellones vacíos ≈ 5 kg

        $r = $this->calc->cupo($veh, $bolsa);

        $this->assertSame(84, $r['bultos']);
        $this->assertNotSame(CalculoDeCarga::LIMITE_PESO, $r['limite']);
        $this->assertLessThan(1400, $r['peso_kg']);
    }

    /** El pasillo reservado no es capacidad: descuenta largo. */
    public function test_el_pasillo_reservado_baja_el_cupo(): void
    {
        $sin = $this->calc->cupo(self::HD35, self::BOLSA20);
        $con = $this->calc->cupo(['pasillo' => 130] + self::HD35, self::BOLSA20);

        $this->assertLessThan($sin['bultos'], $con['bultos']);
    }

    /** El factor de aprovechamiento solo puede bajar, nunca inventar capacidad. */
    public function test_el_factor_de_aprovechamiento_solo_recorta(): void
    {
        $base = $this->calc->cupo(self::HD35, self::BOLSA20);

        $this->assertSame(84, $this->calc->conFactor($base, 1.0)['bultos']);
        $this->assertSame(75, $this->calc->conFactor($base, 0.9)['bultos']);
        $this->assertSame(84, $this->calc->conFactor($base, 5.0)['bultos'], 'Un factor > 1 se recorta a 1.');
        $this->assertSame(0, $this->calc->conFactor($base, -1)['bultos']);
    }

    /**
     * El de 10 L rinde más que el de 20: dato de venta, no detalle técnico.
     *
     * OJO con el número: 1.000 y no 900. Un prototipo previo en metros con coma
     * flotante daba 900 porque `2.00 // 0.40` devuelve 4 en vez de 5 (0.40×5 no es
     * exactamente 2.0 en binario). Por eso el servicio trabaja en CENTÍMETROS
     * ENTEROS: la división entera no tiene ese problema.
     */
    public function test_el_botellon_de_10_l_rinde_mas_que_el_de_20(): void
    {
        $b10 = ['largo' => 110, 'ancho' => 21, 'alto' => 40, 'unidades' => 5, 'apilable_max' => 6];

        $r20 = $this->calc->cupo(self::HD35, self::BOLSA20);
        $r10 = $this->calc->cupo(self::HD35, $b10);

        $this->assertGreaterThan($r20['unidades'], $r10['unidades']);
        $this->assertSame(1000, $r10['unidades']);
    }
}
