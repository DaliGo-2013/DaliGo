<?php

namespace App\Services\Carga;

/**
 * Cuántos bultos entran en un vehículo (simulador de carga · Logística).
 *
 * POR QUÉ NO SE DIVIDEN VOLÚMENES. La tentación es `volumen del camión ÷ volumen
 * del bulto`. Está mal y siempre miente hacia arriba: si sobran 15 cm de ancho,
 * ahí no entra nada — son espacio muerto. La división de volúmenes se los come
 * como si fueran aprovechables y promete carga que no cabe, que es el peor error
 * posible en una herramienta de venta.
 *
 * Acá se calcula por REJILLA con división entera en las tres dimensiones:
 *
 *     floor(largo/a) × floor(ancho/b) × floor(alto/c)
 *
 * Eso reproduce el patrón real de estiba de Dali (bultos regulares apilados en
 * bloque, verificado en fotos de carga) y por eso el resultado es exacto para el
 * caso dominante, no estimativo.
 *
 * ORIENTACIÓN. Si el bulto NO es de orientación fija se prueban las 6 rotaciones
 * y gana la mejor. Si es fija (botellones acostados con el pico a la puerta,
 * jaulas de máquina a lo largo del costado) se usan las medidas tal como vienen.
 * La diferencia no es cosmética: en el HD35 el mismo bulto da 84 bolsas con
 * rotación libre y 64 con la orientación fija de la práctica — 24% menos.
 *
 * FACTOR DE APROVECHAMIENTO. La carga real no es una rejilla perfecta: hay
 * amarres, hilera del piso girada, y gente que necesita pasar. `factor` (0-1)
 * castiga el resultado teórico y se calibra contando UNA carga real. Mientras no
 * se calibre vale 1 y el número hay que leerlo como techo.
 */
class CalculoDeCarga
{
    /** Nombre del límite que se agotó primero. */
    public const LIMITE_LARGO = 'largo';

    public const LIMITE_ANCHO = 'ancho';

    public const LIMITE_ALTO = 'alto';

    public const LIMITE_PESO = 'peso';

    public const LIMITE_NINGUNO = 'ninguno';

    /**
     * Máximo de bultos de UN tipo en un vehículo vacío.
     *
     * @param  array{largo:int,ancho:int,alto:int,peso_max_kg?:int|null,pasillo?:int}  $vehiculo  cm y kg
     * @param  array{largo:int,ancho:int,alto:int,peso?:float|null,unidades?:int,apilable_max?:int,orientacion_fija?:bool}  $bulto  cm y kg
     * @return array{bultos:int,unidades:int,rejilla:array{largo:int,ancho:int,alto:int},orientacion:array{largo:int,ancho:int,alto:int},limite:string,peso_kg:float,volumen_ocupado_m3:float,volumen_vehiculo_m3:float,ocupacion:float}
     */
    public function cupo(array $vehiculo, array $bulto): array
    {
        // El pasillo se descuenta del LARGO: es el paso desde la puerta hacia
        // adentro, no una franja a lo ancho.
        $L = max(0, (int) $vehiculo['largo'] - (int) ($vehiculo['pasillo'] ?? 0));
        $W = (int) $vehiculo['ancho'];
        $H = (int) $vehiculo['alto'];

        $mejor = null;

        foreach ($this->orientaciones($bulto) as $o) {
            [$a, $b, $c] = $o;
            if ($a <= 0 || $b <= 0 || $c <= 0) {
                continue;
            }

            $nl = intdiv($L, $a);
            $nw = intdiv($W, $b);
            // El tope de apilado manda sobre lo que la altura permitiría.
            $nh = min(intdiv($H, $c), max(1, (int) ($bulto['apilable_max'] ?? 1)));

            $n = $nl * $nw * $nh;
            if ($mejor === null || $n > $mejor['bultos']) {
                $mejor = [
                    'bultos' => $n,
                    'rejilla' => ['largo' => $nl, 'ancho' => $nw, 'alto' => $nh],
                    'orientacion' => ['largo' => $a, 'ancho' => $b, 'alto' => $c],
                ];
            }
        }

        $mejor ??= [
            'bultos' => 0,
            'rejilla' => ['largo' => 0, 'ancho' => 0, 'alto' => 0],
            'orientacion' => ['largo' => 0, 'ancho' => 0, 'alto' => 0],
        ];

        $bultos = $mejor['bultos'];
        $limite = $bultos === 0
            ? $this->porQueNoEntraNinguno($L, $W, $H, $this->orientaciones($bulto))
            : $this->limiteDominante($mejor['rejilla']);

        // Peso: puede recortar el cupo aunque el espacio sobre. Con botellones
        // vacíos no pasa nunca, pero con cajas de repuestos sí.
        $pesoUnit = (float) ($bulto['peso'] ?? 0);
        $topePeso = $vehiculo['peso_max_kg'] ?? null;
        if ($pesoUnit > 0 && $topePeso) {
            $porPeso = (int) floor(((int) $topePeso) / $pesoUnit);
            if ($porPeso < $bultos) {
                $bultos = max(0, $porPeso);
                $limite = self::LIMITE_PESO;
            }
        }

        $volVeh = $this->m3($vehiculo['largo'], $W, $H);
        $volOcu = $this->m3($bulto['largo'], $bulto['ancho'], $bulto['alto']) * $bultos;

        return [
            'bultos' => $bultos,
            'unidades' => $bultos * max(1, (int) ($bulto['unidades'] ?? 1)),
            'rejilla' => $mejor['rejilla'],
            'orientacion' => $mejor['orientacion'],
            'limite' => $limite,
            'peso_kg' => round($pesoUnit * $bultos, 2),
            'volumen_ocupado_m3' => round($volOcu, 2),
            'volumen_vehiculo_m3' => round($volVeh, 2),
            'ocupacion' => $volVeh > 0 ? round($volOcu / $volVeh, 4) : 0.0,
        ];
    }

