<?php

namespace App\Services\Carga;

/**
 * EL ACOMODO A MANO: mover y girar los bloques que el motor ya colocó.
 *
 * Pedido del dueño, tres veces (10 y 11-08-2026, la última textual): «que te dé la
 * opción de dar vuelta la caja y acomodar como uno quiero». Las dos primeras veces la
 * respuesta fue que no, con el argumento de que arrastrar bultos deja armar en pantalla
 * una carga que el cálculo dice que no cabe. El argumento sigue siendo cierto; la
 * decisión es del dueño y así se carga un camión de verdad. Lo que queda de ese reparo
 * es la HONESTIDAD del resultado, que es lo que esta clase protege:
 *
 *  1. UN ACOMODO NO CAMBIA LAS CUENTAS. Mover un bloque cambia DÓNDE va, no cuántos
 *     entran. El motor ya dijo cuántos; arrastrarlos no descubre lugar nuevo. Si alguien
 *     mueve todo a un rincón, la app no le va a ofrecer meter más.
 *  2. LO QUE ESTÁ MAL SE DICE, no se corrige en silencio. Dos bloques encimados o uno
 *     medio afuera se reportan como `choques` y `fuera`. No se los reacomoda —eso sería
 *     volver a decidir por el usuario— ni se los descarta.
 *  3. UN ACOMODO VIEJO SE TIRA ENTERO. Si el resultado cambió de cantidad de bloques, las
 *     posiciones guardadas ya no corresponden a estos productos: aplicar las primeras
 *     tres pondría la carga de otro en el lugar equivocado, en silencio y con cara de
 *     verificada. Por eso el acomodo viaja con `de` (para cuántos bloques se hizo) y si
 *     no coincide se descarta completo.
 *
 * GIRAR es sobre el PISO, no volcar. Se intercambian largo↔ancho del bulto y de la
 * rejilla a la vez —el bloque entero rota 90°, con las cajas adentro—, y el ALTO no se
 * toca: así el tope de apilado que calculó el motor sigue valiendo. Volcar una caja sí
 * cambia cuántas se pueden encimar, y eso es una pregunta para el motor (el campo «Cómo
 * viaja»), no para el mouse.
 *
 * POR QUÉ VIAJA EN LA URL y no en una tabla: el simulador es una función pura de su query
 * string —el link ES el escenario, ver `PlanCargaPublicoController`—. Con el acomodo
 * adentro, el plan acomodado a mano se comparte, se baja a Excel y se vuelve a abrir sin
 * una migración ni una tabla de acomodos viejos que nadie limpia.
 */
final class AcomodoManual
{
    /** Formato de cada posición: `x,y` o `x,y,g` (girado), en centímetros enteros. */
    public const FORMATO = '/^-?\d{1,4},-?\d{1,4}(,g)?$/';

    /**
     * @param  array<int|string, string>  $crudo  el parámetro `acomodo` tal como llega
     * @param  ?int  $hechoPara  el `acomodo_de`: para cuántos bloques se armó
     */
    public function __construct(
        private readonly array $crudo = [],
        private readonly ?int $hechoPara = null,
    ) {}

    /**
     * Aplica el acomodo a los bloques del motor.
     *
     * @param  list<array{x:int,y:int,orientacion:array{largo:int,ancho:int,alto:int},rejilla:array{largo:int,ancho:int,alto:int},cantidad:int}>  $bloques
     * @return array{bloques:list<array<string,mixed>>, activo:bool, movidos:int, choques:list<array{0:int,1:int}>, fuera:list<int>, descartado:bool}
     */
    public function aplicar(array $bloques, int $largoCm, int $anchoCm): array
    {
        $posiciones = $this->posiciones();

        // Un acomodo armado para otra cantidad de bloques no es «casi» este: es otro.
        // Se tira entero y la pantalla lo dice, en vez de encajar las primeras tres
        // posiciones sobre productos que ahora son distintos.
        $descartado = $posiciones !== [] && $this->hechoPara !== null && $this->hechoPara !== count($bloques);

        if ($posiciones === [] || $descartado) {
            return [
                'bloques' => $bloques,
                'activo' => false,
                'movidos' => 0,
                'choques' => [],
                'fuera' => [],
                'descartado' => $descartado,
            ];
        }

        $movidos = 0;
        foreach ($posiciones as $i => $pos) {
            if (! isset($bloques[$i])) {
                continue;
            }

            $bloques[$i]['x'] = $pos['x'];
            $bloques[$i]['y'] = $pos['y'];

            if ($pos['girado']) {
                $bloques[$i] = self::girar($bloques[$i]);
            }

            // El estado del giro vuelve EN EL BLOQUE y no solo aplicado: el tablero tiene
            // que reconstruirlo al recargar. Sin esto, la pieza se dibujaba ya girada pero
            // el tablero la creía derecha, y el siguiente «Aplicar» la devolvía al origen.
            $bloques[$i]['girado'] = $pos['girado'];
            $movidos++;
        }

        return [
            'bloques' => $bloques,
            'activo' => $movidos > 0,
            'movidos' => $movidos,
            'choques' => self::choques($bloques),
            'fuera' => self::fuera($bloques, $largoCm, $anchoCm),
            'descartado' => false,
        ];
    }

