<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\AgendaTrabajoRepuesto;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioRepuesto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Descarga de los dos informes de Servicio Tecnico en Excel (pedido del gerente
 * general, 13-08-2026: que el apartado de Informes «se pueda devolver en Excel»
 * para usarlo como datos y decidir con ellos, sin declarar para que).
 *
 * Lo que estos candados protegen no es que el archivo abra, sino que sirva para
 * eso: que sea una TABLA PLANA, COMPLETA y MANIPULABLE.
 *
 * - Completa: la pantalla muestra el top 15 de repuestos; el archivo no puede
 *   heredar ese recorte, porque un dato truncado en silencio se lee como «esto
 *   fue todo lo que pasó» y se decide sobre eso.
 * - Manipulable: las fechas tienen que viajar como FECHA de Excel, no como
 *   texto. Si van como texto no ordenan ni filtran ni entran a una tabla
 *   dinamica, y el archivo deja de servir justamente para lo que se pidio.
 * - Fiel: el universo exportado es el MISMO que muestra el informe. Un export
 *   que filtra distinto que la pantalla produce planillas que circulan por
 *   correo contradiciendo a la app.
 */
class InformeServicioTecnicoExcelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Serial de Excel del 01-08-2026, calculado APARTE para que el assert no
     * repita la formula del codigo: 36526 (01-01-2000) + 9497 (26 años, 7
     * bisiestos) + 212 (enero a julio de 2026) = 46235.
     */
    private const SERIAL_1_AGO_2026 = 46235;

    private const RUTA_TALLER = 'admin.servicio-tecnico.informe.dispensadores.excel';

    private const RUTA_TERRENO = 'admin.servicio-tecnico.informe.industrial.excel';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    /** Abre el .xlsx descargado y devuelve el XML de una de sus partes. */
    private function parte(string $binario, string $parte): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test-informe');
        file_put_contents($tmp, $binario);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'El binario descargado no es un zip válido.');
        $xml = $zip->getFromName($parte);
        $this->assertNotFalse($xml, "Falta la parte {$parte} del xlsx.");
        $zip->close();
        @unlink($tmp);

        return (string) $xml;
    }

    /** @param  array<string, mixed>  $filtros */
    private function descargar(string $ruta, array $filtros = []): string
    {
        $res = $this->actingAs($this->admin())->get(route($ruta, $filtros));
        $res->assertOk();

        // getContent() y no streamedContent(): el .xlsx se arma completo en
        // memoria y viaja en un Response normal (mismo criterio que FlotaExcel).
        return (string) $res->getContent();
    }

    /** @param  array<string, mixed>  $extra */
    private function orden(array $extra = []): OrdenServicio
    {
        // El estado se FIJA: OrdenServicioFactory lo sortea entre los 7 y un
        // assert sobre el estado seria flaky (bitacora 13-07).
        return OrdenServicio::factory()->create(array_merge([
            'codigo' => 'ST-TEST0001',
            'fecha_ingreso' => '2026-08-01',
            'estado' => 'entregado',
            'tipo_equipo' => 'dispensador',
            'cliente_nombre' => 'Cliente Planilla SA',
            'facturacion' => 'reparacion',
        ], $extra));
    }

    /** @param  array<string, mixed>  $extra */
    private function trabajo(array $extra = []): AgendaTrabajo
    {
        // Igual que arriba: el `tipo` de AgendaTrabajoFactory es aleatorio.
        return AgendaTrabajo::factory()->create(array_merge([
            'fecha' => '2026-08-01',
            'tipo' => 'mantencion',
            'estado' => 'agendado',
            'cliente_nombre' => 'Terreno Planilla SA',
        ], $extra));
    }

    // --- Acceso --------------------------------------------------------------

    public function test_cada_excel_exige_el_permiso_de_su_informe(): void
    {
        foreach ([self::RUTA_TALLER, self::RUTA_TERRENO] as $ruta) {
            $this->actingAs(User::factory()->create())
                ->get(route($ruta))
                ->assertRedirect(route('dashboard'));
        }
    }

    // --- El boton en la pantalla ---------------------------------------------

    /**
     * El enlace se asserta por RUTA y no por el texto del boton: es lo unico que
     * no puede satisfacer otra parte de la pagina por casualidad (doctrina
     * verde-engañoso, bitacora 20-07). Y lleva el periodo YA RESUELTO, para que
     * lo que se descarga sea lo que se esta viendo y no el mes por defecto.
     *
     * assertSee SIN el `false`: la URL viaja en un href, asi que el `&` de la
     * query string sale escapado como `&amp;` y buscar la cadena cruda no la
     * encuentra nunca (con `false` este test fallaba estando el boton puesto).
     */
    public function test_cada_informe_ofrece_descargar_el_periodo_que_se_esta_viendo(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.informe.dispensadores', ['anio' => 2026, 'mes' => 8]))
            ->assertOk()
            ->assertSee(route(self::RUTA_TALLER, ['anio' => 2026, 'mes' => 8]));

        $this->actingAs($this->admin())
            ->get(route('admin.servicio-tecnico.informe.industrial', ['anio' => 2026, 'mes' => 8]))
            ->assertOk()
            ->assertSee(route(self::RUTA_TERRENO, ['anio' => 2026, 'mes' => 8]));
    }

    // --- Forma del archivo ---------------------------------------------------

    public function test_el_excel_del_taller_trae_dos_hojas_con_autofiltro_y_cabecera_fija(): void
    {
        $binario = $this->descargar(self::RUTA_TALLER);

        $workbook = $this->parte($binario, 'xl/workbook.xml');
        $this->assertStringContainsString('name="Órdenes"', $workbook);
        $this->assertStringContainsString('name="Repuestos"', $workbook);

        // Las dos mañas de planilla que hacen que la tabla se pueda trabajar sin
        // prepararla a mano.
        $hoja = $this->parte($binario, 'xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<autoFilter', $hoja);
        $this->assertStringContainsString('state="frozen"', $hoja);
    }

    public function test_el_excel_de_terreno_trae_dos_hojas(): void
    {
        $workbook = $this->parte($this->descargar(self::RUTA_TERRENO), 'xl/workbook.xml');

        $this->assertStringContainsString('name="Trabajos"', $workbook);
        $this->assertStringContainsString('name="Repuestos"', $workbook);
    }

    // --- Tabla plana ---------------------------------------------------------

    public function test_la_hoja_de_ordenes_trae_una_fila_por_orden_con_sus_datos(): void
    {
        $this->orden();

        $hoja = $this->parte($this->descargar(self::RUTA_TALLER, ['anio' => 2026, 'mes' => 8]), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('ST-TEST0001', $hoja);
        $this->assertStringContainsString('Cliente Planilla SA', $hoja);
        $this->assertStringContainsString('Folio', $hoja);
    }

    public function test_la_hoja_de_repuestos_repite_el_contexto_de_su_orden(): void
    {
        $orden = $this->orden();
        OrdenServicioRepuesto::create([
            'orden_servicio_id' => $orden->id,
            'nombre' => 'Membrana 400 GPD',
            'sku' => 'REP-MEMB-400',
            'cantidad' => 3,
            'precio_unitario' => 2500,
        ]);

        $hoja = $this->parte($this->descargar(self::RUTA_TALLER, ['anio' => 2026, 'mes' => 8]), 'xl/worksheets/sheet2.xml');

        $this->assertStringContainsString('Membrana 400 GPD', $hoja);
        $this->assertStringContainsString('REP-MEMB-400', $hoja);
        // El subtotal viene calculado (3 x 2500): la fila se puede sumar sin
        // escribir una formula.
        $this->assertStringContainsString('<v>7500</v>', $hoja);
        // Y el contexto de la orden se REPITE en la fila del repuesto: es lo que
        // permite armar una tabla dinamica de esta hoja sola, sin BUSCARV.
        $this->assertStringContainsString('ST-TEST0001', $hoja);
        $this->assertStringContainsString('Cliente Planilla SA', $hoja);
    }

    public function test_el_archivo_no_hereda_el_top_15_de_la_pantalla(): void
    {
        $orden = $this->orden();
        for ($i = 1; $i <= 18; $i++) {
            OrdenServicioRepuesto::create([
                'orden_servicio_id' => $orden->id,
                'nombre' => 'Repuesto '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'cantidad' => 1,
                'precio_unitario' => 100,
            ]);
        }

        $hoja = $this->parte($this->descargar(self::RUTA_TALLER, ['anio' => 2026, 'mes' => 8]), 'xl/worksheets/sheet2.xml');

        for ($i = 1; $i <= 18; $i++) {
            $nombre = 'Repuesto '.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->assertStringContainsString($nombre, $hoja, "El Excel truncó y dejó fuera «{$nombre}».");
        }
    }

    public function test_las_fechas_van_como_fecha_de_excel_y_no_como_texto(): void
    {
        $this->orden(['fecha_ingreso' => '2026-08-01']);

        $hoja = $this->parte($this->descargar(self::RUTA_TALLER, ['anio' => 2026, 'mes' => 8]), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('<v>'.self::SERIAL_1_AGO_2026.'</v>', $hoja);
        $this->assertStringNotContainsString('2026-08-01', $hoja);
    }

    // --- Fidelidad con la pantalla -------------------------------------------

    public function test_respeta_el_periodo_elegido(): void
    {
        $this->orden();
        $this->orden(['codigo' => 'ST-OTROMES', 'fecha_ingreso' => '2026-07-15']);

        $hoja = $this->parte($this->descargar(self::RUTA_TALLER, ['anio' => 2026, 'mes' => 8]), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('ST-TEST0001', $hoja);
        $this->assertStringNotContainsString('ST-OTROMES', $hoja);
    }

    public function test_respeta_el_filtro_de_tipo_de_equipo(): void
    {
        $this->orden();
        $this->orden(['codigo' => 'ST-LAVADORA', 'tipo_equipo' => 'lavadora']);

        $hoja = $this->parte(
            $this->descargar(self::RUTA_TALLER, ['anio' => 2026, 'mes' => 8, 'tipo' => 'dispensador']),
            'xl/worksheets/sheet1.xml'
        );

        $this->assertStringContainsString('ST-TEST0001', $hoja);
        $this->assertStringNotContainsString('ST-LAVADORA', $hoja);
    }

    public function test_terreno_exporta_el_mismo_universo_que_el_informe(): void
    {
        $this->trabajo(['cliente_nombre' => 'Agendado SA']);
        $this->trabajo(['cliente_nombre' => 'Realizado SA', 'estado' => 'realizado']);
        $this->trabajo(['cliente_nombre' => 'Cancelado SA', 'estado' => 'cancelado']);
        $this->trabajo(['cliente_nombre' => 'Solicitado SA', 'estado' => 'solicitado']);

        $hoja = $this->parte($this->descargar(self::RUTA_TERRENO, ['anio' => 2026, 'mes' => 8]), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('Agendado SA', $hoja);
        $this->assertStringContainsString('Realizado SA', $hoja);
        // El informe cuenta solo agendados y realizados: el archivo tampoco puede
        // traer cancelados ni solicitudes sin coordinar.
        $this->assertStringNotContainsString('Cancelado SA', $hoja);
        $this->assertStringNotContainsString('Solicitado SA', $hoja);
    }

    public function test_terreno_trae_sus_repuestos_y_avisa_que_no_tienen_precio(): void
    {
        $trabajo = $this->trabajo();
        AgendaTrabajoRepuesto::create([
            'agenda_trabajo_id' => $trabajo->id,
            'nombre' => 'Filtro de papel',
            'cantidad' => 2,
        ]);

        $hoja = $this->parte($this->descargar(self::RUTA_TERRENO, ['anio' => 2026, 'mes' => 8]), 'xl/worksheets/sheet2.xml');

        $this->assertStringContainsString('Filtro de papel', $hoja);
        $this->assertStringContainsString('<v>2</v>', $hoja);
        $this->assertStringContainsString('Terreno Planilla SA', $hoja);
        // La hoja DICE que en terreno no hay SKU ni precio. Sin esa nota, quien
        // compare las dos planillas concluye que en terreno no se gastan
        // repuestos, cuando lo que pasa es que no se registran con codigo.
        $this->assertStringContainsString('no hay SKU ni precio', $hoja);
    }
}
