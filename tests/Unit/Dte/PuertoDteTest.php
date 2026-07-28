<?php

namespace Tests\Unit\Dte;

use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\EstadoSii;
use App\Services\Dte\FoliosDisponibles;
use App\Services\Dte\LineaDocumento;
use App\Services\Dte\ResultadoEmision;
use App\Support\FechaNegocio;
use Tests\TestCase;

/**
 * Puerto de emisión de DTE (M05): los DTO y el mapeo de estados. Sin base de
 * datos ni HTTP, pero con la app arrancada porque FechaNegocio lee la zona del
 * negocio desde config (mismo criterio que Tests\Unit\CodigoIncidenteTest).
 */
class PuertoDteTest extends TestCase
{
    // --- EstadoSii: la trampa de la escala invertida de Bsale ---

    public function test_el_cero_de_bsale_significa_aceptado_no_pendiente(): void
    {
        // La doc de Bsale: "0 es correcto, 1 es enviado, 2 es rechazado".
        // Si alguien "arregla" esto asumiendo que 0 = pendiente, este test cae.
        $this->assertSame(EstadoSii::ACEPTADO, EstadoSii::desdeBsale(0));
        $this->assertSame(EstadoSii::ENVIADO, EstadoSii::desdeBsale(1));
        $this->assertSame(EstadoSii::RECHAZADO, EstadoSii::desdeBsale(2));
    }

    public function test_un_valor_desconocido_no_inventa_estado(): void
    {
        // Nunca se adivina un estado tributario: queda pendiente de reconsulta.
        $this->assertSame(EstadoSii::PENDIENTE, EstadoSii::desdeBsale(null));
        $this->assertSame(EstadoSii::PENDIENTE, EstadoSii::desdeBsale(99));
    }

    public function test_solo_aceptado_rechazado_y_no_declarado_son_finales(): void
    {
        $this->assertTrue(EstadoSii::esFinal(EstadoSii::ACEPTADO));
        $this->assertTrue(EstadoSii::esFinal(EstadoSii::RECHAZADO));
        $this->assertTrue(EstadoSii::esFinal(EstadoSii::NO_DECLARADO));

        // Estos dos son los que el cron debe perseguir.
        $this->assertFalse(EstadoSii::esFinal(EstadoSii::PENDIENTE));
        $this->assertFalse(EstadoSii::esFinal(EstadoSii::ENVIADO));
        $this->assertFalse(EstadoSii::esFinal(EstadoSii::ERROR));
    }

    public function test_las_variantes_de_badge_existen_en_el_design_system(): void
    {
        // x-badge solo define brand|neutral|danger y cae en brand sin avisar:
        // una variante inventada pintaría "rechazado" de color marca.
        foreach (EstadoSii::TODOS as $estado) {
            $this->assertContains(
                EstadoSii::variante($estado),
                ['brand', 'neutral', 'danger'],
                "La variante de '{$estado}' no existe en x-badge",
            );
            $this->assertNotSame($estado, EstadoSii::etiqueta($estado), "Falta etiqueta de '{$estado}'");
        }
    }

    // --- Montos ---

    public function test_la_linea_calcula_su_neto_con_descuento(): void
    {
        $linea = new LineaDocumento(
            descripcion: 'Dispensador LB-16',
            cantidad: 3,
            precioNetoUnitario: 10_000,
        );
        $this->assertSame(30_000, $linea->netoLinea());

        $conDescuento = new LineaDocumento(
            descripcion: 'Dispensador LB-16',
            cantidad: 2,
            precioNetoUnitario: 10_000,
            descuentoPct: 15,
        );
        $this->assertSame(17_000, $conDescuento->netoLinea());
    }

    public function test_el_documento_suma_el_neto_de_sus_lineas(): void
    {
        $doc = $this->documento([
            new LineaDocumento('Botellón 20 L', 30, 1_975),
            new LineaDocumento('Flete', 1, 5_000),
        ]);

        $this->assertSame(30 * 1_975 + 5_000, $doc->neto());
    }

    // --- DocumentoTributario ---

    public function test_la_fecha_por_defecto_es_el_dia_de_negocio(): void
    {
        // No now(): una venta nocturna quedaría fechada mañana (P-TZ-01).
        $this->assertSame(FechaNegocio::hoy(), $this->documento()->fechaEmisionEfectiva());
    }

