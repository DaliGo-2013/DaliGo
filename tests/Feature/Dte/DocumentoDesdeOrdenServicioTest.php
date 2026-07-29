<?php

namespace Tests\Feature\Dte;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Dte\DocumentoDesdeOrdenServicio;
use App\Services\Dte\DocumentoTributario;
use App\Services\Dte\EmisionException;
use App\Services\Dte\FormaPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados del armador (M05): una orden de Servicio Técnico real convertida en
 * documento tributario, con las reglas de Contabilidad del 28-jul-2026 aplicadas.
 *
 * Nada de esto emite: el armador devuelve un objeto. Es lo que permite el ensayo
 * en seco.
 */
class DocumentoDesdeOrdenServicioTest extends TestCase
{
    use RefreshDatabase;

    private function armador(): DocumentoDesdeOrdenServicio
    {
        return new DocumentoDesdeOrdenServicio;
    }

    /**
     * Orden cobrable (reparación, no garantía) con repuestos y mano de obra.
     */
    private function orden(array $repuestos = [], int $manoObra = 0, array $extra = []): OrdenServicio
    {
        $orden = OrdenServicio::factory()->create(array_merge([
            'facturacion' => 'reparacion',
            'mano_obra' => $manoObra,
        ], $extra));

        foreach ($repuestos as $r) {
            $orden->repuestos()->create([
                'nombre' => $r['nombre'] ?? 'Termostato',
                'sku' => $r['sku'] ?? null,
                'cantidad' => $r['cantidad'] ?? 1,
                'precio_unitario' => $r['precio'] ?? 1000,
            ]);
        }

        return $orden->fresh();
    }

    public function test_arma_una_boleta_con_una_linea_por_repuesto_y_una_de_mano_de_obra(): void
    {
        $orden = $this->orden([
            ['nombre' => 'Termostato', 'sku' => '1070154', 'precio' => 12000],
            ['nombre' => 'Llave de paso', 'sku' => '1070001', 'precio' => 8000, 'cantidad' => 2],
        ], manoObra: 30000);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA, FormaPago::EFECTIVO);

