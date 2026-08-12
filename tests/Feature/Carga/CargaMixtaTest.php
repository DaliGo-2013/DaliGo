<?php

namespace Tests\Feature\Carga;

use App\Services\Carga\CalculoDeCarga;
use PHPUnit\Framework\TestCase;

/**
 * Candados de la CARGA MIXTA («¿cabe esta carga?») — el caso textual del pedido
 * del dueño: 200 botellones + 20 cajas de tapas + 10 dispensadores → ¿entra?
 *
 * Mismo credo que CalculoDeCargaTest: el único error imperdonable es PROMETER
 * carga que no cabe. Todas las reglas del acomodo por zonas son conservadoras a
 * propósito (espacio sobre un bloque = muerto, bloque parcial reserva su caja
 * envolvente) y estos números salieron calculados A MANO antes que el código.
 */
class CargaMixtaTest extends TestCase
{
    /**
     * USAR TODO EL ESPACIO: el motor gira el bulto en lo que sobra.
     *
     * Pedido del dueño (10-08): «que se pueda cargar el camión completo hasta la
     * puerta y que se ocupe todo el espacio posible». A mano él pone el grueso
     * acostado y, en la franja de 40 cm de la puerta, las bolsas paradas y
     * cruzadas — de largo no entran.
     *
     * El PRIMER bloque conserva siempre la estiba pedida (es la que reproduce los
     * cupos verificados); solo los bloques que van en las regiones sobrantes
     * pueden girar. Y no relaja el credo: cada bloque extra sigue saliendo de una
     * rejilla exacta sobre una región real.
     */
    public function test_usar_todo_el_espacio_llena_lo_que_sobra_girando_el_bulto(): void
    {
        $hd35 = ['largo' => 430, 'ancho' => 204, 'alto' => 220, 'peso_max_kg' => null, 'pasillo' => 0];
        // Bolsa ACOSTADA de costado, apilando hasta el techo y sin rotación propia.
        $acostada = ['largo' => 130, 'ancho' => 51, 'alto' => 26, 'unidades' => 5,
            'apilable_max' => 8, 'orientacion_fija' => true];
        $linea = [['bulto' => $acostada, 'cantidad' => 400]];

        $sin = (new CalculoDeCarga)->carga($hd35, $linea);
        $con = (new CalculoDeCarga)->carga($hd35, $linea, false, true);

        // 96 bolsas es el bloque limpio (3 × 4 × 8); girando el sobrante entran más.
        $this->assertSame(96, $sin['lineas'][0]['colocados']);
        $this->assertGreaterThan($sin['lineas'][0]['colocados'], $con['lineas'][0]['colocados'],
            'Con «usar todo el espacio» no entró ni un bulto más.');

        // Y aparecen bloques EXTRA: son las regiones sobrantes, no un bloque inflado.
        $this->assertCount(1, $sin['bloques']);
        $this->assertGreaterThan(1, count($con['bloques']));
    }

    /**
     * APAGADO NO MUEVE NADA. Es lo que protege todos los números verificados y el
     * candado de consistencia entre las dos pestañas: la opción es opt-in, así que
     * quien no la toca ve exactamente lo de siempre.
     */
    public function test_apagado_da_el_mismo_resultado_de_siempre(): void
    {
        $hd35 = ['largo' => 430, 'ancho' => 204, 'alto' => 220, 'peso_max_kg' => null, 'pasillo' => 0];
        $bolsa = ['largo' => 130, 'ancho' => 26, 'alto' => 51, 'unidades' => 5,
            'apilable_max' => 6, 'orientacion_fija' => true];
        $linea = [['bulto' => $bolsa, 'cantidad' => 500]];

        $porDefecto = (new CalculoDeCarga)->carga($hd35, $linea);
        $explicito = (new CalculoDeCarga)->carga($hd35, $linea, false, false);

        $this->assertSame($porDefecto, $explicito);
        // Y sigue siendo el cupo máximo de pie: 84 bolsas = 420 botellones.
        $this->assertSame(84, $porDefecto['lineas'][0]['colocados']);
    }

    private CalculoDeCarga $calc;

    /** Hyundai HD35: 4,30 × 2,00 × 2,20 m (medidas del dueño). */
    private const HD35 = ['largo' => 430, 'ancho' => 200, 'alto' => 220, 'peso_max_kg' => null, 'pasillo' => 0];

