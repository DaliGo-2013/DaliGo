<?php

namespace Tests\Unit\Excel;

use App\Services\Excel\EscritorXlsx;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Candados del esqueleto OOXML compartido. Lo que se vigila acá no es el
 * contenido de las hojas (eso lo cubren PlanCartaGanttExcelTest y
 * FlotaExcelTest sobre el archivo entero) sino el ARMADO del paquete, que es lo
 * que se dejó de escribir dos veces: que las partes estén, que cada hoja quede
 * declarada en los tres lugares que Excel cruza —content types, workbook y
 * rels— y que los rId de esos dos últimos coincidan. Un rId que no cierra deja
 * un archivo que no abre, y ningún parser lo nota.
 */
class EscritorXlsxTest extends TestCase
{
    private const HOJA = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>';

    private const ESTILOS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>';

    /** @return array<string, string> parte => su XML */
    private function partes(string $binario): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'escritor');
        file_put_contents($tmp, $binario);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'El binario no es un zip válido.');
        $partes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $partes[(string) $zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($tmp);

        return $partes;
    }

    public function test_una_hoja_trae_las_partes_minimas_del_formato(): void
    {
        $partes = $this->partes(EscritorXlsx::armar(['Flota' => self::HOJA], self::ESTILOS));

        $this->assertSame([
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
        ], array_keys($partes));

        $this->assertSame(self::HOJA, $partes['xl/worksheets/sheet1.xml']);
        $this->assertSame(self::ESTILOS, $partes['xl/styles.xml']);
    }

    public function test_cada_hoja_queda_declarada_en_los_tres_lugares_que_excel_cruza(): void
    {
        $partes = $this->partes(EscritorXlsx::armar([
            'Carta Gantt' => self::HOJA,
            'Avance por modulo' => self::HOJA,
            'Tercera' => self::HOJA,
        ], self::ESTILOS));

        for ($n = 1; $n <= 3; $n++) {
            $this->assertArrayHasKey("xl/worksheets/sheet$n.xml", $partes);
            $this->assertStringContainsString(
                "<Override PartName=\"/xl/worksheets/sheet$n.xml\"",
                $partes['[Content_Types].xml'],
                "La hoja $n no está declarada en [Content_Types].xml."
            );
            $this->assertStringContainsString(
                "Target=\"worksheets/sheet$n.xml\"",
                $partes['xl/_rels/workbook.xml.rels'],
                "La hoja $n no tiene relationship."
            );
        }

        $this->assertStringContainsString('<sheet name="Carta Gantt" sheetId="1"', $partes['xl/workbook.xml']);
        $this->assertStringContainsString('<sheet name="Avance por modulo" sheetId="2"', $partes['xl/workbook.xml']);
        $this->assertStringContainsString('<sheet name="Tercera" sheetId="3"', $partes['xl/workbook.xml']);
    }

    /**
     * El cruce que de verdad importa: TODO r:id del workbook tiene que existir
     * en el .rels y apuntar a la hoja de ese número. Es lo que rompe un cambio
     * distraído en el mapa de rId, y es invisible para un parser.
     */
    public function test_los_rid_del_workbook_apuntan_a_la_hoja_que_corresponde(): void
    {
        $partes = $this->partes(EscritorXlsx::armar([
            'Uno' => self::HOJA, 'Dos' => self::HOJA, 'Tres' => self::HOJA,
        ], self::ESTILOS));

        preg_match_all('/<sheet name="[^"]*" sheetId="(\d+)" r:id="(rId\d+)"\/>/', $partes['xl/workbook.xml'], $m);
        $this->assertCount(3, $m[1]);

        $rels = simplexml_load_string($partes['xl/_rels/workbook.xml.rels']);
        $destino = [];
        foreach ($rels->Relationship as $rel) {
            $destino[(string) $rel['Id']] = (string) $rel['Target'];
        }

        foreach ($m[1] as $i => $sheetId) {
            $rid = $m[2][$i];
            $this->assertArrayHasKey($rid, $destino, "El workbook usa $rid y el .rels no lo declara.");
            $this->assertSame("worksheets/sheet$sheetId.xml", $destino[$rid]);
        }

        // Y styles.xml se queda con rId2 pase lo que pase (ver EscritorXlsx::rIdHoja).
        $this->assertSame('styles.xml', $destino['rId2']);
    }

    public function test_el_nombre_de_la_hoja_se_escapa(): void
    {
        $partes = $this->partes(EscritorXlsx::armar(['Flota & "extras"' => self::HOJA], self::ESTILOS));

        $this->assertStringContainsString('name="Flota &amp; &quot;extras&quot;"', $partes['xl/workbook.xml']);
        $this->assertNotFalse(simplexml_load_string($partes['xl/workbook.xml']));
    }

    public function test_todas_las_partes_son_xml_bien_formado(): void
    {
        $partes = $this->partes(EscritorXlsx::armar([
            'Carta Gantt' => self::HOJA, 'Avance por modulo' => self::HOJA,
        ], self::ESTILOS));

        $previo = libxml_use_internal_errors(true);
        foreach ($partes as $nombre => $xml) {
            libxml_clear_errors();
            $this->assertNotFalse(simplexml_load_string($xml), "La parte $nombre no es XML válido.");
            $this->assertSame([], libxml_get_errors(), "La parte $nombre tiene errores de XML.");
        }
        libxml_use_internal_errors($previo);
    }

    public function test_sin_hojas_no_se_arma_un_archivo_que_excel_no_puede_abrir(): void
    {
        $this->expectException(RuntimeException::class);

        EscritorXlsx::armar([], self::ESTILOS);
    }
}
