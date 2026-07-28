<?php

namespace Tests\Feature\Dte;

use App\Models\DteEmitido;
use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\EmisionDte;
use App\Services\Dte\EmisionException;
use App\Services\Dte\EmisorDte;
use App\Services\Dte\EstadoSii;
use App\Services\Dte\FoliosDisponibles;
use App\Services\Dte\LineaDocumento;
use App\Services\Dte\ResultadoEmision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados del registro de emisión (M05 · B3): que un documento no se pueda
 * emitir dos veces.
 *
 * Es lo que el informe a Gerencia (§9) promete textualmente: "la base de datos
 * impide físicamente crear dos para el mismo origen; un doble clic no puede
 * duplicar". Estos tests son la prueba de esa afirmación.
 *
 * El emisor va faqueado con un doble que CUENTA sus llamadas: lo que se prueba
 * es cuántas veces se llega a emitir, no qué se manda (eso es BsaleEmisorTest).
 */
class EmisionDteTest extends TestCase
{
    use RefreshDatabase;

    private function documento(string $salesId = 'ST-1234'): DocumentoTributario
    {
        return new DocumentoTributario(
            tipoDte: DocumentoTributario::BOLETA,
            salesId: $salesId,
            lineas: [new LineaDocumento('Hora servicio técnico', 1, 42017, codigoProducto: '9771001')],
            receptorNombre: 'Cliente de mostrador',
            totalConIva: 50000,
        );
    }

    /** Emisor de prueba: cuenta llamadas y se le puede pedir que falle. */
    private function emisorFalso(?\Throwable $error = null): EmisorDte
    {
        return new class($error) implements EmisorDte
        {
            public int $llamadas = 0;

            public function __construct(private ?\Throwable $error) {}

            public function nombre(): string
            {
                return 'bsale';
            }

            public function emitir(DocumentoTributario $documento): ResultadoEmision
            {
                $this->llamadas++;

                if ($this->error) {
                    throw $this->error;
                }

                return new ResultadoEmision(
                    exitoso: true,
                    estado: EstadoSii::ACEPTADO,
                    folio: 1050,
                    documentoExternoId: '4321',
                    urlXml: 'https://bsale.test/x.xml',
                    urlPdf: 'https://bsale.test/x.pdf',
                    neto: 42017,
                    iva: 7983,
                    total: 50000,
                );
            }

            public function consultarEstado(string $documentoExternoId): ResultadoEmision
            {
                $this->llamadas++;

                return new ResultadoEmision(
                    exitoso: true,
                    estado: EstadoSii::ACEPTADO,
                    folio: 1050,
                    documentoExternoId: $documentoExternoId,
                );
            }

            public function anularConNotaCredito(string $documentoExternoId, string $motivo, string $salesId): ResultadoEmision
            {
                return new ResultadoEmision(exitoso: true, estado: EstadoSii::ACEPTADO);
            }

            public function foliosDisponibles(int $tipoDte): FoliosDisponibles
            {
                return new FoliosDisponibles(tipoDte: $tipoDte, disponibles: 100);
            }
        };
    }

    public function test_emitir_deja_el_documento_registrado(): void
    {
        $registro = (new EmisionDte($this->emisorFalso()))->emitir($this->documento());

        $this->assertSame(1050, $registro->folio);
        $this->assertSame('4321', $registro->documento_externo_id);
        $this->assertSame(EstadoSii::ACEPTADO, $registro->estado_sii);
        $this->assertSame('bsale', $registro->emisor);
        $this->assertSame(50000, $registro->total);
        $this->assertNotNull($registro->emitido_at);
        $this->assertDatabaseCount('dte_emitidos', 1);
    }

