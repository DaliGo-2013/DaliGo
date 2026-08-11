<?php

namespace Tests\Feature\Carga;

use App\Services\Carga\CalculoDeCarga;
use Tests\TestCase;

/**
 * LÍNEAS ABIERTAS: «lo que quepa».
 *
 * Nacen de fusionar las dos preguntas de la pantalla en una sola (pedido del dueño
 * 11-08-2026: «los dos apartados se repiten, sería bueno juntar ambas opciones en una
 * sola y que ahí se pueda calcular sobre un producto o varios»). La fusión se apoya en
 * una convención que ya existía en el formulario: el campo de cantidad vacío significa
 * «el máximo».
 *
 * Lo que estos candados protegen es que la fusión NO cambie ningún número:
 *  · una línea abierta y sola tiene que dar lo MISMO que `cupo()`, que es lo que
 *    reproduce los cuatro cupos de referencia del dueño;
 *  · el relleno no puede comerse la carga que ya está vendida;
 *  · «lo que quepa» no puede reportarse como carga que quedó afuera.
 */
class LineaAbiertaTest extends TestCase
{
    /** HD35 medido con huincha (§3.5bis). */
    private const HD35 = ['largo' => 430, 'ancho' => 200, 'alto' => 220, 'peso_max_kg' => 1500];

    /** La bolsa de 5 botellones de 20 L: el producto de los cupos de referencia. */
    private const BOLSA = [
        'largo' => 130, 'ancho' => 26, 'alto' => 51, 'peso' => 5,
        'unidades' => 5, 'apilable_max' => 6, 'orientacion_fija' => true,
    ];

    /** Caja de tapas, para las cargas de dos productos. */
    private const CAJA = [
        'largo' => 46, 'ancho' => 37, 'alto' => 42, 'peso' => 10,
        'unidades' => 1, 'apilable_max' => 6, 'orientacion_fija' => false,
    ];

    private CalculoDeCarga $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CalculoDeCarga;
    }

    /**
     * EL CANDADO QUE SOSTIENE LA FUSIÓN. Si una línea abierta y sola no da lo mismo que
     * `cupo()`, entonces juntar las dos pantallas cambió una respuesta verificada —y los
     * cuatro cupos que el dueño dictó el 04-08 dejan de reproducirse desde la pantalla
     * nueva, que es lo único que va a mirar de ahora en más.
     */
    public function test_una_linea_abierta_sola_da_lo_mismo_que_el_cupo_maximo(): void
    {
        $cupo = $this->calc->cupo(self::HD35, self::BOLSA);
        $carga = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA, 'abierta' => true]]);

        $this->assertSame(84, $cupo['bultos'], 'Cambió el cupo de referencia del HD35 (420 botellones de pie).');
        $this->assertSame($cupo['bultos'], $carga['lineas'][0]['colocados'],
            'La línea abierta y el cupo máximo dejaron de coincidir: la fusión de las dos pantallas cambia un número verificado.');
        $this->assertSame($cupo['unidades'], $carga['lineas'][0]['unidades_colocadas']);
    }

    public function test_el_relleno_no_se_come_la_carga_que_ya_esta_vendida(): void
    {
        // La abierta se escribe PRIMERO a propósito: es el caso que rompería si el motor
        // respetara el orden de la lista para esto. Las 20 cajas van vendidas.
        $r = $this->calc->carga(self::HD35, [
            ['bulto' => self::BOLSA, 'abierta' => true],
            ['bulto' => self::CAJA, 'cantidad' => 20],
        ], enOrdenDeLista: true);

        $this->assertSame(20, $r['lineas'][1]['colocados'],
            'El relleno se colocó antes que la carga con cantidad fija y la dejó afuera.');
        $this->assertNull($r['lineas'][1]['motivo']);
        $this->assertTrue($r['cabe_todo']);

        // Y la abierta se llevó lo que sobró: algo, pero menos que el camión vacío.
        $solo = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA, 'abierta' => true]]);
        $this->assertGreaterThan(0, $r['lineas'][0]['colocados']);
        $this->assertLessThan($solo['lineas'][0]['colocados'], $r['lineas'][0]['colocados'],
            'Con 20 cajas adentro tendrían que entrar menos bolsas que en el camión vacío.');
    }

    public function test_lo_que_quepa_no_se_reporta_como_carga_que_quedo_afuera(): void
    {
        // Pedir «lo que quepa» y que la pantalla después diga «quedaron 900 afuera» sería
        // inventar un incumplimiento: no se pidió una cantidad.
        $r = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA, 'abierta' => true]]);

        $this->assertTrue($r['lineas'][0]['abierta']);
        $this->assertNull($r['lineas'][0]['pedidos'], 'Una línea abierta no tiene cantidad pedida.');
        $this->assertNull($r['lineas'][0]['motivo'], 'La línea abierta reportó carga afuera.');
        $this->assertTrue($r['cabe_todo'], 'Una línea abierta no puede tumbar el «cabe todo».');
    }

    public function test_una_linea_abierta_dice_si_paro_por_espacio_o_por_kilos(): void
    {
        // Con botellones vacíos sobra el peso: para por espacio.
        $porEspacio = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA, 'abierta' => true]]);
        $this->assertSame('espacio', $porEspacio['lineas'][0]['lleno_por']);

        // Con un bulto pesado, el mismo camión para por kilos MUCHO antes. Es la
        // diferencia que importa: si paró por peso, mandar un camión más grande no
        // cambia nada.
        $plomo = ['largo' => 40, 'ancho' => 40, 'alto' => 40, 'peso' => 120, 'unidades' => 1, 'apilable_max' => 4];
        $porPeso = $this->calc->carga(self::HD35, [['bulto' => $plomo, 'abierta' => true]]);

        $this->assertSame(CalculoDeCarga::LIMITE_PESO, $porPeso['lineas'][0]['lleno_por']);
        $this->assertSame(12, $porPeso['lineas'][0]['colocados'], '1.500 kg / 120 kg = 12 bultos.');
    }

    public function test_dos_abiertas_se_llenan_en_el_orden_de_la_lista(): void
    {
        // La regla que la pantalla tiene que decir: con dos líneas sin cantidad, la
        // primera se lleva lo que quepa y la segunda lo que sobre. No hay reparto
        // proporcional — inventarlo sería prometer un acomodo que el motor no verifica.
        $r = $this->calc->carga(self::HD35, [
            ['bulto' => self::BOLSA, 'abierta' => true],
            ['bulto' => self::CAJA, 'abierta' => true],
        ], enOrdenDeLista: true);

        $solaBolsa = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA, 'abierta' => true]]);

        $this->assertSame($solaBolsa['lineas'][0]['colocados'], $r['lineas'][0]['colocados'],
            'La primera abierta tiene que llevarse lo mismo que si estuviera sola.');
        $this->assertTrue($r['cabe_todo']);
    }

    public function test_una_cantidad_en_cero_no_es_lo_mismo_que_vacia(): void
    {
        // El error tiene que caer del lado de colocar MENOS: una línea sin el flag y con
        // cantidad 0 no coloca nada, y jamás se lee como «llename el camión».
        $r = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA, 'cantidad' => 0]]);

        $this->assertSame(0, $r['lineas'][0]['colocados']);
        $this->assertFalse($r['lineas'][0]['abierta']);
        $this->assertSame(0, $r['lineas'][0]['pedidos']);
    }
}
