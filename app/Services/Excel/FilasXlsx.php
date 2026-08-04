<?php

namespace App\Services\Excel;

/**
 * El buffer de filas de UNA hoja: se le van agregando celdas y devuelve el XML
 * que va adentro de <sheetData>. El envoltorio de la hoja —<cols>, el panel
 * congelado, el autofiltro— lo pone cada Excel, porque ahí sí se diferencian.
 *
 * Una instancia por hoja: lleva su propio contador de fila.
 */
final class FilasXlsx
{
    /** @var array<int, string> filas XML ya armadas */
    private array $filas = [];

    private int $fila = 0;

    /**
     * @param  array<string, int>  $estilos  nombre del estilo => su posición en el cellXfs de styles.xml.
     *                                       Es el contrato con la tabla de estilos de quien construye la hoja.
     */
    public function __construct(private readonly array $estilos) {}

    /**
     * Agrega una fila. Cada celda es [columna 1-based, valor|null, estilo].
     * Valor null (o cadena vacía) = celda solo con relleno.
     *
     * Excel exige las celdas de una fila EN ORDEN de columna y sin referencias
     * repetidas — si no, rechaza el archivo entero sin decir por qué, y el XML
     * sigue siendo bien formado, así que un candado de parseo no lo ve. El caso
     * real que lo destapó: en la carta Gantt la etiqueta del mes y la marca HOY
     * caen en la MISMA columna cuando el mes empieza esta semana. Por eso se
     * indexa por columna (la última celda gana: HOY pisa al mes, que es lo que
     * se quiere ver) y se ordena. **El ksort no es cosmético: es el candado.**
     *
     * @param  array<int, array{0: int, 1: mixed, 2: string}>  $celdas
     */
    public function celdas(array $celdas): void
    {
        $porCol = [];
        foreach ($celdas as $celda) {
            $porCol[$celda[0]] = $celda;
        }
        ksort($porCol);

        $this->fila++;
        $xml = '';
        foreach ($porCol as [$col, $valor, $estilo]) {
            $ref = self::letra($col).$this->fila;
            $s = $this->estilos[$estilo] ?? 0;
            if ($valor === null || $valor === '') {
                $xml .= "<c r=\"$ref\" s=\"$s\"/>";
            } elseif (is_int($valor) || is_float($valor)) {
                $xml .= "<c r=\"$ref\" s=\"$s\"><v>$valor</v></c>";
            } else {
                $texto = htmlspecialchars((string) $valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "<c r=\"$ref\" s=\"$s\" t=\"inlineStr\"><is><t xml:space=\"preserve\">$texto</t></is></c>";
            }
        }
        $this->filas[] = '<row r="'.$this->fila.'">'.$xml.'</row>';
    }

    /** Una fila en blanco (separador). */
    public function vacia(): void
    {
        $this->fila++;
        $this->filas[] = '<row r="'.$this->fila.'"/>';
    }

    /** El XML de las filas, para meter adentro de <sheetData>. */
    public function xml(): string
    {
        return implode('', $this->filas);
    }

    /** A, B, ..., Z, AA, AB... para una columna 1-based. */
    public static function letra(int $col): string
    {
        $s = '';
        while ($col > 0) {
            $col--;
            $s = chr(65 + ($col % 26)).$s;
            $col = intdiv($col, 26);
        }

        return $s;
    }
}
