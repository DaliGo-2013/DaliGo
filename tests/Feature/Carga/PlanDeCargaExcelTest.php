<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\TipoBulto;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Descarga del plan de carga en Excel (pedido del dueño 10-08-2026): que el
 * resultado del simulador deje de vivir solo en la pantalla.
 *
 * Dos familias de candados. La de FORMATO es la de siempre y no se relaja: un
 * .xlsx con XML mal armado no «se ve raro», directamente NO ABRE, y el chequeo
 * por balance de etiquetas no lo detecta.
 *
 * La otra, y la que importa más acá: que la planilla diga LO MISMO que la
 * pantalla. Una descarga que calcula por su cuenta empieza a diferir de lo que el
 * usuario está mirando — es el defecto clásico de este tipo de botón, ya
 * documentado para el Excel de la flota.
 */
class PlanDeCargaExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private CamionSimulacion $hd35;

    private TipoBulto $bolsa;

    private TipoBulto $caja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->hd35 = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35',
            'largo_cm' => 430, 'ancho_cm' => 204, 'alto_cm' => 220,
            'peso_max_kg' => 1500, 'pasillo_cm' => 0, 'activo' => true,
        ]);
        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 5,
            'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => true, 'activo' => true,
        ]);
        $this->caja = TipoBulto::create([
            'nombre' => 'Caja de tapas', 'categoria' => 'cajas',
            'largo_cm' => 46, 'ancho_cm' => 37, 'alto_cm' => 42, 'peso_kg' => 10,
            'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);
    }

    private function bajar(array $params = []): string
    {
        $res = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.excel', $params + ['camion_id' => $this->hd35->id]));
        $res->assertOk();

        return (string) $res->getContent();
    }

    /** @return array<string, string> parte => XML */
    private function partes(string $binario): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'plan');
        file_put_contents($tmp, $binario);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'El binario descargado no es un zip válido.');
        $partes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $partes[(string) $zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($tmp);

        return $partes;
    }

    // --- Acceso -------------------------------------------------------------

    public function test_exige_el_mismo_permiso_que_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.carga.excel'))
            ->assertRedirect(route('dashboard'));
    }

    // --- El archivo ---------------------------------------------------------

    public function test_descarga_con_nombre_fechado_y_content_type_de_excel(): void
    {
        $res = $this->actingAs($this->vendedor)->get(route('admin.carga.excel', [
            'camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id,
        ]));

        $res->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertStringContainsString(
            'Plan_de_carga_'.FechaNegocio::hoy().'.xlsx',
            $res->headers->get('Content-Disposition') ?? '',
        );
    }

    public function test_trae_las_partes_minimas_del_formato(): void
    {
        $partes = $this->partes($this->bajar(['tipo_bulto_id' => $this->bolsa->id]));

        foreach ([
            '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml',
        ] as $parte) {
            $this->assertArrayHasKey($parte, $partes, "Falta la parte {$parte} del xlsx.");
        }
    }

    public function test_todo_el_xml_del_archivo_esta_bien_formado(): void
    {
        // El candado que caza el defecto silencioso: XML mal armado deja el archivo
        // ilegible y Excel solo dice «formato no válido». Con caracteres que hay que
        // escapar en el nombre del producto, que es por donde entra el texto libre.
        TipoBulto::create([
            'nombre' => 'Caja rara < > & " \' ñ á', 'categoria' => 'cajas',
            'largo_cm' => 40, 'ancho_cm' => 30, 'alto_cm' => 30, 'peso_kg' => 2,
            'unidades' => 1, 'apilable_max' => 4, 'soporta_peso_encima' => true,
            'orientacion_fija' => false, 'activo' => true,
        ]);

        $partes = $this->partes($this->bajar([
            'lineas' => [
                ['tipo' => $this->bolsa->id, 'cantidad' => 200],
                ['tipo' => $this->caja->id, 'cantidad' => 30],
            ],
        ]));

        $previo = libxml_use_internal_errors(true);
        foreach ($partes as $nombre => $xml) {
            libxml_clear_errors();
            $this->assertNotFalse(simplexml_load_string($xml), "La parte {$nombre} no es XML válido.");
            $this->assertSame([], array_map(fn ($e) => trim($e->message), libxml_get_errors()),
                "La parte {$nombre} tiene errores de XML.");
        }
        libxml_use_internal_errors($previo);
    }

    // --- Que diga lo mismo que la pantalla ----------------------------------

    public function test_el_cupo_maximo_de_la_planilla_es_el_de_la_pantalla(): void
    {
        // 420 botellones de pie en el HD35: el número verificado por el dueño.
        $hoja = $this->partes($this->bajar(['tipo_bulto_id' => $this->bolsa->id]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('<v>420</v>', $hoja, 'La planilla no trae el cupo de la pantalla.');
        $this->assertStringContainsString('Bolsa 5', $hoja);
        $this->assertStringContainsString('Hyundai HD35', $hoja);
    }

    public function test_la_carga_mixta_viaja_con_lo_que_falta_y_por_que(): void
    {
        // 600 botellones son 120 bolsas y en el HD35 entran 84: quedan 180 afuera.
        $hoja = $this->partes($this->bajar([
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 600]],
        ]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('NO CABE TODO', $hoja);
        $this->assertStringContainsString('<v>180</v>', $hoja, 'No dice cuántas quedaron afuera.');
        $this->assertStringContainsString('espacio', $hoja, 'No dice POR QUÉ quedaron afuera.');
    }

    /**
     * UNA LÍNEA «LO QUE QUEPA» NO PIDIÓ CERO.
     *
     * La planilla circula por correo y su columna «Pedidas» es NUMÉRICA: se suma, se filtra
     * y se ordena. Un 0 ahí no es un rótulo feo, es un dato falso que se propaga a cualquier
     * tabla dinámica que alguien arme después — y encima se lee como «no pidió nada» justo
     * en la línea que se cargó hasta el tope.
     *
     * Así que la celda va VACÍA (en Excel vacío no es cero) y la hoja DECLARA lo que esa
     * columna no puede decir. La nota no es adorno: sin ella, quien recibe el archivo no
     * puede distinguir «se pidió lo que quepa» de «falta el dato» (mismo criterio que el
     * export de Servicio Técnico, bitácora [2026-08-13]).
     */
    public function test_una_linea_hasta_llenar_no_dice_que_se_pidieron_cero(): void
    {
        $hoja = $this->partes($this->bajar([
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => '']],
        ]))['xl/worksheets/sheet1.xml'];

        // Lo que SÍ entró está en la planilla: es la mitad que prueba que la fila existe.
        $this->assertStringContainsString('<v>420</v>', $hoja, 'La línea abierta no llegó a la planilla.');
        $this->assertStringContainsString('Bolsa 5', $hoja);

        // Y la hoja dice qué se pidió, porque la celda no puede.
        $this->assertStringContainsString('lo que quepa', $hoja,
            'La hoja no declara por qué «Pedidas» viene vacía: se lee como un dato faltante.');

        // La fila del producto NO trae un cero. Se mira la fila 4 —cabecera en la 3— y no
        // todo el XML: un `<v>0</v>` puede aparecer legítimamente en otra parte (un índice
        // de estilo, un ancho), y buscarlo suelto haría pasar o fallar por la razón
        // equivocada.
        $fila = [];
        preg_match('/<row r="4".*?<\/row>/s', $hoja, $fila);
        $this->assertNotEmpty($fila, 'No se encontró la fila del producto.');
        $this->assertStringNotContainsString('<v>0</v>', $fila[0],
            'La celda de «Pedidas» dice 0: en una columna numérica eso se suma y miente.');
    }

    /**
     * EL DATO QUE JUSTIFICA LA PLANILLA: el orden de carga.
     *
     * Los números ya están en la pantalla. Lo que el andén no puede deducir sin
     * mirar el dibujo es qué bloque va contra la cabina y cuál contra la puerta.
     */
    public function test_trae_el_orden_de_carga_del_fondo_hacia_la_puerta(): void
    {
        $hoja = $this->partes($this->bajar([
            'lineas' => [
                ['tipo' => $this->bolsa->id, 'cantidad' => 100],
                ['tipo' => $this->caja->id, 'cantidad' => 40],
            ],
        ]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('ORDEN DE CARGA', $hoja);
        // Los dos productos aparecen numerados en la tabla de orden.
        $this->assertStringContainsString('Caja de tapas', $hoja);
        $this->assertStringContainsString('Bolsa 5', $hoja);
    }

    /** La planilla repite el aviso de la pantalla: los cupos son un TECHO. */
    public function test_dice_que_los_cupos_son_un_maximo_no_una_promesa(): void
    {
        $hoja = $this->partes($this->bajar(['tipo_bulto_id' => $this->bolsa->id]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('m', $hoja);
        $this->assertStringContainsString('Verificar contra la carga real', $hoja,
            'Se perdió el aviso de que el cupo es un techo sin calibrar.');
    }

    /**
     * Y si alguien movió los bloques a mano, la planilla lo dice.
     *
     * Es la hoja que se imprime y se le da al chofer: el orden de carga de más abajo sale
     * de esas posiciones, así que sin el aviso se lee como un plan que el motor verificó.
     * Las cantidades sí son las del cálculo — acomodar no descubre lugar nuevo.
     */
    public function test_avisa_cuando_los_bloques_se_acomodaron_a_mano(): void
    {
        $sinTocar = $this->partes($this->bajar(['tipo_bulto_id' => $this->bolsa->id]))['xl/worksheets/sheet1.xml'];
        $this->assertStringNotContainsString('A MANO', $sinTocar);

        $aMano = $this->partes($this->bajar([
            'tipo_bulto_id' => $this->bolsa->id,
            'acomodo' => ['0' => '100,20'],
            'acomodo_de' => 1,
        ]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('acomodaron A MANO', $aMano);
    }

    /**
     * Y si el camión ya salía con carga, la planilla lo dice.
     *
     * Sin eso, la hoja muestra la caja útil y la carga máxima del camión VACÍO y el
     * andén lee que puede subir todo eso — cuando el plan de abajo se calculó contra
     * bastante menos.
     */
    public function test_avisa_cuando_el_camion_ya_salia_con_carga(): void
    {
        $hoja = $this->partes($this->bajar([
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 100]],
            'ocupado_cm' => 200,
            'ocupado_kg' => 300,
        ]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('YA SALE CON CARGA', $hoja);
        $this->assertStringContainsString('2,00 m de piso', $hoja);
        $this->assertStringContainsString('300 kg', $hoja);
    }

    /**
     * En un reparto con paradas, el orden de carga dice ADEMÁS dónde baja cada bloque.
     *
     * Es justo acá donde importa: el que carga es quien decide si un bloque termina
     * contra la puerta o contra la cabina, y cargar en el orden correcto sin saber a
     * qué parada va cada cosa es media instrucción.
     */
    public function test_el_orden_de_carga_dice_en_que_parada_baja_cada_bloque(): void
    {
        $hoja = $this->partes($this->bajar([
            'lineas' => [
                ['tipo' => $this->bolsa->id, 'cantidad' => 100, 'parada' => 1],
                ['tipo' => $this->caja->id, 'cantidad' => 20, 'parada' => 2],
            ],
        ]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('Baja en', $hoja);
        $this->assertStringContainsString('Parada 1', $hoja);
        $this->assertStringContainsString('Parada 2', $hoja);
        $this->assertStringContainsString('se carga último', $hoja);
    }

    public function test_sin_paradas_la_planilla_no_agrega_la_columna(): void
    {
        // Una columna «Baja en» vacía en toda carga normal es ruido en la hoja que se
        // imprime.
        $hoja = $this->partes($this->bajar([
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 100]],
        ]))['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('ORDEN DE CARGA', $hoja);
        $this->assertStringNotContainsString('Baja en', $hoja);
    }

    public function test_el_boton_esta_en_el_menu_del_visor(): void
    {
        // Regla del dueño: todo lo nuevo va en el menú lateral, no suelto.
        $html = $this->actingAs($this->vendedor)
            ->get(route('admin.carga.index', ['camion_id' => $this->hd35->id, 'tipo_bulto_id' => $this->bolsa->id]))
            ->assertOk()->getContent();

        $desde = strpos($html, 'Herramientas');
        $menu = substr($html, $desde, strpos($html, '</aside>', $desde) - $desde);

        $this->assertStringContainsString('Plan de carga (Excel)', $menu,
            'El botón de descarga quedó fuera del menú lateral.');
    }
}
