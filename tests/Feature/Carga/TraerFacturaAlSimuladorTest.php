<?php

namespace Tests\Feature\Carga;

use App\Models\CamionSimulacion;
use App\Models\Cliente;
use App\Models\DocumentoVenta;
use App\Models\DocumentoVentaDetalle;
use App\Models\Producto;
use App\Models\TipoBulto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * TRAER UNA FACTURA AL SIMULADOR (jefe de logística, 10-09-2026: «exportar documentos a la
 * sección de carga del camión para saber su capacidad total»).
 *
 * Tres cosas que fija este archivo y que la conversión sola no cubre:
 *
 *  · EL PERMISO ES DOBLE. El simulador es una calculadora con permiso propio que hasta hoy no
 *    leía nada operativo; leer facturas con solo `simular carga` lo volvería una puerta
 *    lateral a los documentos de venta. Se exige además `manage despachos`, el permiso que
 *    hoy da acceso a esos documentos.
 *  · UN DOCUMENTO ANULADO NO SE CARGA: 422 con el motivo, no un plan de algo que no sale.
 *  · EL AVISO DE LO QUE NO ENTRÓ SOBREVIVE LA RECARGA. Calcular es un GET que recarga la
 *    página, así que todo lo que viviera en el modal moriría justo cuando aparece el
 *    veredicto — y ese veredicto necesita la salvedad al lado. Los textos viajan en la URL y
 *    se dibujan junto a la lista de la carga.
 */
class TraerFacturaAlSimuladorTest extends TestCase
{
    use RefreshDatabase;

    private User $conAmbos;

    private User $soloSimula;

    private TipoBulto $bolsa;

    private Producto $botellon;

    private Producto $sinDeclarar;

    private DocumentoVenta $factura;

