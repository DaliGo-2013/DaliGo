<?php

namespace Tests\Feature\Bsale;

use App\Models\Producto;
use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\CatalogSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class BsaleCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array> */
    private array $fakeVariants = [];

    /** @var array<int,array> */
    private array $fakeTypes = [];

    private bool $httpFaked = false;

    /** Sobre paginado al estilo Bsale. */
    private function envelope(array $items, int $count, int $limit, int $offset): array
    {
        return ['href' => 'x', 'count' => $count, 'limit' => $limit, 'offset' => $offset, 'items' => $items, 'next' => null];
    }

    /**
     * Define los datos faqueados de Bsale. Registra el closure de Http::fake una sola
     * vez (lee el estado mutable), para poder "cambiar" los datos entre syncs sin apilar
     * stubs (apilar haria que ganara el primero).
     */
    private function fakeBsale(array $variants, array $types = []): void
    {
        $this->fakeVariants = $variants;
        $this->fakeTypes = $types;

        if ($this->httpFaked) {
            return;
        }
        $this->httpFaked = true;

        Http::fake(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) ($query['offset'] ?? 0);
            $limit = (int) ($query['limit'] ?? 50);

            if (str_contains($request->url(), 'product_types.json')) {
                return Http::response($this->envelope(array_slice($this->fakeTypes, $offset, $limit), count($this->fakeTypes), $limit, $offset));
            }

            if (str_contains($request->url(), 'variants.json')) {
                return Http::response($this->envelope(array_slice($this->fakeVariants, $offset, $limit), count($this->fakeVariants), $limit, $offset));
            }

            return Http::response([], 404);
        });
    }

    private function variant(int $id, string $code, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'code' => $code,
            'barCode' => '999'.$id,
            'state' => 0,
            'description' => "var {$id}",
            'product' => ['id' => 100 + $id, 'name' => "Producto {$code}", 'product_type' => ['id' => 7]],
        ], $overrides);
    }

    private function sync(): array
    {
        return (new CatalogSync(new BsaleClient('https://api.bsale.io/v1', 'fake-token')))->run();
    }

    public function test_maps_fields_correctly(): void
    {
        $this->fakeBsale(
            [$this->variant(1, '1010001')],
            [['id' => 7, 'name' => 'Botellones', 'state' => 0]],
        );

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);
        $p = Producto::where('bsale_variant_id', 1)->firstOrFail();
        $this->assertSame('1010001', $p->sku);
        $this->assertSame('Producto 1010001', $p->nombre);
        $this->assertSame('Botellones', $p->categoria);
        $this->assertSame('9991', $p->barcode);
        $this->assertSame(7, (int) $p->bsale_product_type_id);
        $this->assertSame(101, (int) $p->bsale_product_id);
        $this->assertTrue($p->activo);
    }

    public function test_preserves_local_fields_on_resync(): void
    {
        $this->fakeBsale([$this->variant(1, 'SKU-1')], [['id' => 7, 'name' => 'Cat', 'state' => 0]]);
        $this->sync();

        Producto::where('bsale_variant_id', 1)->update([
            'peso_kg' => 0.85, 'alto_cm' => 10, 'marca' => 'DALI', 'descripcion' => 'mía',
        ]);

        // Re-sync con un nombre cambiado en Bsale.
        $this->fakeBsale(
            [$this->variant(1, 'SKU-1', ['product' => ['id' => 101, 'name' => 'Nuevo', 'product_type' => ['id' => 7]]])],
            [['id' => 7, 'name' => 'Cat', 'state' => 0]],
        );
        $stats = $this->sync();

        $p = Producto::where('bsale_variant_id', 1)->firstOrFail();
        $this->assertSame(1, $stats['actualizados']);
        $this->assertSame('Nuevo', $p->nombre);           // campo de Bsale actualizado
        $this->assertEquals(0.85, (float) $p->peso_kg);   // local preservado
        $this->assertEquals(10.0, (float) $p->alto_cm);
        $this->assertSame('DALI', $p->marca);
        $this->assertSame('mía', $p->descripcion);
        $this->assertSame(1, Producto::count());
    }

    public function test_adopts_csv_style_row(): void
    {
        // Fila estilo-CSV: sku + peso, SIN enlace a Bsale.
        $csv = Producto::factory()->create([
            'sku' => '1010001', 'bsale_variant_id' => null, 'bsale_product_id' => null,
            'peso_kg' => 2.5, 'marca' => 'DALI',
        ]);

        $this->fakeBsale([$this->variant(1, '1010001')], [['id' => 7, 'name' => 'Cat', 'state' => 0]]);
        $stats = $this->sync();

        $this->assertSame(1, $stats['adoptados']);
        $this->assertSame(1, Producto::count());            // sin duplicar
        $fresh = $csv->fresh();
        $this->assertSame(1, (int) $fresh->bsale_variant_id); // enlace lleno
        $this->assertEquals(2.5, (float) $fresh->peso_kg);    // local preservado
        $this->assertSame('Producto 1010001', $fresh->nombre); // identidad de Bsale
    }

    public function test_tracks_rename_by_variant_id(): void
    {
        $this->fakeBsale([$this->variant(1, 'OLD-CODE')]);
        $this->sync();

        $this->fakeBsale([$this->variant(1, 'NEW-CODE')]); // mismo id, nuevo code
        $stats = $this->sync();

        $this->assertSame(1, Producto::count());
        $this->assertSame('NEW-CODE', Producto::where('bsale_variant_id', 1)->value('sku'));
        $this->assertSame(1, $stats['actualizados']);
    }

    public function test_paginates_across_two_pages(): void
    {
        $variants = [];
        for ($i = 1; $i <= 75; $i++) {
            $variants[] = $this->variant($i, "SKU-{$i}");
        }
        $this->fakeBsale($variants);

        $stats = $this->sync();

        $this->assertSame(75, $stats['creados']);
        $this->assertSame(75, Producto::count());
    }

    public function test_unique_sku_collision_is_skipped_and_reported(): void
    {
        // dos variantes distintas con el mismo code
        $this->fakeBsale([$this->variant(1, 'DUP'), $this->variant(2, 'DUP')]);

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);
        $this->assertSame(1, $stats['omitidos']);
        $this->assertCount(1, $stats['errores']);
        $this->assertSame(2, $stats['errores'][0]['variant_id']);
        $this->assertSame(1, Producto::where('sku', 'DUP')->count());
    }

    public function test_no_per_row_audits(): void
    {
        $this->fakeBsale([$this->variant(1, 'A'), $this->variant(2, 'B')]);
        $this->sync();

        $this->assertSame(0, Audit::where('auditable_type', Producto::class)->count());
    }

    public function test_deactivates_linked_products_missing_from_bsale(): void
    {
        // Primera sync: dos variantes activas.
        $this->fakeBsale([$this->variant(1, 'SKU-1'), $this->variant(2, 'SKU-2')]);
        $this->sync();

        // Producto local SIN enlace a Bsale (CSV/UI): jamas debe desactivarse.
        $localOnly = Producto::factory()->create(['sku' => 'LOCAL-1', 'bsale_variant_id' => null, 'activo' => true]);

        // Bsale deja de listar la variante 2 (desactivada/eliminada alla).
        $this->fakeBsale([$this->variant(1, 'SKU-1')]);
        $stats = $this->sync();

        $this->assertSame(1, $stats['desactivados']);
        $this->assertTrue(Producto::where('bsale_variant_id', 1)->value('activo'));
        $this->assertFalse(Producto::where('bsale_variant_id', 2)->value('activo'));
        $this->assertTrue($localOnly->fresh()->activo);

        // Y si la variante 2 vuelve a Bsale, el espejo la reactiva.
        $this->fakeBsale([$this->variant(1, 'SKU-1'), $this->variant(2, 'SKU-2')]);
        $this->sync();
        $this->assertTrue(Producto::where('bsale_variant_id', 2)->value('activo'));
    }

    public function test_zero_recognized_variants_skips_deactivation(): void
    {
        // Primera sync: dos productos enlazados y activos.
        $this->fakeBsale([$this->variant(1, 'SKU-1'), $this->variant(2, 'SKU-2')]);
        $this->sync();

        // Barrido anomalo: variantes sin code (todas fallan, 0 reconocidas).
        // El guard debe saltar la desactivacion en vez de apagar todo el catalogo.
        $this->fakeBsale([
            $this->variant(8, '', ['code' => '']),
            $this->variant(9, '', ['code' => '']),
        ]);
        $stats = $this->sync();

        $this->assertSame(0, $stats['desactivados']);
        $this->assertSame(2, Producto::where('activo', true)->count());
        $this->assertTrue(
            collect($stats['errores'])->contains(fn ($e) => str_contains($e['error'], 'se omite la desactivación')),
        );
    }
}