    /**
     * Las posiciones parseadas, por ordinal de bloque.
     *
     * Se descarta en silencio lo que no tenga forma de posición: el parámetro viene de
     * una URL que cualquiera puede editar a mano, y una coordenada inventada tiene que
     * dejar el bloque donde lo puso el motor —el lugar verificado— y no reventar la
     * pantalla ni caer en un 0,0 que nadie pidió.
     *
     * @return array<int, array{x:int, y:int, girado:bool}>
     */
    private function posiciones(): array
    {
        $salida = [];

        foreach ($this->crudo as $clave => $valor) {
            if (! is_string($valor) || ! is_numeric($clave) || ! preg_match(self::FORMATO, $valor)) {
                continue;
            }

            $partes = explode(',', $valor);
            $salida[(int) $clave] = [
                'x' => (int) $partes[0],
                'y' => (int) $partes[1],
                'girado' => isset($partes[2]),
            ];
        }

        ksort($salida);

        return $salida;
    }

    /**
     * El bloque rotado 90° sobre el piso: el bulto y la rejilla giran JUNTOS.
     *
     * Girar solo la rejilla dejaría las cajas apuntando para donde estaban y el bloque
     * ocuparía un rectángulo que no es el de ninguna caja real. El alto no se toca a
     * propósito (ver el encabezado de la clase).
     *
     * La carga que va ENCIMA de un pallet gira sola y no hace falta tocarla acá: el
     * `interior` se arma después, y ya se rota comparando el largo con que quedó colocado
     * el pallet contra el suyo propio (ver `SimuladorCargaController::interiorDelPallet`).
     * Es el mismo camino que usa el giro que hace el motor.
     *
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private static function girar(array $b): array
    {
        foreach (['orientacion', 'rejilla'] as $k) {
            [$b[$k]['largo'], $b[$k]['ancho']] = [$b[$k]['ancho'], $b[$k]['largo']];
        }

        return $b;
    }

    /** La huella en el piso: [x0, y0, x1, y1] en cm. */
    private static function huella(array $b): array
    {
        return [
            $b['x'],
            $b['y'],
            $b['x'] + $b['rejilla']['largo'] * $b['orientacion']['largo'],
            $b['y'] + $b['rejilla']['ancho'] * $b['orientacion']['ancho'],
        ];
    }

    /**
     * Los pares de bloques que se pisan en el piso.
     *
     * Se comparan las huellas y NO el volumen: dos bloques encimados en planta son un
     * problema aunque uno sea bajito, porque el visor los dibuja atravesándose y en el
     * camión no se puede. Que uno pudiera ir arriba del otro es una decisión de estiba
     * que el motor no evaluó.
     *
     * @return list<array{0:int,1:int}>
     */
    private static function choques(array $bloques): array
    {
        $pares = [];
        $n = count($bloques);

        for ($i = 0; $i < $n; $i++) {
            [$ax0, $ay0, $ax1, $ay1] = self::huella($bloques[$i]);
            for ($j = $i + 1; $j < $n; $j++) {
                [$bx0, $by0, $bx1, $by1] = self::huella($bloques[$j]);
                // Tocarse no es pisarse: `<` estricto, así dos bloques pegados —que es
                // como se carga— no salen marcados en rojo.
                if ($ax0 < $bx1 && $bx0 < $ax1 && $ay0 < $by1 && $by0 < $ay1) {
                    $pares[] = [$i, $j];
                }
            }
        }

        return $pares;
    }

    /**
     * Los bloques que sobresalen de la caja del camión.
     *
     * @return list<int>
     */
    private static function fuera(array $bloques, int $largoCm, int $anchoCm): array
    {
        $salida = [];

        foreach ($bloques as $i => $b) {
            [$x0, $y0, $x1, $y1] = self::huella($b);
            if ($x0 < 0 || $y0 < 0 || $x1 > $largoCm || $y1 > $anchoCm) {
                $salida[] = $i;
            }
        }

        return $salida;
    }
}