        $this->assertCount(3, $doc->lineas, 'Dos repuestos + mano de obra.');
        $this->assertSame('Termostato', $doc->lineas[0]->descripcion);
        $this->assertSame('1070154', $doc->lineas[0]->codigoProducto);
        $this->assertSame(2, $doc->lineas[1]->cantidad);
        $this->assertSame('Hora servicio técnico', $doc->lineas[2]->descripcion);
        $this->assertSame('9771001', $doc->lineas[2]->codigoProducto, 'La mano de obra lleva su SKU.');
    }

    public function test_el_total_del_documento_es_el_total_de_la_orden(): void
    {
        // 12.000 + 16.000 + 30.000 = 58.000
        $orden = $this->orden([
            ['precio' => 12000],
            ['precio' => 8000, 'cantidad' => 2],
        ], manoObra: 30000);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        // Regla 1: manda el total que paga el cliente.
        $this->assertSame(58000, (int) $orden->costo_total);
        $this->assertSame(58000, $doc->totalEfectivo());
        $this->assertSame($doc->neto() + $doc->iva(), $doc->totalEfectivo(), 'Neto + IVA = total exacto.');
    }

    public function test_el_ajuste_del_redondeo_cae_en_la_mano_de_obra(): void
    {
        // Tres repuestos de $1.000 (el caso que perdía un peso) + mano de obra.
        $orden = $this->orden([
            ['precio' => 1000], ['precio' => 1000], ['precio' => 1000],
        ], manoObra: 50000);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        // Las tres líneas de repuesto quedan en el neto "limpio"...
        $this->assertSame(840, $doc->lineas[0]->precioNetoUnitario);
        $this->assertSame(840, $doc->lineas[1]->precioNetoUnitario);
        $this->assertSame(840, $doc->lineas[2]->precioNetoUnitario);
        // ...y la suma total sigue cuadrando exacto con lo que paga el cliente.
        $this->assertSame(53000, $doc->totalEfectivo());
        $this->assertSame(53000, $doc->neto() + $doc->iva());
    }

    public function test_un_repuesto_escrito_a_mano_va_sin_codigo(): void
    {
        // Caso legítimo: el técnico escribió el repuesto en vez de elegirlo del
        // catálogo. Esa línea va como glosa libre, no se inventa un código.
        $orden = $this->orden([['nombre' => 'Manguera cortada a medida', 'sku' => null, 'precio' => 5000]]);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        $this->assertNull($doc->lineas[0]->codigoProducto);
        $this->assertSame('Manguera cortada a medida', $doc->lineas[0]->descripcion);
    }

    public function test_el_descuento_queda_dentro_de_los_precios_y_el_total_cuadra(): void
    {
        $orden = $this->orden([['precio' => 20000]], manoObra: 30000, extra: [
            'descuento_pct' => 10,
            'descuento_motivo' => array_key_first(OrdenServicio::DESCUENTO_MOTIVOS),
        ]);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        $this->assertSame(45000, (int) $orden->costo_total, '50.000 menos 10%.');
        $this->assertSame(45000, $doc->totalEfectivo());
        $this->assertSame(45000, $doc->neto() + $doc->iva());
        // El descuento queda visible en la observación del documento.
        $this->assertStringContainsString('Descuento 10%', (string) $doc->observacion);
    }

    public function test_una_orden_en_garantia_no_se_factura(): void
    {
        $orden = $this->orden([['precio' => 10000]], extra: [
            'facturacion' => 'garantia',
            'garantia_doc_fecha' => now()->subMonth()->toDateString(),
        ]);

        $this->expectException(EmisionException::class);
        $this->expectExceptionMessage('GARANTÍA');

        $this->armador()->armar($orden, DocumentoTributario::BOLETA);
    }

    public function test_una_garantia_vencida_si_se_factura(): void
    {
        // Regla del negocio que ya existía: garantía vencida = se cobra.
        $orden = $this->orden([['precio' => 10000]], extra: [
            'facturacion' => 'garantia',
            'garantia_doc_fecha' => now()->subMonths(10)->toDateString(),
        ]);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        $this->assertSame(10000, $doc->totalEfectivo());
    }

    public function test_no_se_permite_un_documento_exento(): void
    {
        $orden = $this->orden([['precio' => 10000]]);

        $this->expectException(EmisionException::class);
        $this->expectExceptionMessage('exentas');

        $this->armador()->armar($orden, DocumentoTributario::FACTURA_EXENTA);
    }

    public function test_una_orden_sin_nada_que_cobrar_no_se_factura(): void
    {
        $orden = $this->orden();

        $this->expectException(EmisionException::class);
        $this->expectExceptionMessage('total $0');

        $this->armador()->armar($orden, DocumentoTributario::BOLETA);
    }

    public function test_la_factura_toma_giro_y_direccion_de_la_ficha_del_cliente(): void
    {
        $cliente = Cliente::factory()->create([
            'rut' => '76301506-8',
            'giro' => 'Venta de agua purificada',
            'direccion' => 'Av. Siempre Viva 742',
            'comuna' => 'Talca',
        ]);
        $orden = $this->orden([['precio' => 10000]], extra: ['cliente_rut' => $cliente->rut]);

        $doc = $this->armador()->armar($orden, DocumentoTributario::FACTURA_AFECTA);

        // La orden guarda nombre y RUT; el giro y la dirección que la factura exige
        // salen de la ficha.
        $this->assertSame('Venta de agua purificada', $doc->receptorGiro);
        $this->assertSame('Av. Siempre Viva 742', $doc->receptorDireccion);
        $this->assertSame('Talca', $doc->receptorComuna);
    }

    public function test_sin_ficha_de_cliente_el_giro_queda_vacio(): void
    {
        $orden = $this->orden([['precio' => 10000]], extra: ['cliente_rut' => '11111111-1']);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        // Para una boleta no hace falta. Si fuera factura, el emisor la rechaza con
        // un mensaje claro (ver BsaleEmisorTest).
        $this->assertNull($doc->receptorGiro);
    }

    public function test_el_documento_arrastra_su_origen(): void
    {
        $sucursal = Sucursal::factory()->create(['codigo' => 'MIR']);
        $usuario = User::factory()->create();
        $orden = $this->orden([['precio' => 10000]], extra: ['sucursal_id' => $sucursal->id]);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA, FormaPago::TRANSFERENCIA, $usuario);

        $this->assertSame($orden->id, $doc->origen['orden_servicio_id']);
        $this->assertSame($sucursal->id, $doc->origen['sucursal_id']);
        $this->assertSame($usuario->id, $doc->origen['emitido_por']);
        $this->assertSame(FormaPago::TRANSFERENCIA, $doc->formaPago);
    }

    public function test_el_sales_id_se_deriva_del_codigo_de_la_orden(): void
    {
        $orden = $this->orden([['precio' => 10000]]);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        // Es la clave anti-duplicado: dos veces la misma orden = el mismo salesId.
        $this->assertSame("ST-{$orden->codigo}", $doc->salesId);
        $this->assertSame($doc->salesId, $this->armador()->armar($orden, DocumentoTributario::BOLETA)->salesId);
    }

    public function test_la_observacion_referencia_la_orden(): void
    {
        $orden = $this->orden([['precio' => 10000]], extra: ['numero_serie' => 'SN-12345']);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        $this->assertStringContainsString($orden->codigo, (string) $doc->observacion);
        $this->assertStringContainsString('SN-12345', (string) $doc->observacion);
    }

    public function test_un_repuesto_de_varias_unidades_reparte_el_neto_por_unidad(): void
    {
        // 3 unidades a $1.190 = $3.570 con IVA -> neto 3.000 -> 1.000 por unidad.
        $orden = $this->orden([['precio' => 1190, 'cantidad' => 3]]);

        $doc = $this->armador()->armar($orden, DocumentoTributario::BOLETA);

        $this->assertSame(3, $doc->lineas[0]->cantidad);
        $this->assertSame(1000, $doc->lineas[0]->precioNetoUnitario);
        $this->assertSame(3570, $doc->totalEfectivo());
    }
}
