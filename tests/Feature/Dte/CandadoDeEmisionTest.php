<?php

namespace Tests\Feature\Dte;

use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\BsaleEmisor;
use App\Services\Dte\CandadoDeEmision;
use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\EmisionBloqueadaException;
use App\Services\Dte\LineaDocumento;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Candados del candado (M05 · B4).
 *
 * Lo que se prueba es que NO se manda nada a la red cuando el proceso no tiene
 * permiso de emitir. El riesgo que esto ataja es concreto: en Bsale la dirección
 * de la API es idéntica para prueba y producción, así que un token de producción
 * en un `.env` de desarrollo convierte cualquier prueba en una factura real.
 */
class CandadoDeEmisionTest extends TestCase
{
    private function emisor(): BsaleEmisor
    {
        return new BsaleEmisor(new BsaleClient('https://api.bsale.test/v1', 'token-de-prueba'));
    }

    private function boleta(): DocumentoTributario
    {
        return new DocumentoTributario(
            tipoDte: DocumentoTributario::BOLETA,
            salesId: 'ST-1234',
            lineas: [new LineaDocumento('Hora servicio técnico', 1, 42017, codigoProducto: '9771001')],
            totalConIva: 50000,
        );
    }

    public function test_por_defecto_la_emision_esta_deshabilitada(): void
    {
        // El default del config, sin que ningún test lo toque: apagado.
        $this->assertFalse((bool) config('dte.emision_habilitada'));
        $this->assertFalse(CandadoDeEmision::permitido());
    }

    public function test_con_la_emision_deshabilitada_no_se_manda_nada_a_la_red(): void
    {
        config(['dte.emision_habilitada' => false, 'dte.ambiente' => 'prueba']);
        Http::fake();

        try {
            $this->emisor()->emitir($this->boleta());
            $this->fail('Debía lanzar EmisionBloqueadaException.');
        } catch (EmisionBloqueadaException $e) {
            $this->assertStringContainsString('DESHABILITADA', $e->getMessage());
            $this->assertStringContainsString('No se emitió nada', $e->getMessage());
            $this->assertSame(403, $e->status());
        }

        Http::assertNothingSent();
    }

    public function test_una_credencial_de_produccion_fuera_de_produccion_no_emite(): void
    {
        // El escenario real: el token de la empresa en el .env de un notebook.
        config(['dte.emision_habilitada' => true, 'dte.ambiente' => 'produccion']);
        Http::fake();

        try {
            $this->emisor()->emitir($this->boleta());
            $this->fail('Debía lanzar EmisionBloqueadaException.');
        } catch (EmisionBloqueadaException $e) {
            $this->assertStringContainsString('PRODUCCIÓN', $e->getMessage());
            $this->assertStringContainsString('documento tributario real', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_el_ambiente_manda_incluso_con_el_interruptor_encendido(): void
    {
        // Las dos condiciones son AND: encender el interruptor no alcanza para
        // saltarse el ambiente.
        config(['dte.emision_habilitada' => true, 'dte.ambiente' => 'produccion']);

        $this->assertFalse(CandadoDeEmision::permitido());
        $this->assertStringContainsString('PRODUCCIÓN', (string) CandadoDeEmision::motivoDelBloqueo());
    }

    public function test_un_ambiente_desconocido_se_trata_como_produccion(): void
    {
        // Ante la duda, el lado seguro es el que no emite. Un typo
        // (BSALE_AMBIENTE=pruebas, en plural) no debe habilitar nada.
        config(['dte.emision_habilitada' => true, 'dte.ambiente' => 'pruebas']);

        $this->assertSame(CandadoDeEmision::AMBIENTE_PRODUCCION, CandadoDeEmision::ambiente());
        $this->assertTrue(CandadoDeEmision::esProduccion());
        $this->assertFalse(CandadoDeEmision::permitido());
    }

    public function test_en_prueba_con_el_interruptor_encendido_si_emite(): void
    {
        config(['dte.emision_habilitada' => true, 'dte.ambiente' => 'prueba']);
        Http::fake(['*/documents.json' => Http::response([
            'id' => 1, 'number' => 10, 'informedSii' => 0, 'totalAmount' => 50000,
        ])]);

        $resultado = $this->emisor()->emitir($this->boleta());

        $this->assertTrue($resultado->exitoso);
        $this->assertNull(CandadoDeEmision::motivoDelBloqueo());
        Http::assertSentCount(1);
    }

    public function test_la_nota_de_credito_tambien_esta_bajo_el_candado(): void
    {
        // Anular también EMITE un documento tributario (la NC tiene su propio folio).
        config(['dte.emision_habilitada' => false]);
        Http::fake();

        $this->expectException(EmisionBloqueadaException::class);

        $this->emisor()->anularConNotaCredito('4321', 'Error de emisión', 'NC-ST-1234');
    }

    public function test_leer_nunca_esta_bloqueado(): void
    {
        // El plan acordado es recabar los datos de la cuenta real ANTES de emitir:
        // si el candado bloqueara las lecturas, esa etapa sería imposible.
        config(['dte.emision_habilitada' => false, 'dte.ambiente' => 'produccion']);
        Http::fake(['*/documents/4321.json' => Http::response([
            'id' => 4321, 'number' => 900, 'informedSii' => 0,
        ])]);

        $resultado = $this->emisor()->consultarEstado('4321');

        $this->assertSame(900, $resultado->folio);
        Http::assertSentCount(1);
    }

    public function test_consultar_folios_tampoco_esta_bloqueado(): void
    {
        config(['dte.emision_habilitada' => false, 'dte.ambiente' => 'produccion']);
        Http::fake(['*/document_types/caf.json*' => Http::response([
            'items' => [['from' => 1, 'to' => 100, 'lastNumber' => 10]],
        ])]);

        $this->assertSame(90, $this->emisor()->foliosDisponibles(39)->disponibles);
    }
}
