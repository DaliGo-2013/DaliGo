<?php

namespace App\Services\Excel;

use RuntimeException;
use ZipArchive;

/**
 * El esqueleto de un .xlsx: recibe las hojas YA ARMADAS y el XML de estilos, y
 * devuelve el binario del archivo. Sin dependencias — un .xlsx es un ZIP de
 * XMLs y ZipArchive viene con PHP.
 *
 * Existe porque el mismo esqueleto —[Content_Types], los dos .rels, el
 * workbook y el orden del zip— estaba copiado en CartaGanttExcel y en
 * FlotaExcel: seis metodos identicos que había que arreglar dos veces. Lo que
 * NO vive acá es lo que sí es propio de cada Excel: el contenido de sus hojas
 * y su tabla de estilos (colores, formatos, anchos).
 *
 * Los estilos se pasan como XML y no se construyen acá a propósito: cada hoja
 * indexa sus celdas contra las posiciones de SU cellXfs (ver FilasXlsx), así
 * que la tabla y el mapa de estilos son un par que se escribe junto.
 */
final class EscritorXlsx
{
    /**
     * El .xlsx completo, listo para descargar.
     *
     * @param  array<string, string>  $hojas  nombre de la pestaña => XML completo de su <worksheet>, en orden
     * @param  string  $estilos  XML completo de xl/styles.xml
     */
    public static function armar(array $hojas, string $estilos): string
    {
        if ($hojas === []) {
            throw new RuntimeException('Un .xlsx necesita al menos una hoja.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el zip del Excel.');
        }

        $nombres = array_keys($hojas);

        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($hojas)));
        $zip->addFromString('_rels/.rels', self::relsRaiz());
        $zip->addFromString('xl/workbook.xml', self::workbook($nombres));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relsWorkbook(count($hojas)));
        $zip->addFromString('xl/styles.xml', $estilos);
        foreach (array_values($hojas) as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $xml);
        }
        $zip->close();

        $binario = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $binario;
    }

    /**
     * El rId de la hoja N. **styles.xml se queda con rId2 siempre**, así que
     * las hojas van rId1, rId3, rId4… Se ve raro y es deliberado: el id de los
     * estilos no se corre al agregar una hoja, y es el mapa que ya emitían las
     * dos clases (este refactor no cambió un byte de los archivos generados).
     */
    private static function rIdHoja(int $numero): string
    {
        return 'rId'.($numero === 1 ? 1 : $numero + 1);
    }

    private static function contentTypes(int $hojas): string
    {
        $overrides = '';
        for ($i = 1; $i <= $hojas; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$overrides
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function relsRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /** @param  array<int, string>  $nombres */
    private static function workbook(array $nombres): string
    {
        $sheets = '';
        foreach ($nombres as $i => $nombre) {
            $n = $i + 1;
            $sheets .= '<sheet name="'.htmlspecialchars($nombre, ENT_XML1 | ENT_QUOTES, 'UTF-8').'"'
                .' sheetId="'.$n.'" r:id="'.self::rIdHoja($n).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private static function relsWorkbook(int $hojas): string
    {
        $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        for ($n = 2; $n <= $hojas; $n++) {
            $rels .= '<Relationship Id="'.self::rIdHoja($n).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }
}
