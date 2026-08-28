<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\AgendaTrabajoRepuesto;
use App\Models\Notificacion;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Repuestos que el técnico industrial usa en terreno (pedido del dueño,
 * 14-08-2026): los declara al cerrar el trabajo, junto con el paso a paso.
 *
 * LA REGLA QUE ESTOS CANDADOS PROTEGEN, y que es la razón de ser del lote:
 *
 * 1. SIN PRECIOS. Al técnico industrial le pagan por arreglar e instalar, no por
 *    cobrarle al cliente; la cotización formal la hacen el vendedor y el jefe de
 *    ventas. Por eso su buscador de repuestos es un endpoint APARTE del taller: el
 *    del taller devuelve `precio`, y reusarlo dejaría el precio viajando al
 *    navegador aunque ninguna pantalla lo pinte. El candado mira la RESPUESTA, no
 *    la vista, porque es la respuesta la que se puede leer con las herramientas
 *    del navegador.
 *
 * 2. SIN DESCUENTO DE STOCK. El inventario se descuenta con la factura o boleta
 *    del vendedor —Bsale descuenta al facturar— y el técnico no emite documentos.
 *    Descontar también desde acá consumiría el repuesto DOS VECES. Lo que sí
 *    aporta el código es dejar al vendedor armar esa factura sin volver a
 *    preguntarle nada al técnico.
 *
 * 3. TAMBIÉN EN EL «NO REALIZADO». Una visita que no se pudo terminar igual gasta
 *    repuestos (se cambió el filtro y faltó la membrana). Si solo se guardaran al
 *    marcar realizado, ese consumo no quedaría en ninguna parte.
 */
class RepuestosTerrenoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        Mail::fake();
    }

    private function tecnico(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico_industrial');
    }

    private function jefeVentas(): User
    {
        return tap(User::factory()->create(['email' => 'jefe@impdali.cl']))->assignRole('jefe_ventas');
    }

    private function trabajo(): AgendaTrabajo
    {
        return AgendaTrabajo::factory()->create([
            'fecha' => '2026-08-10',
            'tipo' => 'mantencion',
            'estado' => 'agendado',
            'cliente_nombre' => 'Embotelladora Curicó',
            'ciudad' => 'Curicó',
        ]);
    }

    /** @param  array<string, mixed>  $datos */
    private function cerrar(AgendaTrabajo $t, array $datos)
    {
        return $this->actingAs($this->tecnico())
            ->patch(route('admin.agenda-terreno.estado', $t), $datos);
    }

    // --- Lo que declara el técnico se guarda ----------------------------------

    public function test_el_tecnico_declara_los_repuestos_al_cerrar_con_su_codigo(): void
    {
        $t = $this->trabajo();

        $this->cerrar($t, [
            'estado' => 'realizado',
            'notas_tecnico' => 'Cambié la membrana y el filtro.',
            'repuestos' => [
                ['nombre' => 'Membrana RO 100 GPD', 'sku' => 'MEM-100', 'cantidad' => 2],
                // Escrito a mano: no está en el catálogo. Es un caso legítimo y
                // frecuente, no un dato faltante.
                ['nombre' => 'Abrazadera de 1/2 sin código', 'sku' => '', 'cantidad' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $repuestos = $t->fresh()->repuestos->keyBy('nombre');
        $this->assertCount(2, $repuestos);

        $this->assertSame('MEM-100', $repuestos['Membrana RO 100 GPD']->sku);
        $this->assertSame(2, $repuestos['Membrana RO 100 GPD']->cantidad);

        $this->assertNull(
            $repuestos['Abrazadera de 1/2 sin código']->sku,
            'El repuesto escrito a mano debe quedar con sku null, no con cadena vacía.'
        );
    }

    public function test_una_fila_sin_nombre_se_descarta(): void
    {
        $t = $this->trabajo();

        $this->cerrar($t, [
            'estado' => 'realizado',
            'notas_tecnico' => 'Solo limpieza.',
            'repuestos' => [
                ['nombre' => '', 'sku' => '', 'cantidad' => 1],
                ['nombre' => 'Filtro de papel', 'sku' => null, 'cantidad' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Filtro de papel'], $t->fresh()->repuestos->pluck('nombre')->all());
    }

    /**
     * El candado del punto 3. Muta contra la versión anterior, que guardaba los
     * repuestos solo si `estado === 'realizado'`: con esa condición este test se
     * pone rojo (0 repuestos guardados).
     */
    public function test_un_trabajo_no_realizado_tambien_registra_lo_que_se_uso(): void
    {
        $t = $this->trabajo();

        $this->cerrar($t, [
            'estado' => 'no_realizado',
            'notas_tecnico' => 'Cambié el filtro pero faltaba la membrana; hay que volver.',
            'repuestos' => [
                ['nombre' => 'Filtro de papel', 'sku' => 'FIL-PAP', 'cantidad' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('no_realizado', $t->estado);
        $this->assertCount(
            1,
            $t->repuestos,
            'Una visita que no se pudo terminar igual gasta repuestos: hay que registrarlos.'
        );
        $this->assertSame('FIL-PAP', $t->repuestos->first()->sku);
    }

    // --- El aviso a ventas los lleva adentro ----------------------------------

    /**
     * El vendedor tiene que poder facturar leyendo el correo. Si la lista obliga a
     * entrar a la app, la factura se arma preguntándole al técnico por teléfono,
     * que es exactamente lo que este campo existe para evitar.
     */
    public function test_el_aviso_a_ventas_lleva_los_repuestos_con_cantidad_y_codigo(): void
    {
        $jefe = $this->jefeVentas();
        $t = $this->trabajo();

        $this->cerrar($t, [
            'estado' => 'realizado',
            'notas_tecnico' => 'Mantención completa.',
            'repuestos' => [
                ['nombre' => 'Membrana RO 100 GPD', 'sku' => 'MEM-100', 'cantidad' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $aviso = Notificacion::where('evento', 'terreno.realizado')
            ->where('user_id', $jefe->id)
            ->first();

        $this->assertNotNull($aviso, 'El jefe de ventas no recibió el aviso del cierre.');
        $cuerpo = (string) $aviso->cuerpo;

        $this->assertStringContainsString('2 × Membrana RO 100 GPD', $cuerpo);
        $this->assertStringContainsString('MEM-100', $cuerpo, 'Sin el código el vendedor tiene que buscarlo a mano.');
        $this->assertStringNotContainsString('{repuestos}', $cuerpo, 'El placeholder quedó sin reemplazar.');
    }

    public function test_un_cierre_sin_repuestos_lo_dice_en_vez_de_dejar_el_hueco(): void
    {
        $jefe = $this->jefeVentas();
        $t = $this->trabajo();

        $this->cerrar($t, ['estado' => 'realizado', 'notas_tecnico' => 'Solo limpieza y ajuste.'])
            ->assertSessionHasNoErrors();

        $cuerpo = (string) Notificacion::where('user_id', $jefe->id)->first()?->cuerpo;

        $this->assertStringContainsString('No usó repuestos', $cuerpo);
        $this->assertStringNotContainsString('{repuestos}', $cuerpo);
    }

    // --- El buscador NO conoce los precios ------------------------------------

    /**
     * EL CANDADO CENTRAL DEL LOTE (punto 1). Mira la respuesta HTTP porque es lo
     * que se puede leer en la pestaña de red del navegador: que la vista no pinte
     * el precio no alcanza si el precio llegó.
     *
     * Muta contra la tentación de reusar `admin.servicio-tecnico.buscar-repuesto`,
     * que devuelve `precio` en cada sugerencia.
     */
    public function test_el_buscador_de_repuestos_del_tecnico_nunca_devuelve_precios(): void
    {
        Producto::factory()->create(['sku' => 'MEM-100', 'nombre' => 'Membrana RO 100 GPD']);

        $respuesta = $this->actingAs($this->tecnico())
            ->getJson(route('admin.agenda-terreno.buscar-repuesto', ['q' => 'Membrana']))
            ->assertOk();

        $sugerencias = $respuesta->json();
        $this->assertNotEmpty($sugerencias, 'El buscador no encontró el repuesto del catálogo.');

        // El código SÍ (es para lo que existe el buscador); el precio NO, en
        // ninguna de sus formas.
        $this->assertSame('MEM-100', $sugerencias[0]['sku']);

        foreach ($sugerencias as $s) {
            $claves = array_keys($s);
            $this->assertSame(
                [],
                array_intersect($claves, ['precio', 'precio_unitario', 'valor', 'precio_venta']),
                'El buscador del técnico industrial no puede devolver precios: '.implode(',', $claves)
            );
            $this->assertSame(['nombre', 'sku'], $claves, 'Solo nombre y código viajan al técnico.');
        }
    }

    /**
     * El técnico industrial no tiene 'manage servicio tecnico', así que el
     * buscador del taller le está cerrado — de ahí que este endpoint exista. Si
     * alguien "simplificara" apuntando la vista al del taller, el autocompletado
     * quedaría muerto para el único usuario que lo necesita.
     */
    public function test_el_tecnico_industrial_alcanza_su_buscador_y_no_el_del_taller(): void
    {
        $tecnico = $this->tecnico();

        $this->assertFalse($tecnico->can('manage servicio tecnico'));

        $this->actingAs($tecnico)
            ->getJson(route('admin.agenda-terreno.buscar-repuesto', ['q' => 'mem']))
            ->assertOk();

        // El del taller responde con el 403 del proyecto (D-014: redirect al
        // Inicio con el aviso), no con la lista.
        $this->actingAs($tecnico)
            ->get(route('admin.servicio-tecnico.buscar-repuesto', ['q' => 'mem']))
            ->assertRedirect()
            ->assertSessionHas('aviso');
    }

    public function test_el_historial_de_terreno_sugiere_lo_ya_escrito_a_mano(): void
    {
        AgendaTrabajoRepuesto::create([
            'agenda_trabajo_id' => $this->trabajo()->id,
            'nombre' => 'Abrazadera inoxidable 1/2',
            'sku' => null,
            'cantidad' => 1,
        ]);

        $sugerencias = $this->actingAs($this->tecnico())
            ->getJson(route('admin.agenda-terreno.buscar-repuesto', ['q' => 'Abrazadera']))
            ->assertOk()
            ->json();

        $this->assertSame([['nombre' => 'Abrazadera inoxidable 1/2', 'sku' => null]], $sugerencias);
    }

    // --- Visita técnica: los repuestos son un PRONÓSTICO, no un consumo -------

    /**
     * EL FLUJO QUE DICTÓ EL DUEÑO (14-08-2026): la PRIMERA visita de Carlos a un
     * cliente es de revisión — va a ver qué hay que hacer y, si es una reparación,
     * qué repuestos se necesitan. Después vuelve a la sucursal, se junta con el
     * jefe de ventas y el vendedor, y con eso se arma la cotización para la
     * SEGUNDA visita, que es cuando el trabajo se hace.
     *
     * O sea: en una visita técnica el técnico NO INSTALA NADA. Sus repuestos son
     * lo que va a necesitar, no lo que gastó.
     *
     * POR QUÉ ESTO ES UN CANDADO Y NO UN DETALLE DE REDACCIÓN: si el informe los
     * contara como usados, mostraría consumo que nunca salió de bodega — y lo
     * contaría DOS VECES, porque en la segunda visita se declaran de nuevo al
     * usarlos de verdad. El número quedaría inflado justo en el informe que la
     * gerencia usa para decidir compras.
     */
    private function trabajoDeTipo(string $tipo): AgendaTrabajo
    {
        return AgendaTrabajo::factory()->create([
            'fecha' => '2026-08-10',
            'tipo' => $tipo,
            'estado' => 'agendado',
            'cliente_nombre' => 'Embotelladora Curicó',
            'ciudad' => 'Curicó',
        ]);
    }

    public function test_los_repuestos_de_una_visita_tecnica_no_cuentan_como_usados_en_el_informe(): void
    {
        // Misma cantidad, mismo repuesto, en los dos tipos de trabajo.
        foreach (['visita_tecnica', 'mantencion'] as $tipo) {
            AgendaTrabajoRepuesto::create([
                'agenda_trabajo_id' => $this->trabajoDeTipo($tipo)->id,
                'nombre' => 'Membrana RO 100 GPD',
                'sku' => 'MEM-100',
                'cantidad' => 4,
            ]);
        }

        $jefe = tap(User::factory()->create())->assignRole('admin');

        $vista = $this->actingAs($jefe)
            ->get(route('admin.servicio-tecnico.informe.industrial', ['anio' => 2026, 'mes' => 8]))
            ->assertOk();

        // Solo las 4 de la mantención: las 4 de la visita son pronóstico.
        $this->assertSame(
            4,
            (int) $vista->viewData('totalUnidadesRepuestos'),
            'El informe está contando como usado el pronóstico de la visita técnica.'
        );
    }

    public function test_el_excel_rotula_cada_linea_como_usada_o_por_cotizar(): void
    {
        AgendaTrabajoRepuesto::create([
            'agenda_trabajo_id' => $this->trabajoDeTipo('visita_tecnica')->id,
            'nombre' => 'Membrana por cotizar', 'sku' => 'MEM-100', 'cantidad' => 2,
        ]);
        AgendaTrabajoRepuesto::create([
            'agenda_trabajo_id' => $this->trabajoDeTipo('reparacion')->id,
            'nombre' => 'Filtro gastado', 'sku' => 'FIL-PAP', 'cantidad' => 1,
        ]);

        $jefe = tap(User::factory()->create())->assignRole('admin');

        // getContent() y no streamedContent(): el .xlsx se arma completo en memoria
        // y viaja en un Response normal (mismo criterio que InformeTerrenoExcel).
        $binario = (string) $this->actingAs($jefe)
            ->get(route('admin.servicio-tecnico.informe.industrial.excel', ['anio' => 2026, 'mes' => 8]))
            ->assertOk()
            ->getContent();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $binario);
        $z = new \ZipArchive;
        $this->assertTrue($z->open($tmp) === true, 'El binario descargado no es un zip válido.');
        $hoja = (string) $z->getFromName('xl/worksheets/sheet2.xml');
        $z->close();
        @unlink($tmp);

        // Ninguna de las dos se pierde, y se distinguen: es una tabla de datos, el
        // que la filtra decide — pero tiene que poder distinguirlas.
        $this->assertStringContainsString('Registro', $hoja);
        $this->assertStringContainsString('Por cotizar', $hoja);
        $this->assertStringContainsString('Usado', $hoja);
        $this->assertStringContainsString('Membrana por cotizar', $hoja);
        $this->assertStringContainsString('Filtro gastado', $hoja);
    }

    public function test_el_rotulo_cambia_segun_el_tipo_de_visita(): void
    {
        $visita = $this->trabajoDeTipo('visita_tecnica');
        $trabajo = $this->trabajoDeTipo('reparacion');

        // Al técnico se le habla en segunda persona (pantalla de operario); a
        // ventas, en tercera (el aviso).
        $this->assertSame('Repuestos que vas a necesitar', $visita->repuestosEtiquetaFormulario());
        $this->assertSame('Repuestos que usaste', $trabajo->repuestosEtiquetaFormulario());
        $this->assertStringContainsString('se van a necesitar', $visita->repuestosTitulo());
        $this->assertSame('Repuestos usados', $trabajo->repuestosTitulo());

        // Y en la pantalla del técnico se lee el de la visita, con su explicación.
        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.agenda-terreno.index', ['dia' => '2026-08-10']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Repuestos que vas a necesitar', $html);
        $this->assertStringContainsString('ventas arma la cotización', $html);
    }

    public function test_el_aviso_de_una_visita_tecnica_dice_que_es_para_cotizar(): void
    {
        $jefe = $this->jefeVentas();
        $visita = $this->trabajoDeTipo('visita_tecnica');

        $this->cerrar($visita, [
            'estado' => 'realizado',
            'notas_tecnico' => 'Revisé la planta: hay que cambiar la membrana y la bomba.',
            'repuestos' => [
                ['nombre' => 'Membrana RO 100 GPD', 'sku' => 'MEM-100', 'cantidad' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $cuerpo = (string) Notificacion::where('user_id', $jefe->id)->first()?->cuerpo;

        // Ventas tiene que leer que esto es el insumo de la cotización, no un gasto.
        $this->assertStringContainsString('se van a necesitar', $cuerpo);
        $this->assertStringContainsString('2 × Membrana RO 100 GPD', $cuerpo);
        $this->assertStringNotContainsString('Repuestos usados', $cuerpo);
        $this->assertStringNotContainsString('{repuestos_titulo}', $cuerpo);
    }

    // --- La pantalla del técnico ----------------------------------------------

    public function test_la_pantalla_del_tecnico_ofrece_declarar_repuestos_y_no_habla_de_plata(): void
    {
        $this->trabajo();

        $html = $this->actingAs($this->tecnico())
            ->get(route('admin.agenda-terreno.index', ['dia' => '2026-08-10']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Repuestos que usaste', $html);

        // OJO: la URL viaja por `@js()`, y `json_encode` escapa las barras — en el
        // HTML dice `admin\/agenda-terreno\/…`. Hay que des-escapar ANTES de
        // comparar, y no solo para que pase el assert positivo: sin esto el assert
        // NEGATIVO de abajo pasaría siempre (la forma cruda nunca está en la
        // página) y sería un verde-engañoso. Primo del gotcha del `&amp;` en los
        // href [2026-08-13], del otro lado del escapado.
        $sinEscapes = str_replace('\\/', '/', $html);

        $this->assertStringContainsString(route('admin.agenda-terreno.buscar-repuesto'), $sinEscapes);

        // Ni un campo de precio ni el endpoint del taller: si aparecen, alguien
        // volvió a mezclar el cobro con el trabajo del técnico.
        $this->assertStringNotContainsString('precio_unitario', $html);
        $this->assertStringNotContainsString(route('admin.servicio-tecnico.buscar-repuesto'), $sinEscapes);
    }
}
