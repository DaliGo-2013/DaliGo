<?php

namespace Tests\Unit\Dte;

use App\Services\Dte\DesgloseNeto;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Candados de la regla de Contabilidad del 28-jul-2026: el TOTAL que paga el
 * cliente es la cifra que debe cuadrar exacto.
 *
 * El test que importa es el último (la propiedad sobre cientos de combinaciones):
 * los casos puntuales pueden pasar por casualidad, la propiedad no.
 */
class DesgloseNetoTest extends TestCase
{
    public function test_una_sola_linea_redonda(): void
    {
        $r = DesgloseNeto::repartir([11900]);

        $this->assertSame([10000], $r['netos']);
        $this->assertSame(10000, $r['neto']);
        $this->assertSame(1900, $r['iva']);
        $this->assertSame(11900, $r['total']);
    }

    public function test_el_caso_que_perdia_un_peso(): void
    {
        // Tres líneas de $1.000. Redondeando línea por línea: 840 x 3 = 2.520,
        // IVA 479, total 2.999 → faltaba $1 sobre los $3.000 que pagó el cliente.
        $r = DesgloseNeto::repartir([1000, 1000, 1000]);

        $this->assertSame(2521, $r['neto'], 'El neto autoritativo es el del total, no la suma de líneas.');
        $this->assertSame(479, $r['iva']);
        $this->assertSame(3000, $r['total']);
        $this->assertSame(3000, $r['neto'] + $r['iva'], 'Neto + IVA tiene que dar el total exacto.');
        $this->assertSame(2521, array_sum($r['netos']), 'Las líneas suman el neto del documento.');
    }

    public function test_el_residuo_va_a_la_linea_de_mayor_monto(): void
    {
        // Tres repuestos de $1.000 + mano de obra de $50.000. El residuo de $1
        // cae en la mano de obra, que es donde Contabilidad lo prefiere.
        $r = DesgloseNeto::repartir([1000, 1000, 1000, 50000]);

        $this->assertSame([840, 840, 840], array_slice($r['netos'], 0, 3), 'Las líneas chicas quedan intactas.');
        $this->assertSame(DesgloseNeto::netoDe(50000) + 1, $r['netos'][3], 'La línea grande absorbió el ajuste.');
        $this->assertSame($r['neto'], array_sum($r['netos']));
        $this->assertSame(53000, $r['neto'] + $r['iva']);
    }

    public function test_si_el_reparto_ya_cuadra_no_toca_ninguna_linea(): void
    {
        // El ajuste no siempre existe: acá el residuo es 0 y las dos líneas
        // quedan tal cual (no se "ajusta por si acaso").
        $r = DesgloseNeto::repartir([1000, 50000]);

        $this->assertSame([DesgloseNeto::netoDe(1000), DesgloseNeto::netoDe(50000)], $r['netos']);
        $this->assertSame(51000, $r['neto'] + $r['iva']);
    }

    public function test_se_puede_forzar_la_linea_que_absorbe_el_ajuste(): void
    {
        $r = DesgloseNeto::repartir([1000, 1000, 1000], ajustarEn: 2);

        $this->assertSame(840, $r['netos'][0]);
        $this->assertSame(840, $r['netos'][1]);
        $this->assertSame(841, $r['netos'][2]);
    }

    public function test_una_linea_inexistente_no_puede_absorber_el_ajuste(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DesgloseNeto::repartir([1000, 1000], ajustarEn: 7);
    }

    public function test_sin_lineas_devuelve_ceros(): void
    {
        $r = DesgloseNeto::repartir([]);

        $this->assertSame(['netos' => [], 'neto' => 0, 'iva' => 0, 'total' => 0], $r);
    }

    public function test_empate_de_montos_reparte_siempre_igual(): void
    {
        // Determinismo: dos emisiones del mismo documento tienen que dar líneas
        // idénticas, o el descuadre sería imposible de rastrear.
        $primera = DesgloseNeto::repartir([7777, 7777, 7777]);
        $segunda = DesgloseNeto::repartir([7777, 7777, 7777]);

        $this->assertSame($primera, $segunda);
    }

    /**
     * La propiedad que tiene que valer SIEMPRE: la suma de los netos de las
     * líneas más el IVA es exactamente el total que pagó el cliente.
     */
    public function test_neto_mas_iva_siempre_cuadra_con_el_total(): void
    {
        mt_srand(20260728); // reproducible: un fallo se puede volver a correr igual

        for ($caso = 0; $caso < 300; $caso++) {
            $brutos = [];
            for ($i = 0, $n = mt_rand(1, 8); $i < $n; $i++) {
                $brutos[] = mt_rand(1, 900000);
            }

            $r = DesgloseNeto::repartir($brutos);
            $detalle = 'brutos: '.implode(',', $brutos);

            $this->assertSame(array_sum($brutos), $r['total'], $detalle);
            $this->assertSame($r['total'], $r['neto'] + $r['iva'], $detalle);
            $this->assertSame($r['neto'], array_sum($r['netos']), $detalle);
            $this->assertGreaterThanOrEqual(0, min($r['netos']), "Ningún neto puede quedar negativo. {$detalle}");
        }
    }
}