    public function test_emitir_dos_veces_el_mismo_origen_no_duplica(): void
    {
        $emisor = $this->emisorFalso();
        $servicio = new EmisionDte($emisor);

        $primero = $servicio->emitir($this->documento());
        $segundo = $servicio->emitir($this->documento());

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, $emisor->llamadas, 'El segundo intento NO debe llegar al emisor.');
        $this->assertDatabaseCount('dte_emitidos', 1);
    }

    public function test_la_fila_se_reserva_antes_de_llamar_al_emisor(): void
    {
        // Si la reserva fuera posterior a la emisión, un fallo dejaría cero
        // rastro de que se intentó.
        $emisor = $this->emisorFalso(new EmisionException('Bsale se cayó'));

        try {
            (new EmisionDte($emisor))->emitir($this->documento());
            $this->fail('Debía propagar la excepción.');
        } catch (EmisionException) {
            // esperado
        }

        $registro = DteEmitido::firstOrFail();
        $this->assertSame(EstadoSii::ERROR, $registro->estado_sii);
        $this->assertSame('Bsale se cayó', $registro->mensaje_sii);
        $this->assertNull($registro->folio, 'No hay folio: el documento no existe en el emisor.');
    }

    public function test_un_intento_fallido_se_puede_reintentar_con_el_mismo_sales_id(): void
    {
        // Falla la primera vez...
        try {
            (new EmisionDte($this->emisorFalso(new EmisionException('timeout'))))->emitir($this->documento());
        } catch (EmisionException) {
            // esperado
        }

        // ...y el reintento SÍ debe emitir, porque el documento no llegó a existir.
        $emisor = $this->emisorFalso();
        $registro = (new EmisionDte($emisor))->emitir($this->documento());

        $this->assertSame(1, $emisor->llamadas);
        $this->assertSame(EstadoSii::ACEPTADO, $registro->estado_sii);
        // Reusa la fila reservada en el intento fallido, no crea otra.
        $this->assertDatabaseCount('dte_emitidos', 1);
    }

    public function test_un_documento_ya_aceptado_no_se_reintenta_ni_por_error(): void
    {
        $emisor = $this->emisorFalso();
        (new EmisionDte($emisor))->emitir($this->documento());

        // Otro servicio, otro emisor: igual no vuelve a emitir.
        $otro = $this->emisorFalso();
        (new EmisionDte($otro))->emitir($this->documento());

        $this->assertSame(0, $otro->llamadas);
    }

    public function test_origenes_distintos_emiten_documentos_distintos(): void
    {
        $emisor = $this->emisorFalso();
        $servicio = new EmisionDte($emisor);

        $servicio->emitir($this->documento('ST-1'));
        $servicio->emitir($this->documento('ST-2'));

        $this->assertSame(2, $emisor->llamadas);
        $this->assertDatabaseCount('dte_emitidos', 2);
    }

    public function test_guarda_los_montos_que_informa_el_emisor(): void
    {
        // Si Bsale calculó distinto, el que vale para el SII es el suyo: guardar
        // el nuestro esconderia el descuadre en vez de mostrarlo.
        $registro = (new EmisionDte($this->emisorFalso()))->emitir($this->documento());

        $this->assertSame(42017, $registro->neto);
        $this->assertSame(7983, $registro->iva);
        $this->assertSame(50000, $registro->neto + $registro->iva);
    }

    public function test_reconsultar_actualiza_el_estado(): void
    {
        $registro = DteEmitido::create([
            'tipo_dte' => DocumentoTributario::BOLETA,
            'sales_id' => 'ST-77',
            'documento_externo_id' => '4321',
            'estado_sii' => EstadoSii::ENVIADO,
        ]);

        $emisor = $this->emisorFalso();
        $actualizado = (new EmisionDte($emisor))->reconsultar($registro);

        $this->assertSame(EstadoSii::ACEPTADO, $actualizado->estado_sii);
        $this->assertSame(1, $emisor->llamadas);
    }

    public function test_reconsultar_sin_id_externo_no_llama_al_emisor(): void
    {
        $registro = DteEmitido::create([
            'tipo_dte' => DocumentoTributario::BOLETA,
            'sales_id' => 'ST-78',
            'estado_sii' => EstadoSii::ERROR,
        ]);

        $emisor = $this->emisorFalso();
        (new EmisionDte($emisor))->reconsultar($registro);

        $this->assertSame(0, $emisor->llamadas, 'Sin id externo no hay nada que consultar.');
    }

    public function test_el_puerto_resuelve_a_bsale_desde_el_contenedor(): void
    {
        // El binding de AppServiceProvider: la app pide el puerto, recibe Bsale.
        $this->assertInstanceOf(\App\Services\Bsale\BsaleEmisor::class, app(EmisorDte::class));
        $this->assertSame('bsale', app(EmisorDte::class)->nombre());
    }

    public function test_un_emisor_desconocido_en_config_revienta(): void
    {
        config(['dte.emisor' => 'inventado']);

        $this->expectException(\InvalidArgumentException::class);

        app(EmisorDte::class);
    }
}
