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
     * CARGA MIXTA: ¿cabe ESTA carga concreta? (pedido del dueño 04-08-2026, el
     * caso textual del pedido original: «200 botellones + 20 cajas de tapas +
     * 10 dispensadores → ¿entra en el camión X?»).
     *
     * Responde una pregunta DISTINTA de cupo(): cupo() dice el máximo de UN tipo
     * en el camión vacío; carga() recibe una lista de (tipo, cantidad) y dice si
     * cabe todo, qué quedó afuera y POR QUÉ — que es lo que el vendedor necesita
     * para negociar («te llevo 140 de los 200 y el resto la próxima semana»).
     *
     * CÓMO ACOMODA: bloques de rejilla exacta sobre regiones rectangulares de
     * piso (partición tipo guillotina). Cada tipo se coloca como un bloque
     * (misma división entera de cupo()) en la región más al fondo donde quepa;
     * el piso restante se parte en dos regiones (detrás y al costado del bloque)
     * y sigue el próximo tipo. Reproduce el patrón real de estiba por ZONAS que
     * se ve en las fotos de carga de Dali (muro de bolsas, máquinas a un
     * costado, cajas en el resto) sin caer en el empaque 3D genérico, que es
     * NP-difícil y del que ninguna heurística puede prometer exactitud.
     *
     * REGLAS CONSERVADORAS, deliberadas — todo error va hacia ABAJO:
     * - El espacio SOBRE un bloque es espacio muerto: no se apila un tipo encima
     *   de otro. La estiba real a veces lo hace; prometerlo sin regla de soporte
     *   por kilo sería exagerar, y un simulador que exagera es peor que ninguno.
     * - Un bloque parcial reserva el piso de su caja envolvente completa.
     * - El orden de colocación es por volumen de bulto DESCENDENTE (lo grande
     *   primero, como en la práctica), no el orden en que se escribieron —
     *   salvo que `$enOrdenDeLista` lo pida, y ahí manda el orden del usuario.
     *
     * @param  array{largo:int,ancho:int,alto:int,peso_max_kg?:int|null,pasillo?:int}  $vehiculo  cm y kg
     * @param  list<array{bulto: array, cantidad: int}>  $lineas  cantidad EN BULTOS
     * @param  bool  $enOrdenDeLista  respeta el orden dado en vez de ordenar por volumen
     * @return array{lineas: array<int, array{pedidos:int,colocados:int,unidades_colocadas:int,motivo:?string}>, bloques: list<array{linea:int,x:int,y:int,orientacion:array{largo:int,ancho:int,alto:int},rejilla:array{largo:int,ancho:int,alto:int},cantidad:int}>, cabe_todo:bool, peso_kg:float, volumen_ocupado_m3:float, volumen_vehiculo_m3:float, ocupacion:float}
     */
    public function carga(array $vehiculo, array $lineas, bool $enOrdenDeLista = false): array
    {
        $L = max(0, (int) $vehiculo['largo'] - (int) ($vehiculo['pasillo'] ?? 0));
        $W = (int) $vehiculo['ancho'];
        $H = (int) $vehiculo['alto'];
        $topePeso = $vehiculo['peso_max_kg'] ?? null;

        // El ORDEN de colocación decide qué producto queda al fondo y qué queda contra la
        // puerta, porque cada bloque se pone en la región más al fondo donde quepa.
        //
        // Por defecto: lo grande primero (estable: a igual volumen, el orden escrito), que
        // es como se estiba en la práctica. Con `$enOrdenDeLista` manda el orden que armó
        // el usuario, y ahí él decide qué va al fondo — es la forma HONESTA de «mover la
        // carga»: se reordena la lista y el motor recalcula, así que el resultado sigue
        // siendo un acomodo que el motor verificó. Arrastrar bloques a mano dejaría armar
        // en pantalla una carga que el cálculo dice que no cabe.
        $orden = array_keys($lineas);
        if (! $enOrdenDeLista) {
            usort($orden, function (int $a, int $b) use ($lineas) {
                $va = $lineas[$a]['bulto']['largo'] * $lineas[$a]['bulto']['ancho'] * $lineas[$a]['bulto']['alto'];
                $vb = $lineas[$b]['bulto']['largo'] * $lineas[$b]['bulto']['ancho'] * $lineas[$b]['bulto']['alto'];

                return $vb <=> $va ?: $a <=> $b;
            });
        }

        // Regiones de piso libres. Arranca con toda la caja menos el pasillo.
        $regiones = ($L > 0 && $W > 0) ? [['x' => 0, 'y' => 0, 'largo' => $L, 'ancho' => $W]] : [];

        $porLinea = [];
        $bloques = [];
        $pesoAcum = 0.0;
        $volOcupado = 0.0;

        foreach ($orden as $i) {
            $bulto = $lineas[$i]['bulto'];
            $pedidos = max(0, (int) $lineas[$i]['cantidad']);
            $porUnidad = max(1, (int) ($bulto['unidades'] ?? 1));
            $pesoUnit = (float) ($bulto['peso'] ?? 0);
            $restan = $pedidos;
            $capadoPorPeso = false;

            while ($restan > 0) {
                // El tope de peso es GLOBAL a la carga: se evalúa antes de cada
                // bloque, porque las líneas anteriores ya consumieron kilos.
                $topeBultos = ($pesoUnit > 0 && $topePeso !== null)
                    ? (int) floor((max(0, $topePeso - $pesoAcum)) / $pesoUnit)
                    : PHP_INT_MAX;
                if ($topeBultos <= 0) {
                    $capadoPorPeso = true;
                    break;
                }

                $puesto = $this->colocarBloque($regiones, $bulto, min($restan, $topeBultos), $H);
                if ($puesto === null) {
                    break;   // no cabe ni uno en ninguna región
                }

                $bloques[] = $puesto + ['linea' => $i];
                $restan -= $puesto['cantidad'];
                $pesoAcum += $pesoUnit * $puesto['cantidad'];
                $volOcupado += $this->m3($bulto['largo'], $bulto['ancho'], $bulto['alto']) * $puesto['cantidad'];
            }

            $colocados = $pedidos - $restan;
            $porLinea[$i] = [
                'pedidos' => $pedidos,
                'colocados' => $colocados,
                'unidades_colocadas' => $colocados * $porUnidad,
                'motivo' => $restan > 0 ? $this->motivoDelFaltante($bulto, $L, $W, $H, $capadoPorPeso) : null,
            ];
        }

        ksort($porLinea);
        $volVeh = $this->m3($vehiculo['largo'], $W, $H);

        return [
            'lineas' => $porLinea,
            'bloques' => $bloques,
            'cabe_todo' => array_filter($porLinea, fn (array $l) => $l['motivo'] !== null) === [],
            'peso_kg' => round($pesoAcum, 2),
            'volumen_ocupado_m3' => round($volOcupado, 2),
            'volumen_vehiculo_m3' => round($volVeh, 2),
            'ocupacion' => $volVeh > 0 ? round($volOcupado / $volVeh, 4) : 0.0,
        ];
    }

    /**
     * Coloca UN bloque del bulto en la primera región donde quepa (recorriendo
     * fondo → puerta) y parte esa región en guillotina. Devuelve null si no
     * entra ni un bulto en ninguna región.
     *
     * La región elegida es la de menor `x` (más al fondo): así lo grande queda
     * contra la cabina y el dibujo 3D se parece al camión real.
     *
     * @param  list<array{x:int,y:int,largo:int,ancho:int}>  $regiones  por referencia
     * @return ?array{x:int,y:int,orientacion:array{largo:int,ancho:int,alto:int},rejilla:array{largo:int,ancho:int,alto:int},cantidad:int}
     */
    private function colocarBloque(array &$regiones, array $bulto, int $maximo, int $H): ?array
    {
        usort($regiones, fn (array $a, array $b) => $a['x'] <=> $b['x'] ?: $a['y'] <=> $b['y']);

        foreach ($regiones as $k => $region) {
            $mejor = null;
            foreach ($this->orientaciones($bulto) as [$a, $b, $c]) {
                if ($a <= 0 || $b <= 0 || $c <= 0) {
                    continue;
                }
                $nl = intdiv($region['largo'], $a);
                $nw = intdiv($region['ancho'], $b);
                $nh = min(intdiv($H, $c), max(1, (int) ($bulto['apilable_max'] ?? 1)));
                $n = $nl * $nw * $nh;
                if ($n > 0 && ($mejor === null || $n > $mejor['n'])) {
                    $mejor = ['n' => $n, 'a' => $a, 'b' => $b, 'c' => $c, 'nl' => $nl, 'nw' => $nw, 'nh' => $nh];
                }
            }

            if ($mejor === null) {
                continue;
            }

            $cantidad = min($mejor['n'], $maximo);

            // Huella REAL del bloque parcial: columnas de nh, en rebanadas a lo
            // ancho. Reservar la rejilla completa cuando se piden 3 bultos
            // regalaría piso que otro tipo puede usar.
            $columnas = (int) ceil($cantidad / $mejor['nh']);
            $anchoUsado = min($columnas, $mejor['nw']);
            $largoUsado = (int) ceil($columnas / $mejor['nw']);

            $bl = $largoUsado * $mejor['a'];
            $bw = $anchoUsado * $mejor['b'];

            // Guillotina: lo que queda DETRÁS del bloque (hacia la puerta, ancho
            // completo) y lo que queda AL COSTADO (mismo largo del bloque).
            $nuevas = [];
            if ($region['largo'] - $bl > 0) {
                $nuevas[] = ['x' => $region['x'] + $bl, 'y' => $region['y'], 'largo' => $region['largo'] - $bl, 'ancho' => $region['ancho']];
            }
            if ($region['ancho'] - $bw > 0) {
                $nuevas[] = ['x' => $region['x'], 'y' => $region['y'] + $bw, 'largo' => $bl, 'ancho' => $region['ancho'] - $bw];
            }
            array_splice($regiones, $k, 1, $nuevas);

            return [
                'x' => $region['x'],
                'y' => $region['y'],
                'orientacion' => ['largo' => $mejor['a'], 'ancho' => $mejor['b'], 'alto' => $mejor['c']],
                'rejilla' => ['largo' => $largoUsado, 'ancho' => $anchoUsado, 'alto' => $mejor['nh']],
                'cantidad' => $cantidad,
            ];
        }

        return null;
    }

    /**
     * Por qué quedó carga afuera: peso, un eje del camión (si no entra ni uno en
     * la caja VACÍA), o espacio a secas (cabía solo, pero el resto de la carga
     * se lo comió).
     */
    private function motivoDelFaltante(array $bulto, int $L, int $W, int $H, bool $capadoPorPeso): string
    {
        if ($capadoPorPeso) {
            return self::LIMITE_PESO;
        }

        foreach ($this->orientaciones($bulto) as [$a, $b, $c]) {
            if ($a <= $L && $b <= $W && $c <= $H) {
                return 'espacio';
            }
        }

        return $this->porQueNoEntraNinguno($L, $W, $H, $this->orientaciones($bulto));
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

        // SOLO HORIZONTAL: gira 90° sobre el piso pero NO se tumba. Es el caso del
        // pallet armado — se lo puede poner a lo largo o a lo ancho, pero acostarlo
        // volcaría la carga. Sin esto había que elegir entre dos mentiras: con
        // `orientacion_fija` se perdía el giro válido de 90° (y el cupo salía más bajo de
        // lo real), y sin ella el motor probaba tumbarlo y podía prometer un acomodo que
        // en la vida se hace.
        if (($bulto['rotacion'] ?? null) === 'horizontal') {
            return [[$l, $w, $h], [$w, $l, $h]];
        }

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
