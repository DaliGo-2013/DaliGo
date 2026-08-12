<?php

namespace Tests\Feature\Carga;

use App\Services\Carga\CalculoDeCarga;
use Tests\TestCase;

/**
 * SEGUNDO PISO: un tipo apoyado ENCIMA de otro.
 *
 * Pedido del dueño (11-08-2026) mirando una carga que le dejó 200 botellones de 10 L
 * afuera: «lo más bien pueden agregarse arriba de los de 20 lts o al lado, porque son
 * livianos y no rompen nada».
 *
 * Da vuelta la regla 2 del credo (§2), que decía que el espacio sobre un bloque es
 * espacio muerto. Vale entender POR QUÉ se puede dar vuelta: esa regla no prohibía
 * apilar, prohibía **prometerlo sin una regla de soporte por kilo**. La regla llegó, y
 * sale de un dato que el catálogo ya traía curado producto por producto.
 *
 * Estos candados son sobre todo de PRUDENCIA: acá el error no se paga con un número
 * feo, se paga con mercadería rota abajo.
 */
class SegundoPisoTest extends TestCase
{
    /** Chevy 3 (NQR 919 · H3), medido con huincha. */
    private const CHEVY = ['largo' => 790, 'ancho' => 220, 'alto' => 230, 'peso_max_kg' => 6430];

    private const BOLSA20 = [
        'largo' => 130, 'ancho' => 26, 'alto' => 51, 'peso' => 3.75,
        'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true, 'orientacion_fija' => true,
    ];

    private const BOLSA10 = [
        'largo' => 110, 'ancho' => 21, 'alto' => 40, 'peso' => 2.5,
        'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true, 'orientacion_fija' => true,
    ];

    /** LB-93: 15,5 kg y su jaula rotulada «keep off». NO aguanta peso encima. */
    private const DISPENSADOR = [
        'largo' => 38, 'ancho' => 33, 'alto' => 98, 'peso' => 15.5,
        'unidades' => 1, 'apilable_max' => 2, 'soporta_peso_encima' => false,
    ];

