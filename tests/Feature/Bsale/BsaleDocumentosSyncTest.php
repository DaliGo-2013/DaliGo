<?php

namespace Tests\Feature\Bsale;

use App\Models\Bodega;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\DocumentoVenta;
use App\Models\Producto;
use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\DocumentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * DESPACHOS-v1 · P-DSP-01. Los fakes replican el shape REAL de producción
 * (P-DSP-00, docs/qa/INFRA/2026-07-14--INFRA--p-dsp-00-shape-documents.md):
 * estados INT, fechas epoch, details como sobre anidado {items}, client
 * ausente en boletas, línea de detalle sin description.
 */
class BsaleDocumentosSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array> */
    private array $fakeDocs = [];

    /** @var array<int,array<int,array>> details por documento para el endpoint dedicado */
    private array $fakeDetallesPorDoc = [];

    private bool $httpFaked = false;

    private function envelope(array $items, int $count, int $limit, int $offset): array
    {
        return ['href' => 'x', 'count' => $count, 'limit' => $limit, 'offset' => $offset, 'items' => $items, 'next' => null];
    }

    private function fakeBsale(array $docs): void
    {
        $this->fakeDocs = $docs;

        if ($this->httpFaked) {
            return;
        }
        $this->httpFaked = true;

        Http::fake(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) ($query['offset'] ?? 0);
            $limit = (int) ($query['limit'] ?? 50);

            // Endpoint dedicado de detalles (plan B para documentos largos).
            if (preg_match('#documents/(\d+)/details\.json#', $request->url(), $m)) {
                $items = $this->fakeDetallesPorDoc[(int) $m[1]] ?? [];
                if ($items === false) {
                    return Http::response('boom', 500); // simula endpoint caído
                }

                return Http::response($this->envelope(array_slice($items, $offset, $limit), count($items), $limit, $offset));
            }

            if (str_contains($request->url(), 'documents.json')) {
                // HONRA emissiondaterange como la API real: un doc fuera de la
                // ventana NO vuelve (así los tests de ventana/watermark muerden).
                $docs = $this->fakeDocs;
                if (preg_match('/\[(\d+),(\d+)\]/', (string) ($query['emissiondaterange'] ?? ''), $r)) {
                    $docs = array_values(array_filter($docs, function (array $d) use ($r) {
                        $e = $d['emissionDate'] ?? null;
                        if (! is_numeric($e)) {
                            return true; // epoch malformado: se entrega igual (el sync debe tolerarlo)
                        }

                        return (int) $e >= (int) $r[1] && (int) $e <= (int) $r[2];
                    }));
                }

                return Http::response($this->envelope(array_slice($docs, $offset, $limit), count($docs), $limit, $offset));
            }

            return Http::response([], 404);
        });
    }

    /** Documento con el shape real (redactado) de P-DSP-00. */
    private function bsaleDoc(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'emissionDate' => now()->subHours(3)->timestamp, // dentro de toda ventana default
            'number' => 1000 + $id,
            'totalAmount' => 119000,
            'netAmount' => 100000,
            'taxAmount' => 19000,
            'state' => 0,
            'commercialState' => 0,
            'cancellationStatus' => 0,
            'cancellationDate' => null,
            'informedSii' => 1,
            'urlPdf' => "https://dte.example/pdf/{$id}",
            'urlPublicView' => "https://dte.example/view/{$id}",
            'token' => "tok{$id}",
            'document_type' => ['href' => 'x', 'id' => 8],
            'office' => ['href' => 'x', 'id' => 801, 'name' => 'Central'],
            // client: ausente por defecto (boleta) — los tests lo agregan
            'details' => ['href' => 'x', 'count' => 0, 'limit' => 25, 'offset' => 0, 'items' => []],
        ], $overrides);
    }

    /** Línea con el shape real: SIN description; variant como nodo. */
    private function bsaleDetalle(int $id, array $variant = [], array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'lineNumber' => 1,
            'quantity' => 3.0,
            'netUnitValue' => 5000,
            'netUnitValueRaw' => 5000.5,
            'totalAmount' => 17857,
            'netDiscount' => 0,
            'totalDiscount' => 500,
            'variant' => $variant === [] ? ['href' => 'x', 'id' => 701] : $variant,
            'note' => '',
        ], $overrides);
    }

    private function sobreDetalles(array $items, ?int $count = null): array
    {
        return ['href' => 'x', 'count' => $count ?? count($items), 'limit' => 25, 'offset' => 0, 'items' => $items];
    }

    private function sync(): array
    {
        return (new DocumentSync(new BsaleClient('https://api.bsale.io/v1', 'token-test')))->run();
    }

    public function test_crea_documento_con_detalles_y_matchea_cliente_bodega_producto(): void
    {
        $cliente = Cliente::create(['razon_social' => 'Cliente SpA', 'bsale_client_id' => 501]);
        $bodega = Bodega::create(['nombre' => 'Central', 'es_virtual' => false, 'activa' => true, 'bsale_office_id' => 801]);
        $producto = Producto::create(['sku' => 'BOT-20', 'nombre' => 'Botellón 20L', 'activo' => true, 'bsale_variant_id' => 701]);

        $epoch = now()->subDay()->timestamp;
        $this->fakeBsale([
            $this->bsaleDoc(11, [
                'emissionDate' => $epoch,
                'client' => ['href' => 'x', 'id' => 501],
                'details' => $this->sobreDetalles([$this->bsaleDetalle(9001)]),
            ]),
        ]);

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);
        $this->assertSame(1, $stats['detalles']);

        $doc = DocumentoVenta::firstOrFail();
        $this->assertSame(11, (int) $doc->bsale_document_id);
        $this->assertSame(1011, (int) $doc->folio);
        $this->assertSame(8, (int) $doc->bsale_document_type_id);
        $this->assertSame($epoch, $doc->emitido_at->timestamp); // epoch→datetime
        $this->assertSame(0, $doc->state);                          // INT (hallazgo #1)
        $this->assertSame(0, $doc->commercial_state);
        $this->assertSame(0, $doc->cancellation_status);
        $this->assertSame(1, (int) $doc->informed_sii);
        $this->assertSame('tok11', $doc->token);
        $this->assertSame($cliente->id, $doc->cliente_id);
        $this->assertSame($bodega->id, $doc->bodega_id);
        $this->assertEquals(100000, (float) $doc->neto);
        $this->assertEquals(19000, (float) $doc->iva);
        $this->assertEquals(119000, (float) $doc->total);

        $det = $doc->detalles()->firstOrFail();
        $this->assertSame($producto->id, $det->producto_id);
        $this->assertSame('Botellón 20L', $det->descripcion);        // desde el producto espejado
        $this->assertEquals(3.0, (float) $det->cantidad);
        $this->assertEquals(5000.5, (float) $det->precio_neto);      // netUnitValueRaw, no el int
        $this->assertEquals(500, (float) $det->descuento);
    }

    public function test_segundo_run_actualiza_sin_duplicar_y_captura_anulacion(): void
    {
        $this->fakeBsale([
            $this->bsaleDoc(11, ['details' => $this->sobreDetalles([$this->bsaleDetalle(9001)])]),
        ]);
        $this->sync();

        // El doc se anula en Bsale entre corridas.
        $this->fakeBsale([
            $this->bsaleDoc(11, [
                'commercialState' => 2,
                'cancellationStatus' => 1,
                'cancellationDate' => 1752566400,
                'details' => $this->sobreDetalles([$this->bsaleDetalle(9001)]),
            ]),
        ]);
        $stats = $this->sync();

        $this->assertSame(0, $stats['creados']);
        $this->assertSame(1, $stats['actualizados']);
        $this->assertSame(1, DocumentoVenta::count());

        $doc = DocumentoVenta::firstOrFail();
        $this->assertSame(1, $doc->cancellation_status);
        $this->assertSame(1752566400, $doc->cancellation_at->timestamp);
        $this->assertTrue($doc->estaAnulado());
        $this->assertSame(1, $doc->detalles()->count()); // sin duplicar detalle
    }

    public function test_tolera_documento_sin_nodo_client(): void
    {
        // Hallazgo #2: las boletas pueden venir sin client pese al expand.
        $this->fakeBsale([$this->bsaleDoc(12)]);

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);
        $this->assertSame([], $stats['errores']);
        $this->assertNull(DocumentoVenta::firstOrFail()->cliente_id);
    }

    public function test_ventana_default_es_ultimos_7_dias(): void
    {
        $this->fakeBsale([]);
        $this->sync();

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'documents.json')) {
                return false;
            }
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            if (! preg_match('/\[(\d+),(\d+)\]/', (string) ($query['emissiondaterange'] ?? ''), $m)) {
                return false;
            }
            $desde = (int) $m[1];
            $hasta = (int) $m[2];

            return abs($desde - now()->subDays(DocumentSync::DIAS_DEFAULT)->timestamp) < 120
                && abs($hasta - now()->timestamp) < 120;
        });
    }

    public function test_ventana_respeta_el_piso_configurado(): void
    {
        Configuracion::create([
            'clave' => DocumentSync::CONFIG_DESDE,
            'valor' => '2026-07-01',
            'tipo' => Configuracion::TIPO_STRING,
            'grupo' => 'despachos',
            'descripcion' => 'test',
        ]);

        $this->fakeBsale([]);
        $this->sync();

        $esperado = Carbon::parse('2026-07-01')->startOfDay()->timestamp;

        Http::assertSent(function (Request $request) use ($esperado) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), 'documents.json')
                && str_contains((string) ($query['emissiondaterange'] ?? ''), "[{$esperado},");
        });
    }

    public function test_ventana_avanza_desde_el_ultimo_documento_con_resolape(): void
    {
        DocumentoVenta::create([
            'bsale_document_id' => 99,
            'emitido_at' => now()->subDays(2),
        ]);

        $this->fakeBsale([]);
        $this->sync();

        $esperado = now()->subDays(2)->subDays(DocumentSync::DIAS_RESOLAPE)->timestamp;

        Http::assertSent(function (Request $request) use ($esperado) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            if (! preg_match('/\[(\d+),/', (string) ($query['emissiondaterange'] ?? ''), $m)) {
                return false;
            }

            return str_contains($request->url(), 'documents.json')
                && abs(((int) $m[1]) - $esperado) < 120;
        });
    }

    public function test_descripcion_cae_al_nodo_variant_y_luego_a_null(): void
    {
        $this->fakeBsale([
            $this->bsaleDoc(13, ['details' => $this->sobreDetalles([
                // variant con description utilizable
                $this->bsaleDetalle(9101, ['href' => 'x', 'id' => 777, 'description' => 'Desde variant', 'code' => 'VAR-1']),
                // variant pelado {href,id}: sin producto ni texto → null
                $this->bsaleDetalle(9102, ['href' => 'x', 'id' => 778]),
            ])]),
        ]);

        $this->sync();

        $doc = DocumentoVenta::firstOrFail();
        $this->assertSame('Desde variant', $doc->detalles()->where('bsale_detail_id', 9101)->firstOrFail()->descripcion);
        $this->assertNull($doc->detalles()->where('bsale_detail_id', 9102)->firstOrFail()->descripcion);
    }

    public function test_documento_largo_pagina_los_detalles_por_el_endpoint_dedicado(): void
    {
        // El sobre declara 3 líneas pero solo trae 1 → plan B: documents/{id}/details.json
        $this->fakeDetallesPorDoc[14] = [
            $this->bsaleDetalle(9201),
            $this->bsaleDetalle(9202),
            $this->bsaleDetalle(9203),
        ];

        $this->fakeBsale([
            $this->bsaleDoc(14, ['details' => $this->sobreDetalles([$this->bsaleDetalle(9201)], count: 3)]),
        ]);

        $this->sync();

        $this->assertSame(3, DocumentoVenta::firstOrFail()->detalles()->count());
    }

    public function test_lineas_sin_id_no_borran_los_detalles_existentes(): void
    {
        $this->fakeBsale([
            $this->bsaleDoc(15, ['details' => $this->sobreDetalles([
                $this->bsaleDetalle(9301),
                $this->bsaleDetalle(9302),
            ])]),
        ]);
        $this->sync();
        $this->assertSame(2, DocumentoVenta::firstOrFail()->detalles()->count());

        // Segunda corrida: líneas presentes pero SIN id (guard whereNotIn-vacío
        // de la bitácora [2026-06-12]) → no debe barrer el detalle local.
        $sinId = $this->bsaleDetalle(0);
        unset($sinId['id']);
        $this->fakeBsale([
            $this->bsaleDoc(15, ['details' => $this->sobreDetalles([$sinId])]),
        ]);
        $this->sync();

        $this->assertSame(2, DocumentoVenta::firstOrFail()->detalles()->count());
    }

    public function test_linea_eliminada_en_bsale_se_borra_del_espejo(): void
    {
        $this->fakeBsale([
            $this->bsaleDoc(16, ['details' => $this->sobreDetalles([
                $this->bsaleDetalle(9401),
                $this->bsaleDetalle(9402),
            ])]),
        ]);
        $this->sync();

        $this->fakeBsale([
            $this->bsaleDoc(16, ['details' => $this->sobreDetalles([$this->bsaleDetalle(9401)])]),
        ]);
        $this->sync();

        $doc = DocumentoVenta::firstOrFail();
        $this->assertSame(1, $doc->detalles()->count());
        $this->assertSame(9401, (int) $doc->detalles()->firstOrFail()->bsale_detail_id);
    }

    public function test_clamp_del_piso_gana_con_espejo_poblado(): void
    {
        // Rama piso-vs-avance (review P-DSP-01, hallazgo #9): con filas cuya
        // ventana natural retrocedería más atrás del piso, el piso gana.
        DocumentoVenta::create(['bsale_document_id' => 98, 'emitido_at' => now()->subDays(3)]);
        $piso = now()->subDay()->format('Y-m-d');
        Configuracion::create([
            'clave' => DocumentSync::CONFIG_DESDE,
            'valor' => $piso,
            'tipo' => Configuracion::TIPO_STRING,
            'grupo' => 'despachos',
            'descripcion' => 'test',
        ]);

        $this->fakeBsale([]);
        $this->sync();

        $esperado = Carbon::parse($piso)->startOfDay()->timestamp;

        Http::assertSent(function (Request $request) use ($esperado) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), 'documents.json')
                && str_contains((string) ($query['emissiondaterange'] ?? ''), "[{$esperado},");
        });
    }

    public function test_documento_malo_no_aborta_la_corrida_y_queda_en_omitidos(): void
    {
        $sinId = $this->bsaleDoc(0);
        unset($sinId['id']);

        $this->fakeBsale([$sinId, $this->bsaleDoc(21)]);

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);   // el doc bueno entró igual
        $this->assertSame(1, $stats['omitidos']);
        $this->assertNull($stats['errores'][0]['document_id']);
        $this->assertStringContainsString('sin id', $stats['errores'][0]['error']);
        $this->assertSame(1, DocumentoVenta::count());
    }

    public function test_epoch_invalido_deja_las_fechas_null(): void
    {
        $this->fakeBsale([
            $this->bsaleDoc(22, ['emissionDate' => 'basura', 'cancellationDate' => 0]),
        ]);

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);
        $doc = DocumentoVenta::firstOrFail();
        $this->assertNull($doc->emitido_at);       // no 1970-01-01
        $this->assertNull($doc->cancellation_at);
    }

    public function test_respuesta_sin_sobre_details_no_borra_los_detalles_espejados(): void
    {
        $this->fakeBsale([
            $this->bsaleDoc(23, ['details' => $this->sobreDetalles([
                $this->bsaleDetalle(9501),
                $this->bsaleDetalle(9502),
            ])]),
        ]);
        $this->sync();

        // Segunda corrida: el expand no trajo details → tolerar, no barrer.
        $this->fakeBsale([$this->bsaleDoc(23, ['details' => null])]);
        $this->sync();

        $this->assertSame(2, DocumentoVenta::firstOrFail()->detalles()->count());
    }

    public function test_watermark_reintenta_un_documento_fallido_en_la_corrida_siguiente(): void
    {
        // Repro del hallazgo #1 del review: doc "largo" (5 días atrás) cuyo
        // endpoint dedicado de detalles cae con 500 → NO debe perderse.
        $epochViejo = now()->subDays(5)->timestamp;
        $this->fakeDetallesPorDoc[31] = false; // 500 en documents/31/details.json

        $this->fakeBsale([
            $this->bsaleDoc(31, [
                'emissionDate' => $epochViejo,
                'details' => $this->sobreDetalles([$this->bsaleDetalle(9601)], count: 3),
            ]),
            $this->bsaleDoc(32), // doc sano de hoy: empuja el max local
        ]);

        $stats = $this->sync();
        $this->assertSame(1, $stats['omitidos']);
        // Transaccional: el doc fallido NO queda espejado a medias (sin header huérfano).
        $this->assertSame(1, DocumentoVenta::count());

        // El endpoint sana; la ventana (anclada al watermark retenido en el
        // fallido, no al max local) debe re-barrer e incorporar el doc completo.
        $this->fakeDetallesPorDoc[31] = [
            $this->bsaleDetalle(9601),
            $this->bsaleDetalle(9602),
            $this->bsaleDetalle(9603),
        ];

        $stats = $this->sync();

        $this->assertSame([], $stats['errores']);
        $recuperado = DocumentoVenta::where('bsale_document_id', 31)->first();
        $this->assertNotNull($recuperado, 'El doc fallido debe recuperarse en la corrida siguiente.');
        $this->assertSame(3, $recuperado->detalles()->count());
    }

    public function test_piso_antiguo_avanza_por_tramos_sin_backfill_gigante(): void
    {
        // Hallazgo #4 del review: piso viejo + espejo vacío NO puede pedir
        // toda la historia (~676k docs) de un tirón: tramos de 30 días.
        $piso = now()->subDays(60)->format('Y-m-d');
        Configuracion::create([
            'clave' => DocumentSync::CONFIG_DESDE,
            'valor' => $piso,
            'tipo' => Configuracion::TIPO_STRING,
            'grupo' => 'despachos',
            'descripcion' => 'test',
        ]);

        $this->fakeBsale([$this->bsaleDoc(41)]); // emitido hoy: FUERA del primer tramo

        $this->sync();

        $desdeEsperado = Carbon::parse($piso)->startOfDay();
        $hastaEsperado = $desdeEsperado->copy()->addDays(DocumentSync::DIAS_VENTANA_MAX)->timestamp;

        Http::assertSent(function (Request $request) use ($desdeEsperado, $hastaEsperado) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), 'documents.json')
                && str_contains((string) ($query['emissiondaterange'] ?? ''), "[{$desdeEsperado->timestamp},{$hastaEsperado}]");
        });
        $this->assertSame(0, DocumentoVenta::count()); // el doc de hoy no entra al tramo

        // Segunda corrida: el watermark avanzó el tramo; tras suficientes
        // corridas la ventana alcanza el presente (aquí: tramo 2 de 30 días).
        $this->sync();

        Http::assertSent(function (Request $request) use ($hastaEsperado) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            if (! preg_match('/\[(\d+),/', (string) ($query['emissiondaterange'] ?? ''), $m)) {
                return false;
            }

            // desde del tramo 2 = fin del tramo 1 - resolape
            return str_contains($request->url(), 'documents.json')
                && abs(((int) $m[1]) - ($hastaEsperado - DocumentSync::DIAS_RESOLAPE * 86400)) < 120;
        });
    }

    public function test_piso_malformado_no_mata_la_sync_y_usa_el_default(): void
    {
        // Hallazgo #7 del review: un valor DD/MM/YYYY tipeado por un humano no
        // puede tumbar la sync horaria — se ignora con warning y aplica el default.
        Configuracion::create([
            'clave' => DocumentSync::CONFIG_DESDE,
            'valor' => '14/07/2026',
            'tipo' => Configuracion::TIPO_STRING,
            'grupo' => 'despachos',
            'descripcion' => 'test',
        ]);

        $this->fakeBsale([]);
        $stats = $this->sync(); // no debe lanzar

        $this->assertSame([], $stats['errores']);

        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            if (! preg_match('/\[(\d+),/', (string) ($query['emissiondaterange'] ?? ''), $m)) {
                return false;
            }

            return str_contains($request->url(), 'documents.json')
                && abs(((int) $m[1]) - now()->subDays(DocumentSync::DIAS_DEFAULT)->timestamp) < 120;
        });
    }
}