    private DocumentoVenta $anulada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->conAmbos = tap(User::factory()->create())->givePermissionTo(['simular carga', 'manage despachos']);
        $this->soloSimula = tap(User::factory()->create())->givePermissionTo('simular carga');

        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 5,
            'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true, 'activo' => true,
        ]);
        $this->botellon = Producto::create(['sku' => 'BOT20', 'nombre' => 'Botellón 20 L', 'activo' => true, 'tipo_bulto_id' => $this->bolsa->id]);
        $this->sinDeclarar = Producto::create(['sku' => 'DISP', 'nombre' => 'Dispensador LB-07B', 'activo' => true]);

        $cliente = Cliente::create(['razon_social' => 'Aguas del Sur']);
        $this->factura = $this->documento(4567, $cliente, [[$this->botellon, 200], [$this->sinDeclarar, 3]]);
        $this->anulada = $this->documento(4568, $cliente, [[$this->botellon, 10]], anulada: true);
    }

    /** @param  list<array{0:Producto,1:float}>  $lineas */
    private function documento(int $folio, Cliente $cliente, array $lineas, bool $anulada = false): DocumentoVenta
    {
        $doc = DocumentoVenta::create([
            'bsale_document_id' => $folio,
            'folio' => $folio,
            'emitido_at' => now(),
            'cliente_id' => $cliente->id,
            'cancellation_status' => $anulada ? 1 : null,
        ]);
        foreach ($lineas as $i => [$producto, $cantidad]) {
            DocumentoVentaDetalle::create([
                'documento_venta_id' => $doc->id, 'bsale_detail_id' => $i + 1,
                'producto_id' => $producto->id, 'cantidad' => $cantidad,
            ]);
        }

        return $doc;
    }

    // ─────────────────────────────────────────────────── buscar y traer

    public function test_busca_por_folio_o_por_cliente_y_solo_entre_los_vigentes(): void
    {
        $porFolio = $this->actingAs($this->conAmbos)->getJson(route('admin.carga.documento', ['q' => '456']))->assertOk()->json('documentos');
        // 4568 también empieza con 456, pero está anulada: no aparece.
        $this->assertSame(['4567'], array_column($porFolio, 'folio'));
        $this->assertSame('Aguas del Sur', $porFolio[0]['cliente']);

        $porCliente = $this->actingAs($this->conAmbos)->getJson(route('admin.carga.documento', ['q' => 'aguas']))->assertOk()->json('documentos');
        $this->assertSame(['4567'], array_column($porCliente, 'folio'));
    }

    public function test_traer_el_documento_devuelve_las_lineas_convertidas_y_lo_que_no_entro(): void
    {
        $r = $this->actingAs($this->conAmbos)
            ->getJson(route('admin.carga.documento', ['id' => $this->factura->id]))
            ->assertOk()
            ->json();

        $this->assertSame('4567', $r['documento']['folio']);
        // 200 botellones en UNIDADES: el simulador divide por las 5 de la bolsa él mismo.
        $this->assertSame([['tipo' => $this->bolsa->id, 'cantidad' => 200, 'nombre' => $this->bolsa->nombre]], $r['lineas']);
        // Y el dispensador sin «cómo viaja» se lista con su motivo, no se salta.
        $this->assertCount(1, $r['sin_bulto']);
        $this->assertSame('Dispensador LB-07B', $r['sin_bulto'][0]['nombre']);
        $this->assertSame('sin_declarar', $r['sin_bulto'][0]['motivo']);
    }

    public function test_un_documento_anulado_no_se_carga(): void
    {
        $this->actingAs($this->conAmbos)
            ->getJson(route('admin.carga.documento', ['id' => $this->anulada->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'anulado'));
    }

    // ─────────────────────────────────────────────────── el permiso doble

    /**
     * Con solo `simular carga` no se leen facturas. Es la diferencia entre una calculadora y
     * una puerta lateral: el vendedor que simula cargas no ve documentos de venta en ninguna
     * otra pantalla, y esta ruta no puede ser la primera.
     */
    public function test_con_solo_el_permiso_del_simulador_no_se_leen_facturas(): void
    {
        $this->actingAs($this->soloSimula)
            ->getJson(route('admin.carga.documento', ['q' => '456']))
            ->assertForbidden();

        $this->actingAs($this->soloSimula)
            ->getJson(route('admin.carga.documento', ['id' => $this->factura->id]))
            ->assertForbidden();
    }

    /** Y al revés: `manage despachos` sin `simular carga` tampoco entra (es una ruta del simulador). */
    public function test_con_solo_el_permiso_de_despachos_tampoco(): void
    {
        $soloDespachos = tap(User::factory()->create())->givePermissionTo('manage despachos');

        $this->actingAs($soloDespachos)
            ->getJson(route('admin.carga.documento', ['q' => '456']))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────── el aviso que sobrevive

    /**
     * EL AVISO VIAJA EN LA URL Y SE DIBUJA JUNTO A LA LISTA DE LA CARGA. Antes, el «No se
     * pudieron leer N líneas» del importador de Excel vivía solo en el modal, y la recarga del
     * cálculo se lo llevaba SIEMPRE que la importación fuera parcial — o sea, justo cuando
     * importaba. Sin la salvedad al lado, «cabe todo» sobre media factura se lee como cabe todo.
     */
    public function test_el_aviso_de_lo_que_no_entro_sobrevive_la_recarga_junto_a_la_lista(): void
    {
        $camion = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35', 'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1400, 'pasillo_cm' => 0, 'activo' => true,
        ]);

        $con = $this->actingAs($this->soloSimula)->get(route('admin.carga.index', [
            'camion_id' => $camion->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 20]],
            'origen' => 'Documento N° 4567',
            'no_cargadas' => ['Dispensador LB-07B × 3 — no tiene definido cómo viaja'],
        ]))->assertOk();

        $con->assertSee('data-aviso-carga', false);
        $con->assertSee('Traído de Documento N° 4567');
        $con->assertSee('Dispensador LB-07B × 3');
        $con->assertSee('no entró al cálculo');

        // Sin los parámetros no se dibuja nada: el aviso no es decoración permanente.
        $sin = $this->actingAs($this->soloSimula)->get(route('admin.carga.index', [
            'camion_id' => $camion->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 20]],
        ]))->assertOk();
        $sin->assertDontSee('data-aviso-carga', false);
    }

    /** El texto viene de la URL, así que se imprime escapado: es texto que escribió un usuario. */
    public function test_el_aviso_se_imprime_escapado(): void
    {
        $camion = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35', 'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1400, 'pasillo_cm' => 0, 'activo' => true,
        ]);

        $res = $this->actingAs($this->soloSimula)->get(route('admin.carga.index', [
            'camion_id' => $camion->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 20]],
            'no_cargadas' => ['<script>alert(1)</script>'],
        ]))->assertOk();

        $res->assertDontSee('<script>alert(1)</script>', false);
        $res->assertSee('&lt;script&gt;', false);
    }

    /**
     * Y VIAJA EN EL LINK COMPARTIDO. Es la misma URL firmada, así que es el mismo aviso: quien
     * recibe el plan —un cliente, un conductor— lee que es de una carga a la que le faltan
     * líneas, y no una completa. Sin esto, el link sería la única superficie donde el
     * veredicto aparece sin su salvedad, y es justo la que sale de la empresa.
     */
    public function test_el_aviso_viaja_tambien_en_el_link_compartido(): void
    {
        $camion = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35', 'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1400, 'pasillo_cm' => 0, 'activo' => true,
        ]);

        $url = URL::temporarySignedRoute('publico.plan-carga', now()->addDay(), [
            'camion_id' => $camion->id,
            'lineas' => [['tipo' => $this->bolsa->id, 'cantidad' => 20]],
            'origen' => 'Documento N° 4567',
            'no_cargadas' => ['Dispensador LB-07B × 3 — no tiene definido cómo viaja'],
        ]);

        // Sin login, como lo abre el cliente.
        $res = $this->get($url)->assertOk();

        $res->assertSee('data-aviso-carga', false);
        $res->assertSee('Traído de Documento N° 4567');
        $res->assertSee('Dispensador LB-07B × 3');
    }

    // ─────────────────────────────────────────────────── la pantalla

    public function test_el_modal_ofrece_traer_una_factura_y_ya_no_dice_que_no_puede(): void
    {
        // Con camión elegido: sin él la página no dibuja el cuerpo —y con él, el modal—.
        // Es el estado real de una importación: se trae una carga A un camión.
        $camion = CamionSimulacion::create([
            'nombre' => 'Hyundai HD35', 'largo_cm' => 430, 'ancho_cm' => 200, 'alto_cm' => 220,
            'peso_max_kg' => 1400, 'pasillo_cm' => 0, 'activo' => true,
        ]);

        $res = $this->actingAs($this->soloSimula)->get(route('admin.carga.index', ['camion_id' => $camion->id]))->assertOk();

        $res->assertSee('O traé una factura');
        $res->assertDontSee('Todavía no lee facturas');
        // Los hidden que llevan el aviso al form están en la página.
        $res->assertSee('name="origen"', false);
    }
}
