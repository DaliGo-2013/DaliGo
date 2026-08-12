<?php

namespace App\Services\Carga;

use App\Models\CamionSimulacion;

/**
 * CUÁNTO DE LA CARGA CAE SOBRE CADA EJE (lote 5; los datos de los ejes llegaron el
 * 12-08-2026).
 *
 * PARA QUÉ SIRVE, que no es lo mismo que el cartel de sobrepeso. Un camión puede
 * estar por debajo de su carga máxima y aun así ir mal: si todo el peso queda atrás,
 * el eje trasero se pasa —multa— y el delantero se ALIVIANA, que es un problema de
 * dirección y de frenos. Los kilos totales no dicen nada de eso; el reparto sí.
 *
 * LA FÍSICA ES UNA PALANCA, y es exacta para lo que se pregunta acá: un cuerpo
 * apoyado en dos puntos reparte su peso en proporción inversa a la distancia a cada
 * apoyo. Para cada bloque se toma su CENTRO a lo largo de la caja —la carga está
 * repartida pareja adentro del bloque, que es lo que el motor garantiza: es una
 * rejilla— y se calcula qué fracción cae en cada eje.
 *
 * LO QUE ESTE CÁLCULO **NO** INCLUYE, y por eso la pantalla lo dice:
 *
 *  · EL PESO DEL CAMIÓN VACÍO. Reparte solo LA CARGA. La tara y cómo se apoya no
 *    están medidas para esta flota, y sumarlas de memoria convertiría un número
 *    exacto en una estimación disfrazada. Sirve igual para lo que se usa: comparar
 *    dos formas de acomodar la misma carga.
 *  · LA CAPACIDAD DE CADA EJE. Sin ese dato no se puede decir «te pasaste» de un eje,
 *    solo cuánto le toca. En cuanto lleguen los dos números por camión, el aviso sale
 *    de comparar y ya no hay que tocar este cálculo.
 *
 * SE HACE SOLO CON LOS DOS DATOS MEDIDOS (ver `CamionSimulacion::tieneEjes`). Sin
 * ellos devuelve `null` y la pantalla dice qué falta medir, en vez de mostrar un
 * reparto inventado sobre el que alguien decida si un camión sale o no.
 */
class RepartoPorEje
{
    /**
     * @param  list<array<string, mixed>>  $bloques  los del motor, en centímetros
     * @param  array<int, string>  $nombres  nombre del producto de cada línea, para poder
     *                                       decir CUÁL no tiene peso cargado
     * @return array{delantero_kg: float, trasero_kg: float, total_kg: float, delantero_pct: int, trasero_pct: int, aliviana_el_delantero: bool, sin_peso: list<string>, tope_delantero_kg: ?int, tope_trasero_kg: ?int, se_pasa_delantero: ?bool, se_pasa_trasero: ?bool}|null
     */
    public function calcular(CamionSimulacion $camion, array $bloques, array $lineas, array $nombres = []): ?array
    {
        if (! $camion->tieneEjes() || $bloques === []) {
            return null;
        }

        $xDelantero = (float) $camion->ejeDelanteroCm();
        $entreEjes = (float) $camion->entre_ejes_cm;

        $delantero = 0.0;
        $trasero = 0.0;
        // LO QUE NO SE PUDO REPARTIR. La mitad del catálogo todavía no tiene peso
        // cargado —y está en null a propósito, no se inventa—, así que un reparto puede
        // salir incompleto. Antes eso hacía DESAPARECER la sección sin explicación: se
        // veía en una carga y no en la otra, y no había forma de saber por qué. Ahora
        // se nombra el producto que falta pesar.
        $sinPeso = [];

        foreach ($bloques as $b) {
            $peso = (float) ($lineas[$b['linea']]['bulto']['peso'] ?? 0) * (int) $b['cantidad'];
            if ($peso <= 0.0) {
                $sinPeso[$b['linea']] = $nombres[$b['linea']] ?? 'un producto';

                continue;
            }

            // El centro del bloque a lo largo de la caja. Es el punto donde se puede
            // concentrar todo su peso sin cambiar el resultado, porque adentro del
            // bloque la carga es una rejilla pareja.
            $centro = $b['x'] + ($b['rejilla']['largo'] * $b['orientacion']['largo']) / 2;

            // Fracción que toma el eje TRASERO: cuanto más cerca de él está el centro,
            // más se lleva. Sin acotar a [0, 1] a propósito — si la carga queda DETRÁS
            // del eje trasero la fracción pasa de 1 y el delantero recibe negativo, que
            // no es un error de cuenta: es el camión levantando la trompa, y hay que
            // decirlo en vez de esconderlo con un `min()`.
            $f = ($centro - $xDelantero) / $entreEjes;

            $trasero += $peso * $f;
            $delantero += $peso * (1 - $f);
        }

        $total = $delantero + $trasero;
        $sinPeso = array_values($sinPeso);

        // Nada que repartir Y nada que explicar: el camión va vacío.
        if ($total <= 0.0 && $sinPeso === []) {
            return null;
        }

        // Hay carga pero NINGUNA con peso conocido. Se devuelve igual, en ceros y con
        // los nombres: «no se puede repartir porque falta pesar esto» es una respuesta;
        // no mostrar nada es un misterio.
        if ($total <= 0.0) {
            return [
                'delantero_kg' => 0.0, 'trasero_kg' => 0.0, 'total_kg' => 0.0,
                'delantero_pct' => 0, 'trasero_pct' => 0,
                'aliviana_el_delantero' => false, 'sin_peso' => $sinPeso,
                'tope_delantero_kg' => $camion->eje_delantero_max_kg,
                'tope_trasero_kg' => $camion->eje_trasero_max_kg,
                'se_pasa_delantero' => null, 'se_pasa_trasero' => null,
            ];
        }

        return [
            'delantero_kg' => round($delantero, 1),
            'trasero_kg' => round($trasero, 1),
            'total_kg' => round($total, 1),
            'sin_peso' => $sinPeso,
            // ¿SE PASA DE ALGÚN EJE? (pedido del dueño 12-08: «si me pasé, para evitar
            // una multa, que salga un mensaje en rojo»). En la balanza no se pesa el
            // camión entero: se pesa eje por eje, así que un camión por debajo de su
            // carga útil total puede tener el trasero pasado y lo paga igual.
            //
            // Sin el tope cargado no hay aviso —null, no `false`—: «no se pasa» y «no
            // sé cuánto aguanta» son cosas distintas y la pantalla las dice distinto.
            'tope_delantero_kg' => $camion->eje_delantero_max_kg,
            'tope_trasero_kg' => $camion->eje_trasero_max_kg,
            'se_pasa_delantero' => $camion->eje_delantero_max_kg !== null
                ? $delantero > $camion->eje_delantero_max_kg
                : null,
            'se_pasa_trasero' => $camion->eje_trasero_max_kg !== null
                ? $trasero > $camion->eje_trasero_max_kg
                : null,
            // Los porcentajes se calculan sobre el TOTAL y se redondean solo para
            // mostrar; el que manda es el kilo.
            'delantero_pct' => (int) round($delantero / $total * 100),
            'trasero_pct' => (int) round($trasero / $total * 100),
            // La señal que sí se puede dar sin conocer la capacidad de cada eje: la
            // carga está tan atrás que en vez de apoyar sobre el delantero, lo levanta.
            'aliviana_el_delantero' => $delantero < 0,
        ];
    }
}