    /**
     * Aplica el factor de aprovechamiento medido en terreno (0 < f <= 1).
     *
     * Se expone aparte de `cupo()` para que el número teórico y el realista
     * queden distinguibles: la pantalla debe poder mostrar los dos y decir cuál
     * está calibrado.
     */
    public function conFactor(array $cupo, float $factor): array
    {
        $factor = max(0.0, min(1.0, $factor));
        $bultos = (int) floor($cupo['bultos'] * $factor);

        return [...$cupo, 'bultos' => $bultos, 'unidades' => $bultos * ($cupo['bultos'] > 0 ? intdiv($cupo['unidades'], max(1, $cupo['bultos'])) : 1)];
    }

    /**
     * Las 6 rotaciones del bulto, o solo la cargada si la orientación es fija.
     *
     * @return list<array{int,int,int}>  (largo, ancho, alto) en cm
     */
    private function orientaciones(array $bulto): array
    {
        $l = (int) $bulto['largo'];
        $w = (int) $bulto['ancho'];
        $h = (int) $bulto['alto'];

        if (! empty($bulto['orientacion_fija'])) {
            return [[$l, $w, $h]];
        }

        return [
            [$l, $w, $h], [$l, $h, $w],
            [$w, $l, $h], [$w, $h, $l],
            [$h, $l, $w], [$h, $w, $l],
        ];
    }

    /** Cuál de las tres dimensiones quedó más "apretada" (una sola fila/columna). */
    private function limiteDominante(array $rejilla): string
    {
        $min = min($rejilla['largo'], $rejilla['ancho'], $rejilla['alto']);

        return match (true) {
            $rejilla['largo'] === $min => self::LIMITE_LARGO,
            $rejilla['ancho'] === $min => self::LIMITE_ANCHO,
            default => self::LIMITE_ALTO,
        };
    }

    /**
     * Si no entra NI UNO, decir qué dimensión lo impide (no un "0" pelado).
     *
     * Se evalúa POR ORIENTACIÓN y no contra la medida más chica del bulto. La
     * versión anterior comparaba el alto de la caja contra el lado menor del
     * bulto, así que una jaula de 260 cm de alto en un camión de 220 devolvía
     * "ninguno" — el usuario veía un cero sin explicación. Con orientación fija
     * hay una sola candidata y la respuesta es exacta; con rotación libre se
     * reporta el estorbo de la orientación que menos lejos quedó.
     *
     * @param  list<array{int,int,int}>  $orientaciones
     */
    private function porQueNoEntraNinguno(int $L, int $W, int $H, array $orientaciones): string
    {
        $mejor = null;

        foreach ($orientaciones as [$a, $b, $c]) {
            // Cuánto falta en cada eje (0 si ese eje sí entra).
            $faltas = [
                self::LIMITE_LARGO => max(0, $a - $L),
                self::LIMITE_ANCHO => max(0, $b - $W),
                self::LIMITE_ALTO => max(0, $c - $H),
            ];
            $total = array_sum($faltas);
            if ($total === 0) {
                continue;   // esta orientación entraba: el cero viene de otra parte
            }
            if ($mejor === null || $total < $mejor['total']) {
                arsort($faltas);
                $mejor = ['total' => $total, 'eje' => array_key_first($faltas)];
            }
        }

        return $mejor['eje'] ?? self::LIMITE_NINGUNO;
    }

    private function m3(int|float $largoCm, int|float $anchoCm, int|float $altoCm): float
    {
        return ($largoCm / 100) * ($anchoCm / 100) * ($altoCm / 100);
    }
}
