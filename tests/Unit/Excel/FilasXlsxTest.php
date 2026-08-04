<?php

namespace Tests\Unit\Excel;

use App\Services\Excel\FilasXlsx;
use Tests\TestCase;

/**
 * Candados del escritor de filas COMPARTIDO por los dos Excel de la app
 * (carta Gantt y flota).
 *
 * Existen por el cambio de radio de explosión: cuando cada clase tenía su copia
 * de este código, romperlo arruinaba UN archivo; ahora rompe los dos a la vez.
 * Y lo que se rompe no lo ve ningún candado de parseo — Excel exige las celdas
 * en orden de columna y sin refs repetidas, y si no se cumple **rechaza el
 * archivo entero sin decir por qué**, con el XML perfectamente bien formado.
 * Comprobado: reordenando a mano las celdas de una fila del .xlsx, Excel se
 * niega a abrirlo y `simplexml_load_string` lo sigue dando por bueno.
 */
class FilasXlsxTest extends TestCase
{
    private const ESTILOS = ['texto' => 0, 'negrita' => 1, 'chip' => 7];

    private function filas(): FilasXlsx
    {
        return new FilasXlsx(self::ESTILOS);
    }

    /** @return array<int, string> las refs (A1, B1…) en el orden en que salieron */
    private function refs(string $xml): array
    {
        preg_match_all('/<c r="([A-Z]+\d+)"/', $xml, $m);

        return $m[1];
    }

    // --- Lo que Excel exige y el XML no delata ----------------------------

    public function test_las_celdas_salen_en_orden_de_columna_aunque_lleguen_desordenadas(): void
    {
        $filas = $this->filas();
        $filas->celdas([[5, 'e', 'texto'], [1, 'a', 'texto'], [3, 'c', 'texto'], [2, 'b', 'texto']]);

        $this->assertSame(['A1', 'B1', 'C1', 'E1'], $this->refs($filas->xml()));
    }

    public function test_dos_celdas_en_la_misma_columna_emiten_una_sola_ref_y_gana_la_ultima(): void
    {
        // El caso real que lo destapó: en la carta Gantt la etiqueta del mes y
        // la marca HOY caen en la MISMA columna cuando el mes empieza esta
        // semana. Debe quedar HOY (la última), no las dos.
        $filas = $this->filas();
        $filas->celdas([[1, 'ENE', 'texto'], [1, 'HOY', 'negrita']]);

        $xml = $filas->xml();
        $this->assertSame(['A1'], $this->refs($xml));
        $this->assertStringContainsString('HOY', $xml);
        $this->assertStringNotContainsString('ENE', $xml);
    }

    public function test_cada_fila_numera_sus_celdas_con_su_propia_fila(): void
    {
        $filas = $this->filas();
        $filas->celdas([[1, 'a', 'texto']]);
        $filas->vacia();
        $filas->celdas([[2, 'b', 'texto']]);

        $this->assertSame(['A1', 'B3'], $this->refs($filas->xml()));
        $this->assertStringContainsString('<row r="2"/>', $filas->xml());
    }

    // --- Tipos y escapado --------------------------------------------------

    public function test_los_numeros_viajan_como_numero_y_el_texto_como_inline_string(): void
    {
        // Si un número viajara como texto, la columna no se podría ordenar ni
        // sumar — que es justo para lo que se baja la planilla.
        $filas = $this->filas();
        $filas->celdas([[1, 46364, 'texto'], [2, 0.5, 'texto'], [3, 'Hola', 'texto']]);

        $xml = $filas->xml();
        $this->assertStringContainsString('<c r="A1" s="0"><v>46364</v></c>', $xml);
        $this->assertStringContainsString('<c r="B1" s="0"><v>0.5</v></c>', $xml);
        $this->assertStringContainsString('t="inlineStr"', $xml);
    }

    public function test_el_texto_se_escapa(): void
    {
        $filas = $this->filas();
        $filas->celdas([[1, 'Frenos < 5 mm & luz "check"', 'texto']]);

        $xml = $filas->xml();
        $this->assertStringContainsString('Frenos &lt; 5 mm &amp; luz &quot;check&quot;', $xml);
        $this->assertNotFalse(simplexml_load_string('<r>'.$xml.'</r>'));
    }

    public function test_null_y_cadena_vacia_dejan_la_celda_solo_con_su_estilo(): void
    {
        // Son las celdas de la barra de la carta Gantt: no llevan valor, solo
        // el color del tramo.
        $filas = $this->filas();
        $filas->celdas([[1, null, 'chip'], [2, '', 'chip']]);

        $this->assertSame('<row r="1"><c r="A1" s="7"/><c r="B1" s="7"/></row>', $filas->xml());
    }

    public function test_un_estilo_desconocido_cae_al_estilo_base_y_no_revienta(): void
    {
        $filas = $this->filas();
        $filas->celdas([[1, 'x', 'no-existe']]);

        $this->assertStringContainsString('s="0"', $filas->xml());
    }

    // --- Columna → letra ---------------------------------------------------

    public function test_la_letra_de_columna_cruza_bien_la_z(): void
    {
        // El borde: la columna 26 es Z y la 27 es AA. Un off-by-one acá manda
        // toda la fila a referencias que no existen.
        $this->assertSame('A', FilasXlsx::letra(1));
        $this->assertSame('Y', FilasXlsx::letra(25));   // la última de la flota
        $this->assertSame('Z', FilasXlsx::letra(26));
        $this->assertSame('AA', FilasXlsx::letra(27));
        $this->assertSame('AB', FilasXlsx::letra(28));
        $this->assertSame('AZ', FilasXlsx::letra(52));
        $this->assertSame('BA', FilasXlsx::letra(53));
    }

    public function test_cada_instancia_lleva_su_propio_contador(): void
    {
        // La carta Gantt escribe dos hojas: si el contador se arrastrara, la
        // hoja 2 empezaría en la fila 40 y pico.
        $primera = $this->filas();
        $primera->celdas([[1, 'a', 'texto']]);

        $segunda = $this->filas();
        $segunda->celdas([[1, 'b', 'texto']]);

        $this->assertSame(['A1'], $this->refs($segunda->xml()));
    }
}
