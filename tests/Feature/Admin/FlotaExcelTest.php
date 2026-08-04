<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Logistica\FlotaExcel;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Descarga de la flota en Excel (pedido del dueño 04-08-2026).
 *
 * Lo que estos candados protegen, además de que el archivo abra: que el Excel
 * diga LA VERDAD sobre sí mismo. Un botón de exportar que filtra distinto que la
 * pantalla, o que no declara el filtro que aplicó, produce planillas que
 * circulan por correo diciendo ser la flota completa cuando son 10 filas.
 */
class FlotaExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefeLogistica(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_logistica');
    }

    /** Abre el .xlsx descargado y devuelve el XML de una de sus partes. */
    private function parte(string $binario, string $parte): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test-flota');
        file_put_contents($tmp, $binario);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'El binario descargado no es un zip válido.');
        $xml = $zip->getFromName($parte);
        $this->assertNotFalse($xml, "Falta la parte {$parte} del xlsx.");
        $zip->close();
        @unlink($tmp);

        return (string) $xml;
    }

    private function descargar(array $filtros = []): string
    {
        $res = $this->actingAs($this->jefeLogistica())
            ->get(route('admin.vehiculos.excel', $filtros));
        $res->assertOk();

        // getContent() y no streamedContent(): la respuesta es un Response normal
        // con el binario dentro (el .xlsx se arma completo en memoria, son
        // decenas de filas), y streamedContent() falla si no es un stream.
        return (string) $res->getContent();
    }

    // --- Acceso ------------------------------------------------------------

    public function test_exige_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.vehiculos.excel'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_quien_solo_ve_la_flota_puede_descargarla(): void
    {
        // Consultar no es editar: el perfil de solo lectura (el caso de cobranzas)
        // tiene que poder bajarse la planilla.
        $lector = tap(User::factory()->create())->givePermissionTo('ver vehiculos');
        Vehiculo::factory()->alDia()->create();

        $this->actingAs($lector)->get(route('admin.vehiculos.excel'))->assertOk();
    }

    // --- El archivo --------------------------------------------------------

    public function test_descarga_con_nombre_fechado_y_content_type_de_excel(): void
    {
        Vehiculo::factory()->alDia()->create();

        $res = $this->actingAs($this->jefeLogistica())->get(route('admin.vehiculos.excel'));

        $res->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertStringContainsString(
            'Vehiculos_DaliGo_'.FechaNegocio::hoy().'.xlsx',
            $res->headers->get('Content-Disposition') ?? '',
        );
    }

    public function test_trae_las_partes_minimas_del_formato(): void
    {
        Vehiculo::factory()->alDia()->create();
        $binario = $this->descargar();

        foreach ([
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
        ] as $parte) {
            $this->parte($binario, $parte);
        }
    }

    public function test_todo_el_xml_del_archivo_esta_bien_formado(): void
    {
        // El candado que caza el defecto silencioso: un XML mal armado deja el
        // archivo ilegible y Excel solo dice «formato no válido». Ya pasó una vez
        // en este proyecto (un array serializado como nombre de etiqueta), y el
        // chequeo por balance de etiquetas NO lo detecta.
        Vehiculo::factory()->alDia()->count(3)->create();
        Vehiculo::factory()->create(['observaciones' => 'Con caracteres raros: < > & " \' ñ á']);

        $binario = $this->descargar();
        $tmp = tempnam(sys_get_temp_dir(), 'test-flota');
        file_put_contents($tmp, $binario);

        $zip = new ZipArchive;
        $zip->open($tmp);
        $previo = libxml_use_internal_errors(true);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            if (! str_ends_with($nombre, '.xml') && ! str_ends_with($nombre, '.rels')) {
                continue;
            }
            libxml_clear_errors();
            $doc = simplexml_load_string((string) $zip->getFromIndex($i));
            $errores = libxml_get_errors();
            $this->assertNotFalse($doc, "La parte {$nombre} no es XML válido.");
            $this->assertSame([], array_map(fn ($e) => trim($e->message), $errores),
                "La parte {$nombre} tiene errores de XML.");
        }

        libxml_use_internal_errors($previo);
        $zip->close();
        @unlink($tmp);
    }

    // --- Contenido ---------------------------------------------------------

    public function test_trae_los_vehiculos_con_su_patente_y_su_estado(): void
    {
        Vehiculo::factory()->alDia()->create(['ppu' => 'PFBS22', 'alias' => 'HINO500', 'conductor_nombre' => 'Jorge Barros']);
        Vehiculo::factory()->alDia()->create(['ppu' => 'PLKX54', 'rt_vence' => now()->subDays(4)->toDateString()]);

        $hoja = $this->parte($this->descargar(), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('PFBS22', $hoja);
        $this->assertStringContainsString('HINO500', $hoja);
        $this->assertStringContainsString('Jorge Barros', $hoja);
        // Las dos columnas que la planilla a mano NO puede tener: el estado
        // calculado y el detalle de qué vence.
        $this->assertStringContainsString('Al día', $hoja);
        $this->assertStringContainsString('Vencido', $hoja);
        $this->assertStringContainsString('venció hace 4 días', $hoja);
    }

    public function test_las_fechas_viajan_como_fechas_de_excel_y_no_como_texto(): void
    {
        // Si viajaran como texto, la planilla no se podría ordenar ni filtrar por
        // vencimiento, que es justo para lo que se descarga.
        Vehiculo::factory()->create(['soap_vence' => '2026-12-08']);

        $hoja = $this->parte($this->descargar(), 'xl/worksheets/sheet1.xml');

        // 46364 = serial de Excel del 08-12-2026 (días desde el 30-12-1899).
        $this->assertStringContainsString('<v>46364</v>', $hoja);
        $this->assertStringNotContainsString('2026-12-08', $hoja);
        // Y el formato de fecha existe en los estilos.
        $this->assertStringContainsString('dd\-mm\-yyyy', $this->parte($this->descargar(), 'xl/styles.xml'));
    }

    public function test_el_semirremolque_dice_no_aplica_en_emisiones(): void
    {
        Vehiculo::factory()->alDia()->create(['tipo' => 'semirremolque', 'emisiones_vence' => null]);

        $this->assertStringContainsString('No aplica', $this->parte($this->descargar(), 'xl/worksheets/sheet1.xml'));
    }

    public function test_la_hoja_sale_con_autofiltro_y_cabecera_congelada(): void
    {
        // Se usa como planilla: sin los desplegables de filtro y sin fijar la
        // cabecera, 25 columnas son inmanejables.
        Vehiculo::factory()->alDia()->count(2)->create();
        $hoja = $this->parte($this->descargar(), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('<autoFilter ref="A4:Y6"/>', $hoja);
        $this->assertStringContainsString('state="frozen"', $hoja);
    }

    // --- Que el archivo diga la verdad sobre sí mismo ----------------------

    public function test_respeta_el_filtro_de_la_pantalla(): void
    {
        Vehiculo::factory()->alDia()->create(['ppu' => 'ALDIA11']);
        Vehiculo::factory()->alDia()->create(['ppu' => 'VENCE22', 'rt_vence' => now()->subDay()->toDateString()]);

        $hoja = $this->parte($this->descargar(['doc' => Vehiculo::DOC_VENCIDO]), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('VENCE22', $hoja);
        $this->assertStringNotContainsString('ALDIA11', $hoja);
    }

    public function test_el_archivo_declara_el_filtro_que_se_le_aplico(): void
    {
        // Sin esto, un Excel de 10 filas circula por correo como si fuera la
        // flota completa y nadie puede saberlo mirándolo.
        Vehiculo::factory()->alDia()->create(['base' => 'Coquimbo']);

        $hoja = $this->parte($this->descargar(['doc' => Vehiculo::DOC_AL_DIA, 'base' => 'Coquimbo']), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('Filtro aplicado:', $hoja);
        $this->assertStringContainsString('base Coquimbo', $hoja);
    }

    public function test_sin_filtros_lo_dice_igual(): void
    {
        Vehiculo::factory()->alDia()->create();

        $this->assertStringContainsString(
            'Flota completa (sin filtros)',
            $this->parte($this->descargar(), 'xl/worksheets/sheet1.xml'),
        );
    }

    public function test_el_resumen_cuenta_lo_que_el_archivo_trae_y_no_la_flota_entera(): void
    {
        Vehiculo::factory()->alDia()->count(3)->create();
        Vehiculo::factory()->alDia()->create(['ppu' => 'VENCE22', 'rt_vence' => now()->subDay()->toDateString()]);

        $hoja = $this->parte($this->descargar(['doc' => Vehiculo::DOC_VENCIDO]), 'xl/worksheets/sheet1.xml');

        // Filtrado a los vencidos: 1 vehículo, no 4.
        $this->assertStringContainsString('1 vehículo', $hoja);
        $this->assertStringContainsString('1 con documento vencido', $hoja);
    }

    public function test_una_flota_vacia_no_revienta(): void
    {
        $hoja = $this->parte($this->descargar(), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('0 vehículos', $hoja);
        // El autofiltro necesita un rango válido aunque no haya datos.
        $this->assertStringContainsString('<autoFilter ref="A4:Y5"/>', $hoja);
    }

    // --- El botón ----------------------------------------------------------

    public function test_el_boton_esta_en_el_listado_y_lleva_los_filtros_puestos(): void
    {
        Vehiculo::factory()->alDia()->create();

        $this->actingAs($this->jefeLogistica())
            ->get(route('admin.vehiculos.index', ['doc' => Vehiculo::DOC_VENCIDO, 'base' => 'Mirador']))
            ->assertOk()
            ->assertSee('Descargar Excel')
            // El href arrastra el filtro: si no, el botón baja otra cosa que la
            // que el usuario está mirando. Con escape=true (el default) el `&`
            // esperado se compara como `&amp;`, que es lo que hay en el HTML.
            ->assertSee(route('admin.vehiculos.excel', ['doc' => Vehiculo::DOC_VENCIDO, 'base' => 'Mirador']));
    }

    public function test_el_nombre_del_archivo_lleva_el_dia_de_negocio(): void
    {
        $this->assertSame('Vehiculos_DaliGo_'.FechaNegocio::hoy().'.xlsx', FlotaExcel::nombreArchivo());
    }
}
