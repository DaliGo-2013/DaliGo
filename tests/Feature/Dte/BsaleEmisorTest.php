<?php

namespace Tests\Feature\Dte;

use App\Models\Sucursal;
use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\BsaleEmisor;
use App\Services\Dte\CajaCerradaException;
use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\EmisionException;
use App\Services\Dte\EstadoSii;
use App\Services\Dte\FormaPago;
use App\Services\Dte\LineaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Candados del traductor a Bsale (M05 · B3).
 *
 * Todo contra Http::fake: ningún test sale a la red y NADA emite un documento
 * real. Lo que se prueba es NUESTRA parte —qué se manda, cómo se lee lo que
 * vuelve y qué se le dice al usuario cuando falla—, que es exactamente lo
 * verificable sin credencial.
 */
class BsaleEmisorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Estos tests prueban la TRADUCCIÓN, no el candado (eso es
        // CandadoDeEmisionTest), así que se emite en ambiente de prueba con el
        // interruptor encendido. Sin esto fallarían los 21 — que es justamente la
        // señal de que el candado está puesto donde corresponde.
        config(['dte.emision_habilitada' => true, 'dte.ambiente' => 'prueba']);
    }

    /** Respuesta típica de un documento emitido, con los campos que leemos. */
    private function documentoOk(array $extra = []): array
    {
        return array_merge([
            'id' => 4321,
            'number' => 1050,
            'totalAmount' => 53000,
            'netAmount' => 44538,
            'taxAmount' => 8462,
            'informedSii' => 0,
            'urlXml' => 'https://bsale.test/x.xml',
            'urlPdf' => 'https://bsale.test/x.pdf',
            'ted' => '<TED>…</TED>',
        ], $extra);
    }

    private function emisor(): BsaleEmisor
    {
        return new BsaleEmisor(new BsaleClient('https://api.bsale.test/v1', 'token-de-prueba'));
    }

    /** Boleta base; $extra sobrescribe propiedades públicas del DTO. */
    private function boleta(array $extra = []): DocumentoTributario
    {
        $documento = new DocumentoTributario(
            tipoDte: DocumentoTributario::BOLETA,
            salesId: 'ST-1234',
            lineas: [
                new LineaDocumento('Termostato', 1, 840, codigoProducto: '1070154'),
                new LineaDocumento('Hora servicio técnico', 1, 42018, codigoProducto: '9771001'),
            ],
            totalConIva: 51000,
        );

        foreach ($extra as $campo => $valor) {
            $documento->{$campo} = $valor;
        }

        return $documento;
    }

    /** @return array<int,array> cuerpos enviados */
    private function cuerposEnviados(): array
    {
        return collect(Http::recorded())
            ->map(fn (array $par) => $par[0]->data())
            ->all();
    }

    public function test_emite_una_boleta_y_lee_folio_estado_y_enlaces(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $r = $this->emisor()->emitir($this->boleta());

        $this->assertTrue($r->exitoso);
        $this->assertSame(1050, $r->folio);
        $this->assertSame('4321', $r->documentoExternoId);
        $this->assertSame(EstadoSii::ACEPTADO, $r->estado, 'informedSii 0 es ACEPTADO, no pendiente.');
        $this->assertSame('https://bsale.test/x.pdf', $r->urlPdf);
        $this->assertSame(53000, $r->total);
        $this->assertNotEmpty($r->crudo, 'Se guarda la respuesta cruda para diagnosticar rechazos.');
    }

    public function test_manda_el_sales_id_y_las_lineas_con_su_sku(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->emisor()->emitir($this->boleta());

        $cuerpo = $this->cuerposEnviados()[0];

        $this->assertSame('ST-1234', $cuerpo['salesId'], 'Sin salesId no hay protección anti-duplicado.');
        $this->assertSame(39, $cuerpo['codeSii'], 'Sin documentTypeId configurado se cae a codeSii.');
        $this->assertSame(1, $cuerpo['declareSii'], 'declareSii es entero, no booleano.');
        $this->assertCount(2, $cuerpo['details']);
        $this->assertSame('1070154', $cuerpo['details'][0]['code']);
        $this->assertSame(840, $cuerpo['details'][0]['netUnitValue']);
        $this->assertSame('Termostato', $cuerpo['details'][0]['comment'], 'La glosa impresa es el nombre real (Res. 36/2024).');
        $this->assertArrayNotHasKey('priceListId', $cuerpo, 'Los precios van explícitos: la lista no debe ir.');
    }

    public function test_prefiere_el_document_type_id_configurado_sobre_el_code_sii(): void
    {
        config(['dte.bsale.tipos_documento' => [39 => 77]]);
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->emisor()->emitir($this->boleta());

        $cuerpo = $this->cuerposEnviados()[0];
        $this->assertSame(77, $cuerpo['documentTypeId']);
        $this->assertArrayNotHasKey('codeSii', $cuerpo);
    }

    public function test_una_boleta_a_consumidor_final_no_manda_cliente(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->emisor()->emitir($this->boleta(['receptorRut' => DocumentoTributario::RUT_CONSUMIDOR_FINAL]));

        // Mandar el RUT genérico como cliente crearía una ficha basura en Bsale
        // por cada venta de mostrador.
        $this->assertArrayNotHasKey('client', $this->cuerposEnviados()[0]);
    }

    public function test_una_factura_sin_rut_no_se_emite(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->expectException(EmisionException::class);
        $this->expectExceptionMessage('necesita el RUT del receptor');

        $this->emisor()->emitir(new DocumentoTributario(
            tipoDte: DocumentoTributario::FACTURA_AFECTA,
            salesId: 'ST-9',
            lineas: [new LineaDocumento('Termostato', 1, 840)],
        ));

        Http::assertNothingSent();
    }

    public function test_una_factura_sin_giro_no_se_emite(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->expectException(EmisionException::class);
        $this->expectExceptionMessage('giro');

        $this->emisor()->emitir(new DocumentoTributario(
            tipoDte: DocumentoTributario::FACTURA_AFECTA,
            salesId: 'ST-9',
            lineas: [new LineaDocumento('Termostato', 1, 840)],
            receptorRut: '76301506-8',
            receptorNombre: 'Cliente SpA',
        ));
    }

    public function test_una_factura_completa_manda_el_cliente(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->emisor()->emitir(new DocumentoTributario(
            tipoDte: DocumentoTributario::FACTURA_AFECTA,
            salesId: 'ST-10',
            lineas: [new LineaDocumento('Termostato', 1, 840)],
            receptorRut: '76301506-8',
            receptorNombre: 'Cliente SpA',
            receptorGiro: 'Venta de agua purificada',
            receptorDireccion: 'Av. Siempre Viva 742',
            receptorComuna: 'Talca',
        ));

        $cliente = $this->cuerposEnviados()[0]['client'];
        $this->assertSame('76301506-8', $cliente['code']);
        $this->assertSame('Venta de agua purificada', $cliente['activity']);
        $this->assertArrayNotHasKey('city', $cliente, 'Los campos vacíos no se mandan.');
    }

    public function test_un_documento_sin_lineas_no_se_emite(): void
    {
        Http::fake();

        $this->expectException(EmisionException::class);
        $this->expectExceptionMessage('sin líneas');

        $this->emisor()->emitir(new DocumentoTributario(
            tipoDte: DocumentoTributario::BOLETA,
            salesId: 'ST-11',
            lineas: [],
        ));
    }

    public function test_emite_desde_la_sucursal_donde_se_reparo(): void
    {
        $sucursal = Sucursal::factory()->create(['codigo' => 'MIR']);
        config(['dte.bsale.oficinas' => ['MIR' => 9]]);
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->emisor()->emitir($this->boleta(['origen' => ['sucursal_id' => $sucursal->id]]));

        $this->assertSame(9, $this->cuerposEnviados()[0]['officeId']);
    }

    public function test_una_sucursal_sin_oficina_configurada_frena_la_emision(): void
    {
        $sucursal = Sucursal::factory()->create(['codigo' => 'COQ']);
        config(['dte.bsale.oficinas' => []]);
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        // Falla nombrando la clave que falta, en vez de emitir desde la oficina
        // equivocada: un documento mal atribuido se corrige con nota de crédito.
        $this->expectException(EmisionException::class);
        $this->expectExceptionMessage('dte.bsale.oficinas.COQ');

        $this->emisor()->emitir($this->boleta(['origen' => ['sucursal_id' => $sucursal->id]]));
    }

    public function test_manda_el_pago_por_el_total_que_paga_el_cliente(): void
    {
        config(['dte.bsale.medios_pago' => [FormaPago::EFECTIVO => 3]]);
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->emisor()->emitir($this->boleta(['formaPago' => FormaPago::EFECTIVO]));

        $pago = $this->cuerposEnviados()[0]['payments'][0];
        $this->assertSame(3, $pago['paymentTypeId']);
        // 51000 es el total declarado, no la suma de netos x 1,19.
        $this->assertSame(51000, $pago['amount']);
    }

    public function test_una_forma_de_pago_sin_mapear_frena_la_emision(): void
    {
        config(['dte.bsale.medios_pago' => []]);
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        // Emitir sin pago descuadraría el cierre de caja, y el descuadre lo
        // descubre alguien al final del día sin saber de dónde viene.
        $this->expectException(EmisionException::class);
        $this->expectExceptionMessage('dte.bsale.medios_pago.efectivo');

        $this->emisor()->emitir($this->boleta(['formaPago' => FormaPago::EFECTIVO]));
    }

    public function test_a_credito_no_manda_pago(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk())]);

        $this->emisor()->emitir($this->boleta(['formaPago' => FormaPago::CREDITO]));

        $this->assertArrayNotHasKey('payments', $this->cuerposEnviados()[0]);
    }

    public function test_un_documento_rechazado_por_el_sii_no_es_una_excepcion(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk(['informedSii' => 2]))]);

        $r = $this->emisor()->emitir($this->boleta());

        // El documento EXISTE: se corrige con nota de crédito, no reintentando.
        $this->assertTrue($r->exitoso);
        $this->assertTrue($r->fueRechazadoPorSii());
        $this->assertSame(1050, $r->folio);
    }

    public function test_un_documento_enviado_queda_para_reconsultar(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk(['informedSii' => 1]))]);

        $r = $this->emisor()->emitir($this->boleta());

        $this->assertSame(EstadoSii::ENVIADO, $r->estado);
        $this->assertTrue($r->requiereReconsulta());
    }

    public function test_sin_declarar_al_sii_el_estado_es_no_declarado(): void
    {
        Http::fake(['*/documents.json' => Http::response($this->documentoOk(['informedSii' => 0]))]);

        $r = $this->emisor()->emitir($this->boleta(['declararAlSii' => false]));

        $this->assertSame(EstadoSii::NO_DECLARADO, $r->estado);
        $this->assertSame(0, $this->cuerposEnviados()[0]['declareSii']);
    }

    public function test_la_caja_cerrada_se_traduce_a_su_propio_error(): void
    {
        Http::fake(['*/documents.json' => Http::response(['error' => 'closed box'], 400)]);

        try {
            $this->emisor()->emitir($this->boleta());
            $this->fail('Debía lanzar CajaCerradaException.');
        } catch (CajaCerradaException $e) {
            // El remedio no es técnico: alguien tiene que abrir la caja en Bsale.
            $this->assertStringContainsString('caja del día está cerrada', $e->getMessage());
            $this->assertStringNotContainsString('closed box', $e->getMessage());
        }
    }

    public function test_un_problema_de_folios_se_explica_en_castellano(): void
    {
        Http::fake(['*/documents.json' => Http::response(['error' => 'There is no CAF available'], 400)]);

        try {
            $this->emisor()->emitir($this->boleta());
            $this->fail('Debía lanzar EmisionException.');
        } catch (EmisionException $e) {
            $this->assertStringContainsString('folios', $e->getMessage());
            $this->assertSame(400, $e->status());
        }
    }

    public function test_un_sales_id_repetido_en_bsale_avisa_que_no_se_duplico(): void
    {
        Http::fake(['*/documents.json' => Http::response(['error' => 'salesId already used'], 400)]);

        try {
            $this->emisor()->emitir($this->boleta());
            $this->fail('Debía lanzar EmisionException.');
        } catch (EmisionException $e) {
            $this->assertStringContainsString('no se emitió', $e->getMessage());
        }
    }

    public function test_el_post_no_se_reintenta_solo(): void
    {
        Http::fake(['*/documents.json' => Http::response(['error' => 'boom'], 500)]);

        try {
            $this->emisor()->emitir($this->boleta());
        } catch (EmisionException) {
            // esperado
        }

        // UN solo intento: reintentar un POST que quizá se procesó emitiría un
        // segundo documento tributario con folio propio.
        Http::assertSentCount(1);
    }

    public function test_consultar_estado_relee_el_documento(): void
    {
        Http::fake(['*/documents/4321.json' => Http::response($this->documentoOk(['informedSii' => 0]))]);

        $r = $this->emisor()->consultarEstado('4321');

        $this->assertSame(EstadoSii::ACEPTADO, $r->estado);
        $this->assertFalse($r->requiereReconsulta());
    }

    public function test_la_nota_de_credito_viaja_con_su_propio_sales_id(): void
    {
        Http::fake(['*/returns.json' => Http::response($this->documentoOk(['id' => 9999, 'number' => 7]))]);

        $r = $this->emisor()->anularConNotaCredito('4321', 'Error de emisión', 'NC-ST-1234');

        $cuerpo = $this->cuerposEnviados()[0];
        $this->assertSame(4321, $cuerpo['documentId']);
        $this->assertSame('NC-ST-1234', $cuerpo['salesId'], 'La NC necesita su propia clave de idempotencia.');
        $this->assertSame('Error de emisión', $cuerpo['motive']);
        $this->assertSame(7, $r->folio);
    }

    public function test_folios_disponibles_avisa_cuando_se_agotan_o_vencen(): void
    {
        // OJO el * final: el patrón de Http::fake compara la URL COMPLETA, y
        // esta lleva query string (?codesii=39). Sin él, el fake no matchea y el
        // test intenta salir a la red de verdad.
        Http::fake(['*/document_types/caf.json*' => Http::response([
            'items' => [[
                'from' => 1000,
                'to' => 1040,
                'lastNumber' => 1035,
                'expirationDate' => '2026-08-01',
                'expired' => false,
            ]],
        ])]);

        $folios = $this->emisor()->foliosDisponibles(39);

        $this->assertSame(5, $folios->disponibles);
        $this->assertTrue($folios->estaPorAgotarse());
        $this->assertTrue($folios->requiereAtencion());
    }
}
