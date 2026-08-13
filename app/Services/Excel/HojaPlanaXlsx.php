<?php

namespace App\Services\Excel;

use Illuminate\Support\Carbon;

/**
 * Una hoja PLANA de Excel: titulo, linea de resumen, encabezados y una fila por
 * registro, nada mas.
 *
 * Nace del pedido del gerente general (13-08-2026): que los informes se puedan
 * bajar a Excel «como informacion» para tomar decisiones con ellos, sin decir
 * cuales. Justamente por eso lo que sirve NO es el informe exportado tal como se
 * ve en pantalla, sino la tabla cruda —una fila por hecho, columnas simples, sin
 * celdas combinadas ni subtitulos intercalados—: es la unica forma en que despues
 * puede armar sus propias tablas dinamicas y cruces sin volver a pedir un corte
 * nuevo cada vez. Los agregados que ya muestra la pantalla no se replican acá: se
 * derivan de estas filas en dos clics.
 *
 * El estilo lo declara la COLUMNA, no la celda: en una tabla plana todas las
 * celdas de una columna son del mismo tipo, asi que el llamador solo entrega
 * valores en orden y no puede desalinear el formato. Las fechas viajan como
 * serial de Excel (no como texto) para que ordenen y filtren de verdad, y la hoja
 * sale con autofiltro puesto y cabecera congelada, que es como se usan estas
 * planillas.
 *
 * Sin dependencias: el esqueleto del .xlsx lo pone EscritorXlsx y las filas
 * FilasXlsx. Aca vive solo la forma «tabla plana», compartida por las cuatro
 * hojas de los dos informes de Servicio Tecnico.
 */
final class HojaPlanaXlsx
{
    /** Serial de fechas de Excel: dia 0 = 30-12-1899. */
    private const EPOCA_EXCEL = '1899-12-30';

    /** Titulo, resumen y encabezados antes de la primera fila de datos. */
    private const FILAS_CABECERA = 3;

    /** Estilo => posicion en el cellXfs de estilos(). Contrato con FilasXlsx. */
    public const ESTILOS = [
        'texto' => 0, 'negrita' => 1, 'titulo' => 2, 'sub' => 3, 'cab' => 4,
        'numero' => 5, 'fecha' => 6, 'dinero' => 7, 'ajustado' => 8,
    ];

    private FilasXlsx $filas;

    private int $datos = 0;

    /**
     * @param  array<int, array{0: string, 1: int, 2: string}>  $columnas  [encabezado, ancho, estilo]
     */
    public function __construct(
        string $titulo,
        string $resumen,
        private readonly array $columnas,
    ) {
        $this->filas = new FilasXlsx(self::ESTILOS);
        $this->filas->celdas([[1, $titulo, 'titulo']]);
        $this->filas->celdas([[1, $resumen, 'sub']]);

        $encabezados = [];
        foreach ($this->columnas as $i => [$encabezado]) {
            $encabezados[] = [$i + 1, $encabezado, 'cab'];
        }
        $this->filas->celdas($encabezados);
    }

    /**
     * Una fila de datos: los valores EN ORDEN de columna. Una columna 'fecha'
     * acepta Carbon|string|null y se convierte sola al serial de Excel.
     *
     * @param  array<int, mixed>  $valores
     */
    public function fila(array $valores): void
    {
        $celdas = [];
        foreach ($this->columnas as $i => [, , $estilo]) {
            $valor = $valores[$i] ?? null;
            $celdas[] = [$i + 1, $estilo === 'fecha' ? self::serial($valor) : $valor, $estilo];
        }

        $this->filas->celdas($celdas);
        $this->datos++;
    }

    public function cantidadFilas(): int
    {
        return $this->datos;
    }

    /** El <worksheet> completo de la hoja. */
    public function xml(): string
    {
        $cols = '<cols>';
        foreach ($this->columnas as $i => [, $ancho]) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$ancho.'" customWidth="1"/>';
        }
        $cols .= '</cols>';

        // El autofiltro necesita un rango valido incluso sin datos: con 0 filas
        // queda declarado sobre la sola cabecera (mismo criterio que FlotaExcel).
        $ultima = self::FILAS_CABECERA + max(1, $this->datos);
        $ultimaLetra = FilasXlsx::letra(count($this->columnas));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="'.self::FILAS_CABECERA.'" topLeftCell="A'.(self::FILAS_CABECERA + 1).'" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="14"/>'
            .$cols
            .'<sheetData>'.$this->filas->xml().'</sheetData>'
            .'<autoFilter ref="A'.self::FILAS_CABECERA.':'.$ultimaLetra.$ultima.'"/>'
            .'</worksheet>';
    }

    /**
     * Fecha (Carbon|Y-m-d|null) => serial de Excel. Null cuando no hay dato: una
     * celda vacia es «no ocurrio», distinto de una fecha cualquiera.
     */
    private static function serial(mixed $fecha): ?int
    {
        if (blank($fecha)) {
            return null;
        }

        return (int) Carbon::parse(self::EPOCA_EXCEL)
            ->diffInDays(Carbon::parse($fecha)->startOfDay());
    }

    /**
     * styles.xml compartido por las hojas planas. El indice de cada xf tiene que
     * coincidir con ESTILOS: la tabla y el mapa se escriben juntos (ver
     * EscritorXlsx).
     */
    public static function estilos(): string
    {
        // 164+ = formatos propios; los ids <164 los reserva Excel.
        $numFmts = '<numFmts count="2">'
            .'<numFmt numFmtId="164" formatCode="dd\-mm\-yyyy"/>'
            .'<numFmt numFmtId="165" formatCode="#,##0"/>'
            .'</numFmts>';

        $fonts = '<fonts count="5">'
            .'<font><sz val="10"/><name val="Arial"/></font>'                               // 0 normal
            .'<font><b/><sz val="10"/><name val="Arial"/></font>'                           // 1 negrita
            .'<font><b/><sz val="16"/><color rgb="FFC2410C"/><name val="Arial"/></font>'    // 2 titulo
            .'<font><sz val="9"/><color rgb="FF737373"/><name val="Arial"/></font>'         // 3 sub gris
            .'<font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'     // 4 blanca negrita
            .'</fonts>';

        $fills = '<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'      // 0 obligatorio
            .'<fill><patternFill patternType="gray125"/></fill>'   // 1 obligatorio
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF2B2B2B"/></patternFill></fill>' // 2 cabecera
            .'</fills>';

        $xf = fn (int $font, int $fill, int $numFmt = 0, bool $wrap = false) => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '')
            .($fill ? ' applyFill="1"' : '')
            .' applyFont="1"'
            .($wrap ? ' applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>' : '/>');

        $xfC = fn (int $font, int $fill, int $numFmt = 0) => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '')
            .($fill ? ' applyFill="1"' : '')
            .' applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';

        $cellXfs = '<cellXfs count="9">'
            .$xf(0, 0)              // 0 texto
            .$xf(1, 0)              // 1 negrita
            .$xf(2, 0)              // 2 titulo
            .$xf(3, 0)              // 3 sub
            .$xf(4, 2)              // 4 cab
            .$xfC(0, 0)             // 5 numero
            .$xfC(0, 0, 164)        // 6 fecha
            .$xf(0, 0, 165)         // 7 dinero
            .$xf(0, 0, 0, true)     // 8 ajustado
            .'</cellXfs>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$numFmts.$fonts.$fills
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .$cellXfs
            .'</styleSheet>';
    }
}
