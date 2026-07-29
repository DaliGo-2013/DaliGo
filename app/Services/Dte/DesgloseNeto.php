<?php

namespace App\Services\Dte;

use App\Models\OrdenServicio;
use InvalidArgumentException;

/**
 * Reparte un total CON IVA en netos por línea, sin perder ni ganar un peso.
 *
 * REGLA DE CONTABILIDAD (28-jul-2026), respuesta a la pregunta 1 del informe:
 * la cifra que debe cuadrar exacto es **el TOTAL que paga el cliente**, no el
 * neto de cada línea. DaliGo guarda los precios CON IVA y el documento
 * tributario exige el neto por línea con el IVA desglosado aparte, así que la
 * conversión es inevitable — y con división por 1,19 los redondeos no suman.
 *
 * El problema, con números: tres líneas de $1.000 dan un total de $3.000. Si se
 * redondea línea por línea, cada neto es round(1000/1,19) = 840 y suman 2.520;
 * el IVA de 2.520 es 479, y 2.520 + 479 = 2.999. **Falta un peso**, y ese peso
 * aparece impreso en un documento tributario que el cliente pagó a $3.000.
 *
 * La solución es fijar primero el neto del TOTAL —round(3.000/1,19) = 2.521, el
 * mismo criterio que ya usa OrdenServicio::costo_neto— y cargar la diferencia
 * contra una sola línea. Así sum(netos) + IVA == total, siempre, por
 * construcción y no por suerte.
 *
 * Por defecto el residuo va a la línea de MAYOR monto: es donde un peso pesa
 * menos en términos relativos y es la que puede absorberlo sin quedar negativa.
 * En una reparación esa línea suele ser la mano de obra, que es exactamente
 * donde Contabilidad prefiere el ajuste.
 */
final class DesgloseNeto
{
    /**
     * Reparte los montos CON IVA de cada línea en netos enteros.
     *
     * @param  list<int>  $brutosConIva  Monto con IVA de cada línea, en pesos.
     * @param  int|null  $ajustarEn  Índice de la línea que absorbe el residuo. Por
     *                               defecto, la de mayor monto.
     * @return array{netos: list<int>, neto: int, iva: int, total: int}
     *
     * @throws InvalidArgumentException si $ajustarEn no es un índice válido.
     */
    public static function repartir(array $brutosConIva, ?int $ajustarEn = null): array
    {
        if ($brutosConIva === []) {
            return ['netos' => [], 'neto' => 0, 'iva' => 0, 'total' => 0];
        }

        if ($ajustarEn !== null && ! array_key_exists($ajustarEn, $brutosConIva)) {
            throw new InvalidArgumentException("La línea {$ajustarEn} no existe: no puede absorber el ajuste.");
        }

        $total = array_sum($brutosConIva);

        // El neto del TOTAL es la cifra autoritativa (mismo criterio que
        // OrdenServicio::costo_neto), no la suma de los netos por línea.
        $neto = self::netoDe($total);

        $netos = array_map(self::netoDe(...), $brutosConIva);

        // El residuo es de a lo más medio peso por línea, pero existe.
        $residuo = $neto - array_sum($netos);
        $indice = $ajustarEn ?? self::indiceDelMayor($brutosConIva);
        $netos[$indice] += $residuo;

        return [
            'netos' => array_values($netos),
            'neto' => $neto,
            // El IVA es la DIFERENCIA, no un cálculo aparte: así el documento
            // cuadra con lo que el cliente pagó aunque la tasa cambie.
            'iva' => $total - $neto,
            'total' => $total,
        ];
    }

    /** Neto de un monto con IVA (pesos enteros). */
    public static function netoDe(int $brutoConIva): int
    {
        return (int) round($brutoConIva / (1 + OrdenServicio::TASA_IVA));
    }

    /**
     * Índice del monto mayor. Ante empate gana el primero: el reparto tiene que
     * ser determinista, o dos emisiones del mismo documento darían líneas
     * distintas y el descuadre sería imposible de rastrear.
     *
     * @param  list<int>  $montos
     */
    private static function indiceDelMayor(array $montos): int
    {
        $indice = 0;
        foreach ($montos as $i => $monto) {
            if ($monto > $montos[$indice]) {
                $indice = $i;
            }
        }

        return $indice;
    }
}
