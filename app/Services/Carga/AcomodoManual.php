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
 *  3. UNA POSICIÓN VALE SI SIGUE SIENDO DEL MISMO PRODUCTO. Aplicar una posición sobre un
 *     bloque que ahora es de otra cosa sería mover carga ajena en silencio y con cara de
 *     verificada. Por eso el acomodo viaja con los PRODUCTOS para los que se armó
 *     (`acomodo_para`: un id de tipo de bulto por bloque) y cada posición se compara con el
 *     producto del bloque que hoy ocupa su lugar: la que coincide se aplica, la que no
 *     vuelve a donde la puso el motor.
 *
 *     El PRODUCTO y no el número de línea: cambiarle el producto a una línea, o reordenar
 *     la lista con los botones de mover, deja el mismo índice apuntando a otra cosa. Con el
 *     índice eso pasaba el control; con el id, no.
 *
 *     DECISIÓN DEL DUEÑO (13-08-2026): *«muchas veces los botellones se acomodan por
 *     cantidad y las cajas se acomodan a mano; lo mejor es conservar ambas»*. Antes el
 *     acomodo viajaba con un CONTADOR de bloques y cualquier cambio de cantidad lo tiraba
 *     entero: subir 20 botellones borraba el acomodo de las cajas, que era justo lo que él
 *     había hecho a mano. Con las líneas, cambiar una cantidad conserva las posiciones
 *     mientras el reparto en bloques no cambie.
 *
 *     Y de paso tapa un agujero del contador: dos resultados con el MISMO número de
 *     bloques pero de productos distintos pasaban el control y las posiciones se aplicaban
 *     igual — exactamente la carga ajena que este punto 3 dice evitar.
 *
 *     LO QUE SIGUE SIN CONSERVARSE, y es a propósito: si una línea cambia de CUÁNTOS
 *     bloques ocupa, los ordinales de las que vienen detrás se corren y sus posiciones
 *     dejan de coincidir. Ahí los bloques vuelven al lugar del cálculo y la pantalla lo
 *     dice. Podría arreglarse indexando por producto+ocurrencia en vez de por ordinal, pero
 *     cuando el reparto en bloques cambia las huellas también cambian, así que la posición
 *     vieja es tan probable que se pise como que sirva: volver a lo verificado es la salida
 *     honesta. Dos líneas del MISMO producto tampoco se distinguen entre sí, y no hace
 *     falta: mover carga idéntica al lugar de su gemela no mueve carga ajena.
 *
 *     El contador viejo (`acomodo_de`) se sigue aceptando para los links ya compartidos:
 *     el link ES el escenario y hay planes acomodados a mano circulando por WhatsApp.
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

    /** Formato de `acomodo_para`: el id de producto de cada bloque, en orden. */
    public const FORMATO_PARA = '/^\d{1,7}(,\d{1,7})*$/';

    /**
     * @param  array<int|string, string>  $crudo  el parámetro `acomodo` tal como llega
     * @param  ?int  $hechoPara  el `acomodo_de` VIEJO: para cuántos bloques se armó. Solo
     *                           manda cuando no llega `$paraProductos` (links ya compartidos)
     * @param  ?string  $paraProductos  el `acomodo_para`: un id de tipo de bulto por bloque,
     *                                  en orden, `3,3,5`
     */
    public function __construct(
        private readonly array $crudo = [],
        private readonly ?int $hechoPara = null,
        private readonly ?string $paraProductos = null,
    ) {}

    /**
     * Aplica el acomodo a los bloques del motor.
     *
     * @param  list<array{x:int,y:int,orientacion:array{largo:int,ancho:int,alto:int},rejilla:array{largo:int,ancho:int,alto:int},cantidad:int}>  $bloques
     * @param  array<int, int>  $productoPorLinea  qué producto (id de tipo de bulto) tiene
     *                                             hoy cada línea. Sin esto no se puede saber
     *                                             de quién era cada posición y se aplica como
     *                                             antes: lo usan los tests del servicio y
     *                                             cualquier llamador que no lleve líneas
     * @return array{bloques:list<array<string,mixed>>, activo:bool, movidos:int, ignorados:int, choques:list<array{0:int,1:int}>, fuera:list<int>, descartado:bool}
     */
    public function aplicar(array $bloques, int $largoCm, int $anchoCm, array $productoPorLinea = []): array
    {
        $posiciones = $this->posiciones();

        // Para qué PRODUCTO se guardó cada posición. `null` = no se puede comparar (link
        // viejo sin la lista, o un llamador que no pasó los productos): ahí manda el
        // contador de abajo, que es el comportamiento de antes.
        $productos = $productoPorLinea !== [] && $this->paraProductos !== null
            && preg_match(self::FORMATO_PARA, $this->paraProductos) === 1
                ? array_map('intval', explode(',', $this->paraProductos))
                : null;

        // EL CONTROL VIEJO, solo para los links que ya andan por ahí: un acomodo armado
        // para otra cantidad de bloques no es «casi» este, es otro, y se tira entero.
        $descartado = $posiciones !== [] && $productos === null
            && $this->hechoPara !== null && $this->hechoPara !== count($bloques);

        if ($posiciones === [] || $descartado) {
            return [
                'bloques' => $bloques,
                'activo' => false,
                'movidos' => 0,
                'ignorados' => 0,
                'choques' => [],
                'fuera' => [],
                'descartado' => $descartado,
            ];
        }

        $movidos = 0;
        // Las posiciones que se dejaron pasar porque el bloque de ese lugar ya es de otro
        // producto. Se cuentan para poder decirlo: un bloque que vuelve solo al lugar del
        // cálculo, sin aviso, se lee como que el acomodo no se guardó.
        $ignorados = 0;

        foreach ($posiciones as $i => $pos) {
            if (! isset($bloques[$i])) {
                continue;
            }

            // ¿El bloque que hoy ocupa este lugar es del mismo producto que cuando se
            // acomodó? Si no, la posición no se aplica: se quedaría la carga de otro en un
            // lugar que nadie eligió para ella.
            //
            // El bloque del cupo máximo y el del pallet no traen `linea` —son de un solo
            // producto— y ahí la línea es la 0, la misma con la que el controlador arma el
            // mapa. Sin ese default esos dos modos ignorarían el acomodo entero.
            if ($productos !== null
                && ($productos[$i] ?? -1) !== ($productoPorLinea[$bloques[$i]['linea'] ?? 0] ?? -1)) {
                $ignorados++;

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
            'ignorados' => $ignorados,
            'choques' => self::choques($bloques),
            'fuera' => self::fuera($bloques, $largoCm, $anchoCm),
            // Si NINGUNA posición sobrevivió, el acomodo se perdió completo y hay que
            // decirlo con las mismas palabras de siempre: «se descartó». Media docena de
            // bloques que volvieron solos a su lugar, sin cartel, se lee como un bug.
            'descartado' => $movidos === 0 && $ignorados > 0,
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
