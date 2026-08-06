<?php

namespace Tests\Feature\Admin;

use App\Models\BodegaTraslado;
use App\Models\User;
use App\Services\Inventario\TrasladoBodegaExcel;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use XMLReader;
use ZipArchive;

/**
 * La descarga de la orden de traslado (M04-F2) con el criterio DURO de la
 * casa: un .xlsx con XML inválido no «se ve raro», directamente NO ABRE en
 * Excel — XMLReader recorrido entero, no un parseo laxo (idioma de
 * PlanCartaGanttExcelTest, lección del 31-07).
 */
class TrasladoBodegaExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function orden(): BodegaTraslado
    {
        $orden = BodegaTraslado::factory()->create();
        $orden->items()->create([
            'producto_id' => \App\Models\Producto::factory()->create()->id,
            'nombre' => 'Botellón 20L retornable',
            'sku' => 'BOT-20R',
            'cantidad' => 40,
        ]);

        return $orden;
    }

    /** @return array<string, string> parte => contenido */
    private function partes(string $binario): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $binario);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'El binario descargado no es un zip válido.');

        $partes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $partes[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($tmp);

        return $partes;
    }

    public function test_descarga_con_los_headers_de_la_casa(): void
    {
        $orden = $this->orden();

        $res = $this->actingAs($this->admin())->get(route('admin.bodegas.traslados.excel', $orden));

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $res->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString(
            'Orden_Traslado_'.$orden->id.'_DaliGo_'.FechaNegocio::hoy().'.xlsx',
            $res->headers->get('Content-Disposition'),
        );
    }

    public function test_todas_las_partes_son_xml_bien_formado_con_el_criterio_duro(): void
    {
        $orden = $this->orden();
        $binario = $this->actingAs($this->admin())
            ->get(route('admin.bodegas.traslados.excel', $orden))->getContent();
        $partes = $this->partes($binario);

        // Las 6 partes mínimas del formato, presentes.
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $ruta) {
            $this->assertArrayHasKey($ruta, $partes, "Falta la parte {$ruta}.");
        }

        foreach ($partes as $nombre => $contenido) {
            if (! str_ends_with($nombre, '.xml') && ! str_ends_with($nombre, '.rels')) {
                continue;
            }

            $lector = new XMLReader;
            $this->assertTrue($lector->XML($contenido, 'UTF-8', LIBXML_NONET), "No se pudo abrir {$nombre} como XML.");
            $previo = libxml_use_internal_errors(true);
            libxml_clear_errors();
            while (@$lector->read()) {
                // Recorrer entero: los errores aparecen al leer, no al abrir.
            }
            $errores = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previo);

            $this->assertSame([], array_map(fn ($e) => trim($e->message), $errores), "XML mal formado en {$nombre}.");
        }
    }

    public function test_la_hoja_trae_la_foto_y_el_total(): void
    {
        $orden = $this->orden();
        $binario = $this->actingAs($this->admin())
            ->get(route('admin.bodegas.traslados.excel', $orden))->getContent();
        $hoja = $this->partes($binario)['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('Botellón 20L retornable', $hoja);
        $this->assertStringContainsString('BOT-20R', $hoja);
        $this->assertStringContainsString('<v>40</v>', $hoja, 'La cantidad va como NÚMERO, no como texto.');
        $this->assertStringContainsString('TOTAL', $hoja);
        $this->assertStringContainsString('state="frozen"', $hoja, 'Cabecera congelada: la hoja es usable.');
        $this->assertStringContainsString($orden->bodega->nombre.' → '.$orden->destino->nombre, $hoja);
    }

    public function test_sin_permiso_no_hay_descarga(): void
    {
        $orden = $this->orden();
        $gestor = User::factory()->create();
        $gestor->givePermissionTo('manage productos');

        $this->actingAs($gestor)->get(route('admin.bodegas.traslados.excel', $orden))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
    }

    public function test_nombre_de_archivo_fechado_con_el_dia_de_negocio(): void
    {
        $orden = $this->orden();

        $this->assertSame(
            'Orden_Traslado_'.$orden->id.'_DaliGo_'.FechaNegocio::hoy().'.xlsx',
            TrasladoBodegaExcel::nombreArchivo($orden),
        );
    }
}
