<?php

namespace Tests\Feature;

use App\Models\PlanExtra;
use App\Models\User;
use App\Services\Plan\CartaGanttExcel;
use App\Support\PlanProyecto;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use XMLReader;
use ZipArchive;

/**
 * Descarga de la carta Gantt como Excel (/plan/excel, pedido del dueño
 * 03-08-2026): el archivo se GENERA en cada descarga desde PlanProyecto — la
 * misma fuente que la pagina /plan — asi que siempre sale al dia del repo.
 *
 * La verificacion clave es la de BUEN FORMATO con parser estricto: un .xlsx con
 * XML invalido no "se ve raro", directamente NO ABRE en Excel (leccion del
 * informe Word del 31-07: el chequeo laxo dio verde sobre un archivo que Word
 * rechazaba). XMLReader con retorno de errores es el criterio duro disponible
 * en PHP sin dependencias.
 */
class PlanCartaGanttExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    /** Descarga el archivo y lo devuelve abierto como zip. */
    private function descargar(): array
    {
        $res = $this->actingAs($this->admin())->get('/plan/excel');
        $res->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($tmp, $res->streamedContent ?? $res->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'El binario descargado no es un zip válido.');

        return [$zip, $tmp, $res];
    }

    // --- Acceso ---

    public function test_requiere_permiso_de_ver_el_plan(): void
    {
        $sinPermiso = tap(User::factory()->create())->assignRole('soplador');

        $this->actingAs($sinPermiso)->get('/plan/excel')->assertRedirect(route('dashboard'));
    }

    public function test_descarga_con_nombre_fechado_y_content_type_de_excel(): void
    {
        $res = $this->actingAs($this->admin())->get('/plan/excel');

        $res->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString(
            'Carta_Gantt_DaliGo_'.\App\Support\FechaNegocio::hoy().'.xlsx',
            (string) $res->headers->get('Content-Disposition'),
        );
    }

    // --- Estructura del archivo ---

    public function test_trae_las_partes_minimas_del_formato(): void
    {
        [$zip, $tmp] = $this->descargar();

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $parte) {
            $this->assertNotFalse($zip->locateName($parte), "Falta la parte $parte del xlsx.");
        }
        $zip->close();
        @unlink($tmp);
    }

    /**
     * TODAS las partes XML parsean con un parser estricto. Es el candado que
     * habria cazado el docx que Word rechazaba: un `<0/>` u otro nombre invalido
     * no desbalancea nada, pero no parsea.
     */
    public function test_todo_el_xml_del_archivo_esta_bien_formado(): void
    {
        [$zip, $tmp] = $this->descargar();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            $contenido = $zip->getFromIndex($i);

            $lector = new XMLReader;
            $this->assertTrue(
                $lector->XML($contenido, 'UTF-8', LIBXML_NONET),
                "No se pudo abrir $nombre como XML."
            );
            $previo = libxml_use_internal_errors(true);
            libxml_clear_errors();
            while (@$lector->read()) {
                // recorrer entero: los errores aparecen al leer, no al abrir
            }
            $errores = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previo);
            $lector->close();

            $this->assertSame([], array_map(fn ($e) => trim($e->message), $errores),
                "XML mal formado en $nombre.");
        }
        $zip->close();
        @unlink($tmp);
    }

    // --- Contenido ---

    public function test_la_hoja_trae_todos_los_modulos_y_el_avance_global(): void
    {
        [$zip, $tmp] = $this->descargar();
        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');

        // Cada item de MODULOS aparece (si agregan M18 al plan, el Excel lo trae
        // solo — no hay una segunda lista que actualizar).
        foreach (array_keys(PlanProyecto::MODULOS) as $key) {
            $this->assertStringContainsString(">$key<", $hoja, "El módulo $key no está en el Excel.");
        }

        $pct = PlanProyecto::tracker()['pct_global'];
        $this->assertStringContainsString("AVANCE GLOBAL: {$pct}%", $hoja);

        // Los 4 estados del semaforo de Carlos estan rotulados.
        foreach (['Realizada', 'En curso', 'Atrasada', 'No iniciada'] as $etiqueta) {
            $this->assertStringContainsString($etiqueta, $hoja);
        }

        // La forma del Excel de referencia: secciones por fase y el titulo DALI.
        $this->assertStringContainsString('DALI Cargos-Transporte', $hoja);
        $this->assertStringContainsString('FASE 1 ·', $hoja);
        $this->assertStringContainsString('FASE 2 ·', $hoja);
        $zip->close();
        @unlink($tmp);
    }

    /** La hoja 2 lleva el POR QUÉ de cada % (el fundamento del tracker). */
    public function test_la_hoja_de_avance_trae_los_fundamentos(): void
    {
        [$zip, $tmp] = $this->descargar();
        $hoja2 = (string) $zip->getFromName('xl/worksheets/sheet2.xml');

        $this->assertStringContainsString('Avance por m', $hoja2);
        // Un fundamento real del tracker (el de M04) viaja en la hoja.
        $fundamento = PlanProyecto::tracker()['filas']['M04']['fundamento'] ?? '';
        $this->assertNotSame('', $fundamento, 'El tracker ya no tiene fundamento para M04: ajustar el test.');
        $this->assertStringContainsString(
            htmlspecialchars(mb_substr($fundamento, 0, 30), ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            $hoja2,
        );
        $zip->close();
        @unlink($tmp);
    }

    public function test_los_extras_anotados_a_mano_tambien_viajan(): void
    {
        PlanExtra::create(['titulo' => 'Ambientes de homologacion e integracion', 'estado' => 'en_curso', 'avance' => 10, 'responsable' => 'Marcos']);

        [$zip, $tmp] = $this->descargar();
        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('Ambientes de homologacion e integracion', $hoja);
        $this->assertStringContainsString('TRABAJOS EXTRAS', $hoja);
        $zip->close();
        @unlink($tmp);
    }

    public function test_el_boton_esta_en_la_pagina_del_plan(): void
    {
        $this->actingAs($this->admin())->get('/plan')
            ->assertOk()
            ->assertSee('Descargar Excel')
            ->assertSee(route('plan.excel'), false);
    }

    // --- El semaforo de Carlos ---

    public function test_semaforo_realizada_atrasada_en_curso_no_iniciada(): void
    {
        $excel = new CartaGanttExcel;
        $hoy = \App\Support\FechaNegocio::hoy();
        $ayer = \Illuminate\Support\Carbon::parse($hoy)->subDay()->toDateString();
        $manana = \Illuminate\Support\Carbon::parse($hoy)->addDay()->toDateString();
        $futuro = \Illuminate\Support\Carbon::parse($hoy)->addMonth()->toDateString();

        // 100% es realizada aunque el fin haya pasado.
        $this->assertSame('realizada', $excel->semaforo(100, $ayer, $ayer));
        // <100% con fin pasado = atrasada (la regla pedida por Carlos).
        $this->assertSame('atrasada', $excel->semaforo(90, $ayer, $ayer));
        // En fecha y con avance = en curso; sin empezar y a futuro = no iniciada.
        $this->assertSame('en_curso', $excel->semaforo(30, $manana, $ayer));
        $this->assertSame('no_iniciada', $excel->semaforo(0, $futuro, $manana));
    }
}
