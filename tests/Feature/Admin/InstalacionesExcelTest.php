<?php

namespace Tests\Feature\Admin;

use App\Models\Instalacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Descarga del registro de instalaciones en Excel (pedido del técnico
 * industrial, 13-08-2026: el detalle mes por mes de sus trabajos, porque con eso
 * le pagan las horas extras).
 *
 * Lo que estos candados protegen es lo que hace que el archivo SIRVA para
 * cobrar:
 *
 * - Que los DÍAS por mes estén sumados y bien sumados. Es la cifra del pago: si
 *   el archivo la trae mal, se paga mal.
 * - Que vaya COMPLETO. El listado pagina de 25 en 25 y un respaldo de pago
 *   cortado en la página 1 es una liquidación incompleta — el error más caro
 *   posible acá, y además silencioso.
 * - Que exporte lo MISMO que la pantalla (mismos filtros): un archivo que
 *   filtra distinto que el listado hace discutir dos números que deberían ser
 *   uno.
 */
class InstalacionesExcelTest extends TestCase
{
    use RefreshDatabase;

    private const RUTA = 'admin.instalaciones.excel';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico_industrial');
    }

    /** Abre el .xlsx descargado y devuelve el XML de una de sus partes. */
    private function parte(string $binario, string $parte): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test-instal');
        file_put_contents($tmp, $binario);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'El binario descargado no es un zip válido.');
        $xml = $zip->getFromName($parte);
        $this->assertNotFalse($xml, "Falta la parte {$parte} del xlsx.");
        $zip->close();
        @unlink($tmp);

        return (string) $xml;
    }

    /**
     * Asserta el valor de UNA celda por su referencia (E4, E5…).
     *
     * No se compara contra `<v>N</v>` suelto: la hoja del resumen tiene ocho
     * columnas numéricas y un número cualquiera lo satisface otra. Costó un rojo
     * real: el assert de «6 días» pasaba/fallaba por el `<v>7</v>` de la columna
     * MES (julio), no por los días — el mismo verde-engañoso de la bitácora
     * (20-07), esta vez con números. El `s="…"` queda fuera del patrón a
     * propósito, para no acoplar el test a la tabla de estilos.
     */
    private function assertCelda(string $hoja, string $ref, int|string $valor): void
    {
        $this->assertMatchesRegularExpression(
            '/<c r="'.preg_quote($ref, '/').'"[^>]*><v>'.preg_quote((string) $valor, '/').'<\/v>/',
            $hoja,
            "La celda {$ref} no vale {$valor}."
        );
    }

    /** @param  array<string, mixed>  $filtros */
    private function descargar(array $filtros = []): string
    {
        $res = $this->actingAs($this->tecnico())->get(route(self::RUTA, $filtros));
        $res->assertOk();

        return (string) $res->getContent();
    }

    /** @param  array<string, mixed>  $extra */
    private function instalacion(array $extra = []): Instalacion
    {
        return Instalacion::create(array_merge([
            'fecha' => '2026-07-13',
            'cliente_nombre' => 'Agua Purificada Canto del Agua',
            'cliente_rut' => '76543210-9',
            'comuna_region' => 'Copiapó, Atacama',
            'categoria' => 'lavadora',
            'producto' => 'LAVADORA DE BOTELLONES 20L-220V',
            'instalacion' => true,
            'puesta_en_marcha' => true,
            'dias' => 2,
            'vendedor' => 'Luis Figueroa',
            'n_factura' => '250868',
        ], $extra));
    }

    // --- Acceso --------------------------------------------------------------

    public function test_exige_el_permiso_del_registro(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route(self::RUTA))
            ->assertRedirect(route('dashboard'));
    }

    /**
     * La ruta se declara ANTES del resource: si quedara después, 'excel' entraría
     * como {instalacion} y daría un 404 de modelo en vez del archivo.
     */
    public function test_la_ruta_no_choca_con_la_ficha(): void
    {
        $this->instalacion();

        $this->actingAs($this->tecnico())->get('/admin/instalaciones/excel')->assertOk();
    }

    // --- Forma del archivo ---------------------------------------------------

    public function test_trae_el_detalle_y_el_resumen_por_mes(): void
    {
        $binario = $this->descargar();

        $workbook = $this->parte($binario, 'xl/workbook.xml');
        $this->assertStringContainsString('name="Instalaciones"', $workbook);
        $this->assertStringContainsString('name="Resumen por mes"', $workbook);
    }

    public function test_el_detalle_trae_una_fila_por_instalacion(): void
    {
        $this->instalacion();

        $hoja = $this->parte($this->descargar(), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('Agua Purificada Canto del Agua', $hoja);
        $this->assertStringContainsString('LAVADORA DE BOTELLONES 20L-220V', $hoja);
        $this->assertStringContainsString('Copiapó, Atacama', $hoja);
        $this->assertStringContainsString('Luis Figueroa', $hoja);
        // Los booleanos se leen, no se programan.
        $this->assertStringContainsString('Sí', $hoja);
    }

    // --- La cifra del pago ---------------------------------------------------

    /**
     * El candado que de verdad importa: los días sumados por mes. Junio 4, julio
     * 2+3=5 y el total del período 9. Los meses salen en orden cronológico, así
     * que junio es la fila 4, julio la 5 y el total la 6; los días son la
     * columna E.
     */
    public function test_el_resumen_suma_los_dias_de_cada_mes(): void
    {
        $this->instalacion(['fecha' => '2026-07-13', 'dias' => 2]);
        $this->instalacion(['fecha' => '2026-07-02', 'dias' => 3, 'categoria' => 'llenadora']);
        $this->instalacion(['fecha' => '2026-06-25', 'dias' => 4, 'categoria' => 'planta']);

        $hoja = $this->parte($this->descargar(), 'xl/worksheets/sheet2.xml');

        $this->assertStringContainsString('Junio 2026', $hoja);
        $this->assertStringContainsString('Julio 2026', $hoja);
        $this->assertCelda($hoja, 'E4', 4);   // días de junio
        $this->assertCelda($hoja, 'E5', 5);   // días de julio
        $this->assertStringContainsString('TOTAL DEL PERÍODO', $hoja);
        $this->assertCelda($hoja, 'E6', 9);   // total del período
    }

    /**
     * Una instalación sin días cargados no rompe la suma ni inventa un día: el
     * mes suma solo lo que hay (6, no 7), y las dos instalaciones se cuentan
     * igual — no tener los días cargados no borra el trabajo de la lista.
     */
    public function test_una_instalacion_sin_dias_no_inventa_un_dia(): void
    {
        $this->instalacion(['fecha' => '2026-07-13', 'dias' => 6]);
        $this->instalacion(['fecha' => '2026-07-20', 'dias' => null]);

        $hoja = $this->parte($this->descargar(), 'xl/worksheets/sheet2.xml');

        $this->assertCelda($hoja, 'D4', 2);   // instalaciones del mes
        $this->assertCelda($hoja, 'E4', 6);   // días del mes
    }

    // --- Completo y fiel -----------------------------------------------------

    /**
     * El listado pagina de 25 en 25; el archivo NO. Con 30 registros tienen que
     * estar los 30 — el 26 es el que delata el corte.
     */
    public function test_no_se_corta_en_la_paginacion_del_listado(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->instalacion([
                'fecha' => '2026-07-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'cliente_nombre' => 'Cliente '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'dias' => 1,
            ]);
        }

        $hoja = $this->parte($this->descargar(), 'xl/worksheets/sheet1.xml');

        for ($i = 1; $i <= 30; $i++) {
            $nombre = 'Cliente '.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->assertStringContainsString($nombre, $hoja, "El Excel se cortó y dejó fuera a «{$nombre}».");
        }
    }

    public function test_respeta_el_periodo_y_la_categoria_de_la_pantalla(): void
    {
        $this->instalacion(['fecha' => '2026-07-13', 'cliente_nombre' => 'Julio Lavadora']);
        $this->instalacion(['fecha' => '2026-06-25', 'cliente_nombre' => 'Junio Lavadora']);
        $this->instalacion(['fecha' => '2026-07-08', 'cliente_nombre' => 'Julio Planta', 'categoria' => 'planta']);

        $hoja = $this->parte($this->descargar(['anio' => 2026, 'mes' => 7, 'categoria' => 'lavadora']), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('Julio Lavadora', $hoja);
        $this->assertStringNotContainsString('Junio Lavadora', $hoja);
        $this->assertStringNotContainsString('Julio Planta', $hoja);
    }

    /**
     * Y el archivo DICE qué período trae. Un respaldo de pago que no declara su
     * período se vuelve indiscutible en la dirección equivocada: no hay forma de
     * saber si son las horas de julio o de todo el año.
     */
    public function test_el_archivo_declara_su_periodo(): void
    {
        $this->instalacion(['fecha' => '2026-07-13', 'dias' => 2]);

        $hoja = $this->parte($this->descargar(['anio' => 2026, 'mes' => 7]), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('Julio 2026', $hoja);
        $this->assertStringContainsString('2 días trabajados', $hoja);
    }

    // --- El botón de descarga aparece UNA vez ---------------------------------

    /**
     * EL BOTÓN DE EXCEL APARECE EXACTAMENTE UNA VEZ, en los tres estados de la
     * pantalla. Desde el 14-08 (dueño) se ubica en DOS lugares según lo que se esté
     * viendo —junto a las tarjetas de año, para no gastar una fila entera, y suelto
     * abajo cuando esa línea no existe—, y las dos ubicaciones se excluyen con
     * condiciones negadas entre sí.
     *
     * Ese es exactamente el arreglo que se rompe en silencio: si las condiciones
     * dejan de ser complementarias, o salen DOS botones (y quien mira no sabe cuál
     * apretar) o NINGUNO (y la descarga se vuelve inalcanzable sin que nada falle).
     * Se cuenta la RUTA, que es lo único de la página que no puede aparecer por otro
     * motivo.
     */
    public function test_el_boton_de_excel_aparece_una_sola_vez_en_los_tres_estados(): void
    {
        $tecnico = $this->tecnico();
        $ruta = route('admin.instalaciones.excel');

        // 1) Sin historial todavía: no hay tarjetas de año donde ponerlo.
        $html = $this->actingAs($tecnico)->get(route('admin.instalaciones.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, $ruta), 'Sin historial el botón tiene que salir una vez.');

        // 2) Con historial y sin año abierto: va en la línea de las tarjetas de año.
        $this->instalacion();
        $html = $this->actingAs($tecnico)->get(route('admin.instalaciones.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, $ruta), 'Con tarjetas de año el botón tiene que salir una vez.');

        // Y va DENTRO del bloque del historial, ANTES del listado: si quedara
        // después seguiría siendo una fila propia y no se habría ahorrado nada.
        $posBoton = strpos($html, $ruta);
        $posListado = strpos($html, 'Registro de instalaciones y puestas en marcha');
        $this->assertNotFalse($posListado, 'No se encontró el ancla del listado.');
        $this->assertGreaterThan(
            $posListado,
            $posBoton,
            'El botón no está en el bloque del historial.'
        );

        // 3) Con un año abierto: arriba hay doce tarjetas de mes y el botón vuelve abajo.
        $html = $this->actingAs($tecnico)
            ->get(route('admin.instalaciones.index', ['anio' => 2026]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, $ruta), 'Con un año abierto el botón tiene que salir una vez.');
    }
}
