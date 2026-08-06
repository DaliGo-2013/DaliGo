<?php

namespace App\Services\Inventario;

use App\Models\BodegaTraslado;
use App\Services\Excel\EscritorXlsx;
use App\Services\Excel\FilasXlsx;
use App\Support\FechaNegocio;

/**
 * La orden de traslado como .xlsx (M04-F2): el documento que viaja a bodega
 * para ejecutar el movimiento en Bsale mientras D-005 no habilite el push.
 * Se genera al momento desde la FOTO guardada en la orden (no desde el stock
 * vivo): imprime lo que se decidió trasladar, no lo que haya ahora.
 *
 * Sobre el escritor de la casa (EscritorXlsx + FilasXlsx, molde hojaAvance
 * de CartaGanttExcel). Las fechas van como texto en la cabecera a propósito:
 * es un documento puntual de una orden, no una tabla filtrable — el contrato
 * «fechas como fechas» aplica a columnas de datos, que esta orden no tiene.
 */
class TrasladoBodegaExcel
{
    /** Índice estilo → posición en cellXfs (contrato con estilos()). */
    private const ESTILOS = [
        'texto' => 0, 'negrita' => 1, 'titulo' => 2, 'sub' => 3, 'cab' => 4,
        'numero' => 5, 'total' => 6,
    ];

    public function generar(BodegaTraslado $traslado): string
    {
        $filas = new FilasXlsx(self::ESTILOS);

        $filas->celdas([[1, 'Orden de traslado #'.$traslado->id.' — baja de bodega', 'titulo']]);
        $filas->celdas([[1, sprintf(
            '%s → %s · solicitada por %s el %s · estado: %s',
            $traslado->bodega->nombre,
            $traslado->destino->nombre,
            $traslado->solicitante_nombre,
            $traslado->created_at?->enChile()->format('d-m-Y H:i') ?? '—',
            $traslado->estado,
        ), 'sub']]);
        $filas->celdas([[1, 'Foto de existencias al momento de la orden (espejo Bsale). El movimiento físico se ejecuta en Bsale; la baja se completa sola cuando el espejo confirme stock 0.', 'sub']]);
        $filas->vacia();
        $filas->celdas([[1, 'Producto', 'cab'], [2, 'SKU', 'cab'], [3, 'Cantidad', 'cab']]);

        $total = 0.0;
        foreach ($traslado->items as $item) {
            $cantidad = (float) $item->cantidad;
            $total += $cantidad;
            $filas->celdas([
                [1, $item->nombre, 'texto'],
                [2, $item->sku ?: '—', 'texto'],
                // Entero cuando lo es (el espejo guarda 14,4 pero casi todo es unitario).
                [3, fmod($cantidad, 1.0) === 0.0 ? (int) $cantidad : $cantidad, 'numero'],
            ]);
        }

        $filas->celdas([
            [1, 'TOTAL', 'total'],
            [2, null, 'total'],
            [3, fmod($total, 1.0) === 0.0 ? (int) $total : $total, 'total'],
        ]);

        $cols = '<cols>'
            .'<col min="1" max="1" width="48" customWidth="1"/>'
            .'<col min="2" max="2" width="22" customWidth="1"/>'
            .'<col min="3" max="3" width="12" customWidth="1"/>'
            .'</cols>';

        $hoja = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="14"/>'
            .$cols
            .'<sheetData>'.$filas->xml().'</sheetData>'
            .'</worksheet>';

        return EscritorXlsx::armar(['Orden de traslado' => $hoja], $this->estilos());
    }

    /** Nombre de descarga, fechado con el día de negocio. */
    public static function nombreArchivo(BodegaTraslado $traslado): string
    {
        return 'Orden_Traslado_'.$traslado->id.'_DaliGo_'.FechaNegocio::hoy().'.xlsx';
    }

    /** El par mapa+tabla: cellXfs en el MISMO orden que ESTILOS. */
    private function estilos(): string
    {
        // fonts: 0 normal, 1 negrita, 2 título naranja, 3 sub gris, 4 blanca negrita.
        $fonts = '<fonts count="5">'
            .'<font><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FFC2410C"/><name val="Arial"/></font>'
            .'<font><sz val="9"/><color rgb="FF737373"/><name val="Arial"/></font>'
            .'<font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            .'</fonts>';

        // fills: 0 y 1 obligatorios; 2 cabecera oscura; 3 franja del total.
        $fills = '<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF2B2B2B"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFF7ED"/></patternFill></fill>'
            .'</fills>';

        $xf = fn (int $font, int $fill) => '<xf numFmtId="0" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($fill ? ' applyFill="1"' : '').' applyFont="1"/>';

        $cellXfs = '<cellXfs count="7">'
            .$xf(0, 0)   // 0 texto
            .$xf(1, 0)   // 1 negrita
            .$xf(2, 0)   // 2 titulo
            .$xf(3, 0)   // 3 sub
            .$xf(4, 2)   // 4 cab
            .$xf(0, 0)   // 5 numero
            .$xf(1, 3)   // 6 total (negrita sobre franja)
            .'</cellXfs>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$fonts.$fills
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .$cellXfs
            .'</styleSheet>';
    }
}
