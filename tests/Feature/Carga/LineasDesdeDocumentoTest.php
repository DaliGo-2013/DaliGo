<?php

namespace Tests\Feature\Carga;

use App\Models\Cliente;
use App\Models\DocumentoVenta;
use App\Models\DocumentoVentaDetalle;
use App\Models\Producto;
use App\Models\TipoBulto;
use App\Services\Carga\LineasDesdeDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DE UNA FACTURA A LAS LÍNEAS DEL SIMULADOR (jefe de logística, 10-09-2026).
 *
 * Lo que fija este archivo no es la conversión feliz —eso es una división— sino las tres
 * reglas de HONESTIDAD del veredicto: lo que no tiene bulto se LISTA con su motivo (nunca
 * se salta), las cantidades se redondean HACIA ARRIBA, y dos productos del mismo bulto son
 * UNA línea (el motor acomoda bultos, no códigos). Si alguna se pierde, el simulador dice
 * «cabe todo» sobre una carga a la que le falta parte del pedido — con cara de verificado.
 */
class LineasDesdeDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private TipoBulto $bolsa;

    private TipoBulto $caja;

    private TipoBulto $inactivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bolsa = TipoBulto::create([
            'nombre' => 'Bolsa 5× botellón 20 L (vacío)', 'categoria' => 'botellones',
            'largo_cm' => 130, 'ancho_cm' => 26, 'alto_cm' => 51, 'peso_kg' => 5,
            'unidades' => 5, 'apilable_max' => 6, 'soporta_peso_encima' => true, 'activo' => true,
        ]);
        $this->caja = TipoBulto::create([
            'nombre' => 'Caja de tapas', 'categoria' => 'cajas',
            'largo_cm' => 46, 'ancho_cm' => 37, 'alto_cm' => 42, 'peso_kg' => 10,
            'unidades' => 1, 'apilable_max' => 6, 'soporta_peso_encima' => true, 'activo' => true,
        ]);
        $this->inactivo = TipoBulto::create([
            'nombre' => 'Caja vieja', 'categoria' => 'cajas',
            'largo_cm' => 40, 'ancho_cm' => 30, 'alto_cm' => 30, 'peso_kg' => 4,
            'unidades' => 1, 'apilable_max' => 4, 'soporta_peso_encima' => true, 'activo' => false,
        ]);
    }

    private function producto(string $sku, string $nombre, ?TipoBulto $bulto): Producto
    {
        return Producto::create(['sku' => $sku, 'nombre' => $nombre, 'activo' => true, 'tipo_bulto_id' => $bulto?->id]);
    }

    /** @param  list<array{0:?Producto,1:float,2?:string}>  $lineas  [producto, cantidad, descripción si no hay producto] */
    private function documento(array $lineas): DocumentoVenta
    {
        $doc = DocumentoVenta::create([
            'bsale_document_id' => random_int(1000, 999999),
            'folio' => 4567,
            'emitido_at' => now(),
            'cliente_id' => Cliente::create(['razon_social' => 'Aguas del Sur'])->id,
        ]);

        foreach ($lineas as $i => [$producto, $cantidad]) {
            DocumentoVentaDetalle::create([
                'documento_venta_id' => $doc->id,
                'bsale_detail_id' => $i + 1,
                'producto_id' => $producto?->id,
                'descripcion' => $lineas[$i][2] ?? $producto?->nombre,
                'cantidad' => $cantidad,
            ]);
        }

        return $doc;
    }

    public function test_convierte_cada_producto_a_su_bulto_en_unidades_y_redondea_hacia_arriba(): void
    {
        $botellon = $this->producto('BOT20', 'Botellón 20 L', $this->bolsa);
        $tapas = $this->producto('TAPA', 'Tapa 20 L', $this->caja);

        // 2,5 cajas no existen: se cargan 3. Hacia abajo prometería espacio de menos.
        $r = (new LineasDesdeDocumento)->convertir($this->documento([[$botellon, 200], [$tapas, 2.5]]));

        $this->assertSame([
            ['tipo' => $this->bolsa->id, 'cantidad' => 200, 'nombre' => $this->bolsa->nombre],
            ['tipo' => $this->caja->id, 'cantidad' => 3, 'nombre' => $this->caja->nombre],
        ], $r['lineas']);
        $this->assertSame([], $r['sin_bulto']);
        $this->assertSame([], $r['fuera_de_tope']);
    }

    /** Dos SKU que viajan en la misma bolsa son UNA línea: el motor acomoda bultos, no códigos. */
    public function test_dos_productos_del_mismo_bulto_son_una_sola_linea(): void
    {
        $b20 = $this->producto('BOT20', 'Botellón 20 L', $this->bolsa);
        $b10 = $this->producto('BOT10', 'Botellón 10 L', $this->bolsa);

        $r = (new LineasDesdeDocumento)->convertir($this->documento([[$b20, 100], [$b10, 50]]));

        $this->assertCount(1, $r['lineas']);
        $this->assertSame(150, $r['lineas'][0]['cantidad']);
    }

    /**
     * LO QUE NO TIENE BULTO SE LISTA, CON SU MOTIVO, y no entra a las líneas. Tres casos
     * distintos que se resuelven distinto: sin producto en el catálogo, producto sin «cómo
     * viaja» declarado, y producto cuyo bulto está desactivado (mandarlo al motor armaría un
     * plan con algo que nadie puede elegir en pantalla).
     */
    public function test_lo_que_no_tiene_bulto_se_lista_con_su_motivo_y_no_entra_al_calculo(): void
    {
        $ok = $this->producto('BOT20', 'Botellón 20 L', $this->bolsa);
        $sinDeclarar = $this->producto('DISP', 'Dispensador LB-07B', null);
        $conInactivo = $this->producto('VIEJO', 'Repuesto viejo', $this->inactivo);

        $r = (new LineasDesdeDocumento)->convertir($this->documento([
            [$ok, 10],
            [$sinDeclarar, 3],
            [$conInactivo, 2],
            [null, 1, 'Flete a domicilio'],
        ]));

        $this->assertCount(1, $r['lineas'], 'Solo la línea con bulto vigente entra al cálculo.');

        $motivos = collect($r['sin_bulto'])->pluck('motivo', 'nombre')->all();
        $this->assertSame([
            'Dispensador LB-07B' => LineasDesdeDocumento::MOTIVO_SIN_DECLARAR,
            'Repuesto viejo' => LineasDesdeDocumento::MOTIVO_BULTO_INACTIVO,
            'Flete a domicilio' => LineasDesdeDocumento::MOTIVO_SIN_PRODUCTO,
        ], $motivos);

        // Cada motivo tiene una frase que dice qué hacer: un código pelado no le sirve a
        // quien está mirando la pantalla.
        foreach ($motivos as $motivo) {
            $this->assertArrayHasKey($motivo, LineasDesdeDocumento::etiquetasDeMotivo());
        }
    }

    /** Más de ocho bultos distintos: los de más se devuelven con nombre, no se caen en silencio. */
    public function test_mas_de_ocho_bultos_distintos_van_a_fuera_de_tope(): void
    {
        $lineas = [];
        for ($i = 1; $i <= 10; $i++) {
            $bulto = TipoBulto::create([
                'nombre' => "Bulto {$i}", 'categoria' => 'cajas',
                'largo_cm' => 40, 'ancho_cm' => 30, 'alto_cm' => 30, 'peso_kg' => 4,
                'unidades' => 1, 'apilable_max' => 4, 'soporta_peso_encima' => true, 'activo' => true,
            ]);
            $lineas[] = [$this->producto("SKU{$i}", "Producto {$i}", $bulto), $i];
        }

        $r = (new LineasDesdeDocumento)->convertir($this->documento($lineas));

        $this->assertCount(LineasDesdeDocumento::TOPE_LINEAS, $r['lineas']);
        $this->assertSame([
            ['nombre' => 'Bulto 9', 'cantidad' => 9],
            ['nombre' => 'Bulto 10', 'cantidad' => 10],
        ], $r['fuera_de_tope']);
    }

    /** Una línea con cantidad cero no es una línea: ni entra al cálculo ni se avisa. */
    public function test_una_cantidad_cero_no_es_una_linea(): void
    {
        $p = $this->producto('BOT20', 'Botellón 20 L', $this->bolsa);

        $r = (new LineasDesdeDocumento)->convertir($this->documento([[$p, 0]]));

        $this->assertSame([], $r['lineas']);
        $this->assertSame([], $r['sin_bulto']);
    }
}