    private CalculoDeCarga $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CalculoDeCarga;
    }

    /** @return list<array> los bloques que quedaron apoyados en altura */
    private function enAltura(array $resultado): array
    {
        return array_values(array_filter($resultado['bloques'], fn (array $b) => ($b['apoyo'] ?? 0) > 0));
    }

    /**
     * EL CASO DEL DUEÑO. Con el piso tapizado de bolsas de 20 L —192, cuatro de alto, 204
     * cm— quedan 26 cm de aire arriba, y ahí la bolsa de 10 L entra ACOSTADA (21 cm). Antes
     * de esto, esas bolsas quedaban afuera con motivo «no queda espacio», que era verdad
     * del piso y mentira del camión.
     */
    public function test_las_bolsas_de_10_suben_arriba_de_las_de_20(): void
    {
        $r = $this->calc->carga(self::CHEVY, [
            ['bulto' => self::BOLSA20, 'cantidad' => 192],
            ['bulto' => self::BOLSA10, 'cantidad' => 40],
        ], enOrdenDeLista: true);

        $this->assertSame(192, $r['lineas'][0]['colocados'], 'El piso dejó de llenarse igual que antes.');
        $this->assertGreaterThan(0, $r['lineas'][1]['colocados'], 'Las bolsas de 10 L volvieron a quedar todas afuera.');

        $arriba = $this->enAltura($r);
        $this->assertNotSame([], $arriba, 'No se apoyó nada en altura.');
        $this->assertSame(1, $arriba[0]['linea'], 'Lo que subió no es la línea liviana.');
        $this->assertSame(204, $arriba[0]['apoyo'], 'Apoya a 204 cm: cuatro capas de bolsas de 51.');
        // Entró GIRADA: de pie mide 40 y no cabría en los 26 cm que quedan.
        $this->assertSame(21, $arriba[0]['orientacion']['alto']);
    }

    /**
     * NADA SE APOYA SOBRE UN DISPENSADOR. Decisión del dueño el 11-08, preguntada
     * explícitamente: el catálogo los tiene como «no aguanta peso encima» y así quedan.
     * Si un embalaje cede, cede con la máquina adentro.
     */
    public function test_nada_se_apoya_sobre_un_dispensador(): void
    {
        // 108 dispensadores tapizan el piso del Chevy (20 × 6 de a 2 de alto) y dejan
        // 34 cm de aire arriba, donde la bolsa de 10 L acostada entraría de sobra.
        $r = $this->calc->carga(self::CHEVY, [
            ['bulto' => self::DISPENSADOR, 'cantidad' => 200],
            ['bulto' => self::BOLSA10, 'cantidad' => 40],
        ], enOrdenDeLista: true);

        $this->assertSame([], $this->enAltura($r),
            'Se apoyó carga sobre dispensadores, que están declarados como que NO aguantan peso encima.');
    }

    /**
     * Y AL REVÉS: un dispensador no sube a ningún techo. El flag se pide de los DOS lados
     * —abajo para saber si aguanta, arriba como proxy de que es liviano— y en este
     * catálogo las dos cosas coinciden: las bolsas pesan 3,75 kg y estas máquinas 15,5.
     */
    public function test_un_dispensador_no_sube_a_ningun_techo(): void
    {
        $r = $this->calc->carga(self::CHEVY, [
            ['bulto' => self::BOLSA20, 'cantidad' => 192],
            ['bulto' => self::DISPENSADOR, 'cantidad' => 10],
        ], enOrdenDeLista: true);

        $this->assertSame([], $this->enAltura($r), 'Un dispensador quedó apoyado sobre las bolsas.');
        $this->assertSame('espacio', $r['lineas'][1]['motivo']);
    }

    /**
     * UN SOLO PISO ARRIBA. Lo que se apoya no vuelve a ser techo: el tercer nivel no se
     * promete. Tres bloques que tapizan el piso entero en un camión de 3 m: el primero va
     * al piso, el segundo encima, y el tercero queda AFUERA aunque sobren 150 cm de aire.
     */
    public function test_un_solo_piso_arriba(): void
    {
        $veh = ['largo' => 200, 'ancho' => 100, 'alto' => 300, 'peso_max_kg' => null];
        $plancha = [
            'largo' => 200, 'ancho' => 100, 'alto' => 50, 'peso' => 1,
            'unidades' => 1, 'apilable_max' => 1, 'soporta_peso_encima' => true, 'orientacion_fija' => true,
        ];

        $r = $this->calc->carga($veh, [
            ['bulto' => $plancha, 'cantidad' => 1],
            ['bulto' => $plancha, 'cantidad' => 1],
            ['bulto' => $plancha, 'cantidad' => 1],
        ], enOrdenDeLista: true);

        $this->assertSame(1, $r['lineas'][0]['colocados']);
        $this->assertSame(1, $r['lineas'][1]['colocados'], 'El segundo no se apoyó sobre el primero.');
        $this->assertSame(0, $r['lineas'][2]['colocados'],
            'Se armó un TERCER nivel: lo que se apoya no puede volver a ser techo.');
        $this->assertSame(50, $this->enAltura($r)[0]['apoyo']);
    }

    /**
     * NO SE APOYA SOBRE LO QUE NO DECLARA NADA. El flag ausente se lee como «no aguanta»,
     * que es el lado seguro: los bultos que todavía no se midieron —las jaulas de las
     * máquinas— no van a recibir carga encima por olvido.
     */
    public function test_el_flag_ausente_se_lee_como_que_no_aguanta(): void
    {
        $veh = ['largo' => 200, 'ancho' => 100, 'alto' => 220, 'peso_max_kg' => null];
        $sinDeclarar = ['largo' => 200, 'ancho' => 100, 'alto' => 50, 'unidades' => 1, 'apilable_max' => 1, 'orientacion_fija' => true];
        $caja = ['largo' => 50, 'ancho' => 50, 'alto' => 50, 'unidades' => 1, 'apilable_max' => 3, 'soporta_peso_encima' => true];

        $r = $this->calc->carga($veh, [
            ['bulto' => $sinDeclarar, 'cantidad' => 1],
            ['bulto' => $caja, 'cantidad' => 5],
        ], enOrdenDeLista: true);

        $this->assertSame([], $this->enAltura($r));
        $this->assertSame(0, $r['lineas'][1]['colocados']);
    }

    /**
     * EL PUENTE ENTRE EL CATÁLOGO Y EL MOTOR, que es donde esto estuvo roto.
     *
     * `paraCalculo()` no mandaba `soporta_peso_encima`, así que el motor nunca veía techos
     * y el segundo piso quedaba MUERTO en la app — con sus seis candados en verde, porque
     * ahí el bulto se arma a mano y sí trae el flag. Lo agarró la verificación en el
     * navegador, no la suite: el lienzo seguía dibujando 192 bolsas y ninguna arriba.
     *
     * La lección: un candado sobre el motor no prueba que la PANTALLA use el motor. El
     * puente también necesita el suyo.
     */
    public function test_para_calculo_manda_si_aguanta_peso_encima(): void
    {
        $bolsa = new \App\Models\TipoBulto([
            'nombre' => 'Bolsa', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 3.75,
            'unidades' => 5, 'apilable_max' => 30, 'soporta_peso_encima' => true, 'orientacion_fija' => true,
        ]);
        $maquina = new \App\Models\TipoBulto([
            'nombre' => 'Dispensador', 'categoria' => 'dispensadores',
            'largo_cm' => 38, 'ancho_cm' => 33, 'alto_cm' => 98, 'peso_kg' => 15.5,
            'unidades' => 1, 'apilable_max' => 2, 'soporta_peso_encima' => false, 'orientacion_fija' => false,
        ]);

        $this->assertTrue($bolsa->paraCalculo()['soporta_peso_encima'],
            'El catálogo dice que la bolsa aguanta peso encima y el motor no se está enterando.');
        $this->assertFalse($maquina->paraCalculo()['soporta_peso_encima']);
    }

    /**
     * EL PISO NO SE MOVIÓ. La pasada de altura solo AGREGA: mientras quede piso, el
     * resultado es el de siempre, y una línea sola sigue dando el cupo verificado (el
     * candado que sostiene los cuatro números de referencia del dueño).
     */
    public function test_mientras_quede_piso_no_cambia_ningun_numero(): void
    {
        $solo = $this->calc->carga(self::CHEVY, [['bulto' => self::BOLSA20, 'abierta' => true]]);

        $this->assertSame([], $this->enAltura($solo),
            'Se apiló sobre sí mismo: una línea sola tiene que dar exactamente el cupo.');
        $this->assertSame(
            $this->calc->cupo(self::CHEVY, self::BOLSA20)['bultos'],
            $solo['lineas'][0]['colocados'],
        );
    }
}