    public function test_respeta_la_fecha_explicita_si_viene(): void
    {
        $doc = new DocumentoTributario(
            tipoDte: DocumentoTributario::FACTURA_AFECTA,
            salesId: 'ST-1',
            lineas: [new LineaDocumento('Servicio', 1, 1_000)],
            fechaEmision: '2026-03-04',
        );

        $this->assertSame('2026-03-04', $doc->fechaEmisionEfectiva());
    }

    public function test_reconoce_boletas_y_exentos(): void
    {
        $this->assertTrue($this->documento(tipo: DocumentoTributario::BOLETA)->esBoleta());
        $this->assertTrue($this->documento(tipo: DocumentoTributario::BOLETA_EXENTA)->esBoleta());
        $this->assertFalse($this->documento(tipo: DocumentoTributario::FACTURA_AFECTA)->esBoleta());

        $this->assertTrue($this->documento(tipo: DocumentoTributario::BOLETA_EXENTA)->esExento());
        $this->assertTrue($this->documento(tipo: DocumentoTributario::FACTURA_EXENTA)->esExento());
        $this->assertFalse($this->documento(tipo: DocumentoTributario::BOLETA)->esExento());
    }

    // --- ResultadoEmision ---

    public function test_una_falla_de_emision_no_es_un_rechazo_del_sii(): void
    {
        $falla = ResultadoEmision::fallida('Se cayó la red');

        $this->assertFalse($falla->exitoso);
        $this->assertSame(EstadoSii::ERROR, $falla->estado);
        // Clave: no se puede confundir con un rechazo del SII, porque el remedio
        // es distinto (reintentar vs. emitir nota de crédito).
        $this->assertFalse($falla->fueRechazadoPorSii());
        $this->assertFalse($falla->requiereReconsulta());
    }

    public function test_un_documento_enviado_requiere_reconsulta_y_uno_aceptado_no(): void
    {
        $enviado = new ResultadoEmision(exitoso: true, estado: EstadoSii::ENVIADO, folio: 273481);
        $this->assertTrue($enviado->requiereReconsulta());

        $aceptado = new ResultadoEmision(exitoso: true, estado: EstadoSii::ACEPTADO, folio: 273481);
        $this->assertFalse($aceptado->requiereReconsulta());
    }

    public function test_detecta_el_rechazo_del_sii(): void
    {
        $rechazado = new ResultadoEmision(exitoso: true, estado: EstadoSii::RECHAZADO);

        $this->assertTrue($rechazado->fueRechazadoPorSii());
        $this->assertFalse($rechazado->requiereReconsulta());
    }

    // --- FoliosDisponibles ---

    public function test_avisa_cuando_los_folios_se_agotan(): void
    {
        $pocos = new FoliosDisponibles(tipoDte: 33, disponibles: 12);
        $this->assertTrue($pocos->estaPorAgotarse());
        $this->assertTrue($pocos->requiereAtencion());

        $holgados = new FoliosDisponibles(tipoDte: 33, disponibles: 800);
        $this->assertFalse($holgados->estaPorAgotarse());
        $this->assertFalse($holgados->requiereAtencion());
    }

    public function test_un_caf_vencido_requiere_atencion_aunque_le_queden_folios(): void
    {
        // Res. Ex. SII 58/2017: el CAF vale 6 meses; vencido, el SII rechaza los
        // documentos que lo usen. Tener folios de sobra no salva.
        $vencido = new FoliosDisponibles(tipoDte: 33, disponibles: 900, vencido: true);

        $this->assertTrue($vencido->estaPorVencer());
        $this->assertTrue($vencido->requiereAtencion());
    }

    public function test_avisa_cuando_el_caf_esta_por_vencer(): void
    {
        $porVencer = new FoliosDisponibles(
            tipoDte: 33,
            disponibles: 900,
            venceEl: FechaNegocio::ahora()->addDays(5)->toDateString(),
        );
        $this->assertTrue($porVencer->estaPorVencer());

        $lejano = new FoliosDisponibles(
            tipoDte: 33,
            disponibles: 900,
            venceEl: FechaNegocio::ahora()->addDays(90)->toDateString(),
        );
        $this->assertFalse($lejano->estaPorVencer());
        $this->assertFalse($lejano->requiereAtencion());
    }

    // --- Helpers ---

    /** @param  list<LineaDocumento>|null  $lineas */
    private function documento(?array $lineas = null, int $tipo = DocumentoTributario::FACTURA_AFECTA): DocumentoTributario
    {
        return new DocumentoTributario(
            tipoDte: $tipo,
            salesId: 'ST-1234',
            lineas: $lineas ?? [new LineaDocumento('Servicio técnico', 1, 10_000)],
        );
    }
}