    /** Bolsa de 5 botellones de 20 L, acostada (orientación de la práctica). */
    private const BOLSA20 = ['largo' => 130, 'ancho' => 26, 'alto' => 51, 'unidades' => 5, 'apilable_max' => 6, 'orientacion_fija' => true];

    /** Caja de tapas: 46 × 37 × 42, rota libre. */
    private const CAJA = ['largo' => 46, 'ancho' => 37, 'alto' => 42, 'unidades' => 1, 'apilable_max' => 6];

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CalculoDeCarga;
    }

    /**
     * EL CANDADO DE CONSISTENCIA: una carga de UN solo tipo, pedida de sobra,
     * tiene que dar EXACTAMENTE el cupo máximo de cupo(). Si los dos caminos
     * divergen, la pantalla se contradice a sí misma según la pestaña.
     */
    public function test_con_un_solo_tipo_da_lo_mismo_que_el_cupo_maximo(): void
    {
        $cupo = $this->calc->cupo(self::HD35, self::BOLSA20);
        $carga = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA20, 'cantidad' => 999]]);

        $this->assertSame(84, $cupo['bultos']);
        $this->assertSame($cupo['bultos'], $carga['lineas'][0]['colocados']);
        $this->assertSame($cupo['unidades'], $carga['lineas'][0]['unidades_colocadas']);
        $this->assertFalse($carga['cabe_todo']);
        $this->assertSame('espacio', $carga['lineas'][0]['motivo']);
    }

    /**
     * El caso del pedido, calculado a mano: 28 bolsas (140 botellones) dejan un
     * muro de 130 cm al fondo; en los 300 cm restantes caben 160 cajas (rejilla
     * 8×4×5 con la caja rotada a 37 de largo). Ni una más.
     */
    public function test_bolsas_mas_cajas_en_el_hd35_numeros_a_mano(): void
    {
        $r = $this->calc->carga(self::HD35, [
            ['bulto' => self::BOLSA20, 'cantidad' => 28],
            ['bulto' => self::CAJA, 'cantidad' => 200],
        ]);

        $this->assertSame(28, $r['lineas'][0]['colocados']);
        $this->assertSame(140, $r['lineas'][0]['unidades_colocadas']);
        $this->assertNull($r['lineas'][0]['motivo']);

        $this->assertSame(160, $r['lineas'][1]['colocados']);
        $this->assertSame('espacio', $r['lineas'][1]['motivo']);
        $this->assertFalse($r['cabe_todo']);
    }

    /** Si todo cabe, se dice sin pero: cabe_todo y cero motivos. */
    public function test_una_carga_que_cabe_entera_lo_dice(): void
    {
        $r = $this->calc->carga(self::HD35, [
            ['bulto' => self::BOLSA20, 'cantidad' => 28],
            ['bulto' => self::CAJA, 'cantidad' => 100],
        ]);

        $this->assertTrue($r['cabe_todo']);
        $this->assertSame([null, null], array_column($r['lineas'], 'motivo'));
    }

    /**
     * Un bloque PARCIAL reserva solo su huella real, no la rejilla entera.
     *
     * Con 4 bolsas (una columna: 130 × 26 de piso) el resto del piso queda para
     * las cajas y entran 208; si el bloque parcial reservara la rejilla completa
     * de 84 bolsas, entrarían 160 como en el test anterior. La diferencia son 48
     * cajas REALES que un motor perezoso regalaría.
     */
    public function test_un_bloque_parcial_no_roba_el_piso_que_no_usa(): void
    {
        $r = $this->calc->carga(self::HD35, [
            ['bulto' => self::BOLSA20, 'cantidad' => 4],
            ['bulto' => self::CAJA, 'cantidad' => 999],
        ]);

        $this->assertSame(4, $r['lineas'][0]['colocados']);
        $this->assertSame(208, $r['lineas'][1]['colocados']);
    }

    /**
     * No se apila sobre lo que NO DECLARA que aguanta peso encima.
     *
     * ACTUALIZADO EL 11-08-2026: desde el segundo piso (§2bis) sí se apila un tipo sobre
     * otro, pero solo cuando los dos declaran `soporta_peso_encima`. Este candado siguió
     * verde sin tocarlo, y por la razón correcta: su tarima no declara nada, y el flag
     * ausente se lee como «no aguanta» — el lado seguro, que es el que protege a los
     * bultos todavía sin medir (las jaulas de las máquinas).
     *
     * La prueba limpia: un bloque bajo que tapiza TODO el piso deja 170 cm de
     * aire arriba, y aun así la caja que solo cabría ahí queda AFUERA.
     */
    public function test_no_apila_un_tipo_sobre_otro(): void
    {
        $veh = ['largo' => 200, 'ancho' => 100, 'alto' => 220, 'peso_max_kg' => null, 'pasillo' => 0];
        $tarima = ['largo' => 200, 'ancho' => 100, 'alto' => 50, 'orientacion_fija' => true, 'unidades' => 1, 'apilable_max' => 1];
        $caja = ['largo' => 50, 'ancho' => 50, 'alto' => 50, 'unidades' => 1, 'apilable_max' => 3];

        $r = $this->calc->carga($veh, [
            ['bulto' => $tarima, 'cantidad' => 1],
            ['bulto' => $caja, 'cantidad' => 5],
        ]);

        $this->assertSame(1, $r['lineas'][0]['colocados']);
        $this->assertSame(0, $r['lineas'][1]['colocados'], 'La caja solo cabría ARRIBA de la tarima: no se promete.');
        $this->assertSame('espacio', $r['lineas'][1]['motivo']);
    }

    /**
     * Y el caso real que me corrigió el motor al escribir estos tests: con el
     * muro de 84 bolsas puesto (390 × 182 de piso), detrás quedan 40 cm — y ahí
     * SÍ entran 20 cajas rotadas a 37 de largo (1 × 4 × 5). El cálculo a mano
     * decía cero por descuidar la rotación; la rejilla la probó y tenía razón.
     */
    public function test_la_franja_detras_del_muro_no_se_regala(): void
    {
        $r = $this->calc->carga(self::HD35, [
            ['bulto' => self::BOLSA20, 'cantidad' => 84],
            ['bulto' => self::CAJA, 'cantidad' => 999],
        ]);

        $this->assertSame(84, $r['lineas'][0]['colocados']);
        $this->assertSame(20, $r['lineas'][1]['colocados']);
        $this->assertSame('espacio', $r['lineas'][1]['motivo']);
    }

    /** El peso es GLOBAL: lo que consumió la primera línea se lo descuenta a la segunda. */
    public function test_el_peso_acumulado_recorta_a_la_linea_que_llega_despues(): void
    {
        $veh = ['largo' => 430, 'ancho' => 200, 'alto' => 220, 'peso_max_kg' => 100, 'pasillo' => 0];
        $pesado = ['largo' => 40, 'ancho' => 40, 'alto' => 40, 'peso' => 10.0, 'apilable_max' => 5, 'unidades' => 1];
        $grande = ['largo' => 50, 'ancho' => 50, 'alto' => 50, 'peso' => 10.0, 'apilable_max' => 4, 'unidades' => 1];

        // El grande va primero (volumen mayor): 6 × 10 kg = 60. Al chico le
        // quedan 40 kg → 4 de los 6 pedidos, motivo PESO.
        $r = $this->calc->carga($veh, [
            ['bulto' => $pesado, 'cantidad' => 6],
            ['bulto' => $grande, 'cantidad' => 6],
        ]);

        $this->assertSame(6, $r['lineas'][1]['colocados']);
        $this->assertSame(4, $r['lineas'][0]['colocados']);
        $this->assertSame(CalculoDeCarga::LIMITE_PESO, $r['lineas'][0]['motivo']);
        $this->assertSame(100.0, $r['peso_kg']);
    }

    /** Un bulto más alto que la caja dice ALTO, no un «espacio» genérico. */
    public function test_la_jaula_mas_alta_que_la_caja_dice_por_que_no_entra(): void
    {
        $jaula = ['largo' => 200, 'ancho' => 120, 'alto' => 260, 'orientacion_fija' => true, 'unidades' => 1, 'apilable_max' => 1];

        $r = $this->calc->carga(self::HD35, [['bulto' => $jaula, 'cantidad' => 1]]);

        $this->assertSame(0, $r['lineas'][0]['colocados']);
        $this->assertSame(CalculoDeCarga::LIMITE_ALTO, $r['lineas'][0]['motivo']);
        $this->assertFalse($r['cabe_todo']);
    }

    /** Lo grande se coloca primero aunque se haya escrito último — y el resultado se reporta en el orden escrito. */
    public function test_ordena_por_volumen_pero_reporta_en_el_orden_escrito(): void
    {
        $r = $this->calc->carga(self::HD35, [
            ['bulto' => self::CAJA, 'cantidad' => 10],       // escrito primero, se coloca después
            ['bulto' => self::BOLSA20, 'cantidad' => 28],    // más volumen: va al fondo
        ]);

        // El reporte respeta los índices de entrada…
        $this->assertSame(10, $r['lineas'][0]['colocados']);
        $this->assertSame(28, $r['lineas'][1]['colocados']);

        // …y en la escena el bloque de bolsas quedó AL FONDO (x = 0).
        $bloqueBolsas = collect($r['bloques'])->firstWhere('linea', 1);
        $this->assertSame(0, $bloqueBolsas['x']);
    }

    /** El pasillo se descuenta también en la carga mixta. */
    public function test_el_pasillo_reservado_baja_la_carga_mixta(): void
    {
        $sin = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA20, 'cantidad' => 999]]);
        $con = $this->calc->carga(['pasillo' => 130] + self::HD35, [['bulto' => self::BOLSA20, 'cantidad' => 999]]);

        $this->assertLessThan($sin['lineas'][0]['colocados'], $con['lineas'][0]['colocados']);
    }

    /** Bordes: sin líneas, cantidad cero y un vehículo sin medidas no revientan. */
    public function test_bordes_sin_lineas_y_cantidad_cero(): void
    {
        $vacia = $this->calc->carga(self::HD35, []);
        $this->assertTrue($vacia['cabe_todo']);
        $this->assertSame([], $vacia['bloques']);

        $cero = $this->calc->carga(self::HD35, [['bulto' => self::BOLSA20, 'cantidad' => 0]]);
        $this->assertSame(0, $cero['lineas'][0]['colocados']);
        $this->assertNull($cero['lineas'][0]['motivo']);
        $this->assertTrue($cero['cabe_todo']);
    }

    /**
     * La suma de los bloques es exactamente lo colocado, y ningún bloque se sale
     * de la caja — el chequeo geométrico que hace confiable el dibujo 3D.
     */
    public function test_los_bloques_cuadran_y_ninguno_se_sale_de_la_caja(): void
    {
        $r = $this->calc->carga(self::HD35, [
            ['bulto' => self::BOLSA20, 'cantidad' => 30],
            ['bulto' => self::CAJA, 'cantidad' => 150],
        ]);

        $colocados = array_sum(array_column($r['lineas'], 'colocados'));
        $this->assertSame($colocados, array_sum(array_column($r['bloques'], 'cantidad')));

        foreach ($r['bloques'] as $b) {
            $finLargo = $b['x'] + $b['rejilla']['largo'] * $b['orientacion']['largo'];
            $finAncho = $b['y'] + $b['rejilla']['ancho'] * $b['orientacion']['ancho'];
            $finAlto = $b['rejilla']['alto'] * $b['orientacion']['alto'];
            $this->assertLessThanOrEqual(430, $finLargo, 'Un bloque se sale por el largo.');
            $this->assertLessThanOrEqual(200, $finAncho, 'Un bloque se sale por el ancho.');
            $this->assertLessThanOrEqual(220, $finAlto, 'Un bloque se sale por el alto.');
        }

        // Y ningún par de bloques se superpone en el piso (son rectángulos disjuntos).
        $n = count($r['bloques']);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $r['bloques'][$i];
                $b = $r['bloques'][$j];
                $al = $a['rejilla']['largo'] * $a['orientacion']['largo'];
                $aw = $a['rejilla']['ancho'] * $a['orientacion']['ancho'];
                $bl = $b['rejilla']['largo'] * $b['orientacion']['largo'];
                $bw = $b['rejilla']['ancho'] * $b['orientacion']['ancho'];
                $separados = $a['x'] + $al <= $b['x'] || $b['x'] + $bl <= $a['x']
                    || $a['y'] + $aw <= $b['y'] || $b['y'] + $bw <= $a['y'];
                $this->assertTrue($separados, "Los bloques $i y $j se superponen.");
            }
        }
    }
}
