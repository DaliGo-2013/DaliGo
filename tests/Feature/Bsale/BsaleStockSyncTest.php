<?php

namespace Tests\Feature\Bsale;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\Stock;
use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\StockSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BsaleStockSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array> */
    private array $fakeOffices = [];

    /** @var array<int,array> */
    private array $fakeStocks = [];

    private bool $httpFaked = false;

    /** @var array<string,int> URL-substring => HTTP status para forzar fallos */
    private array $failUrlAtOffset = [];

    private function envelope(array $items, int $count, int $limit, int $offset): array
    {
        return ['href' => 'x', 'count' => $count, 'limit' => $limit, 'offset' => $offset, 'items' => $items, 'next' => null];
    }

    private function fakeBsale(array $offices, array $stocks): void
    {
        $this->fakeOffices = $offices;
        $this->fakeStocks = $stocks;

        if ($this->httpFaked) {
            return;
        }
        $this->httpFaked = true;

        Http::fake(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) ($query['offset'] ?? 0);
            $limit = (int) ($query['limit'] ?? 50);

            if (str_contains($request->url(), 'stocks.json')) {
                if (isset($this->failUrlAtOffset['stocks']) && $offset >= $this->failUrlAtOffset['stocks']) {
                    return Http::response('boom', 500);
                }

                return Http::response($this->envelope(array_slice($this->fakeStocks, $offset, $limit), count($this->fakeStocks), $limit, $offset));
            }

            if (str_contains($request->url(), 'offices.json')) {
                return Http::response($this->envelope(array_slice($this->fakeOffices, $offset, $limit), count($this->fakeOffices), $limit, $offset));
            }

            return Http::response([], 404);
        });
    }

    private function office(int $id, string $name, int $state = 0): array
    {
        return [
            'href' => "https://api.bsale.io/v1/offices/{$id}.json",
            'id' => $id, 'name' => $name, 'description' => '', 'address' => 'El Mirador 150',
            'isVirtual' => 0, 'municipality' => 'Cerrillos', 'city' => 'Santiago', 'email' => '',
            'state' => $state, 'defaultPriceList' => 3,
        ];
    }

    private function stock(int $id, int $officeId, int $variantId, float $qty, float $reserved = 0): array
    {
        return [
            'href' => "https://api.bsale.io/v1/stocks/{$id}.json",
            'id' => $id,
            'quantity' => $qty,
            'quantityReserved' => $reserved,
            'quantityAvailable' => $qty - $reserved,
            'variant' => ['href' => "https://api.bsale.io/v1/variants/{$variantId}.json", 'id' => $variantId],
            'office' => ['href' => "https://api.bsale.io/v1/offices/{$officeId}.json", 'id' => $officeId],
        ];
    }

    private function producto(int $variantId): Producto
    {
        return Producto::factory()->create(['bsale_variant_id' => $variantId]);
    }

    private function sync(): array
    {
        return (new StockSync(new BsaleClient('https://api.bsale.io/v1', 'fake-token')))->run();
    }

    public function test_maps_bodegas_and_stock_correctly(): void
    {
        $producto = $this->producto(979);
        $this->fakeBsale(
            [$this->office(4, 'MIRADOR')],
            [$this->stock(4059, 4, 979, 33, 5)],
        );

        $stats = $this->sync();

        $this->assertSame(1, $stats['bodegas']);
        $this->assertSame(1, $stats['creados']);

        $bodega = Bodega::where('bsale_office_id', 4)->firstOrFail();
        $this->assertSame('MIRADOR', $bodega->nombre);
        $this->assertTrue($bodega->activa);

        $stock = Stock::where('bodega_id', $bodega->id)->where('producto_id', $producto->id)->firstOrFail();
        $this->assertSame('33.0000', $stock->stock_real);
        $this->assertSame('5.0000', $stock->stock_reservado);
        $this->assertSame('28.0000', $stock->stock_disponible);
        $this->assertSame(4059, $stock->bsale_stock_id);
    }

    public function test_resync_updates_without_duplicating(): void
    {
        $this->producto(979);
        $this->fakeBsale([$this->office(4, 'MIRADOR')], [$this->stock(4059, 4, 979, 33)]);
        $this->sync();

        $this->fakeBsale([$this->office(4, 'MIRADOR')], [$this->stock(4059, 4, 979, 50)]);
        $stats = $this->sync();

        $this->assertSame(0, $stats['creados']);
        $this->assertSame(1, $stats['actualizados']);
        $this->assertSame(0, $stats['eliminados']);
        $this->assertSame(1, Stock::count());
        $this->assertSame('50.0000', Stock::firstOrFail()->stock_real);
    }

    public function test_unknown_variant_or_office_is_skipped(): void
    {
        $this->producto(979);
        $this->fakeBsale(
            [$this->office(4, 'MIRADOR')],
            [
                $this->stock(1, 4, 979, 10),        // ok
                $this->stock(2, 4, 999999, 10),     // variante sin producto local
                $this->stock(3, 888, 979, 10),      // office sin bodega local
            ],
        );

        $stats = $this->sync();

        $this->assertSame(1, $stats['creados']);
        $this->assertSame(2, $stats['omitidos']);
        $this->assertSame(1, Stock::count());
    }

    public function test_deletes_stale_stock(): void
    {
        $p1 = $this->producto(979);
        $p2 = $this->producto(980);
        $this->fakeBsale([$this->office(4, 'MIRADOR')], [
            $this->stock(1, 4, 979, 10),
            $this->stock(2, 4, 980, 20),
        ]);
        $this->sync();
        $this->assertSame(2, Stock::count());

        // Bsale deja de reportar el producto 980 en esa bodega: el espejo lo borra.
        $this->fakeBsale([$this->office(4, 'MIRADOR')], [$this->stock(1, 4, 979, 10)]);
        $stats = $this->sync();

        $this->assertSame(1, $stats['eliminados']);
        $this->assertSame(1, Stock::count());
        $this->assertSame($p1->id, Stock::firstOrFail()->producto_id);
    }

    /**
     * EL GUARD: si el catálogo está desincronizado (0 stocks mapean a productos
     * locales) NO se debe borrar el stock existente — el footgun de precios.
     */
    public function test_zero_mapped_stocks_does_not_wipe_existing(): void
    {
        $producto = $this->producto(979);
        $this->fakeBsale([$this->office(4, 'MIRADOR')], [$this->stock(1, 4, 979, 10)]);
        $this->sync();
        $this->assertSame(1, Stock::count());

        // Ahora Bsale devuelve stock SOLO de variantes desconocidas (catálogo desenlazado).
        $this->fakeBsale([$this->office(4, 'MIRADOR')], [$this->stock(9, 4, 999999, 10)]);
        $stats = $this->sync();

        $this->assertSame(0, $stats['eliminados']);
        $this->assertSame(1, $stats['omitidos']);
        $this->assertSame(1, Stock::count(), 'El stock existente NO debe borrarse cuando 0 stocks mapean.');
        $this->assertNotEmpty($stats['errores']);
    }

    /** Fallo de API a mitad del barrido: NO debe ejecutarse el borrado de stale. */
    public function test_api_failure_mid_sync_does_not_delete(): void
    {
        $this->producto(979);
        $this->producto(980);
        // Carga inicial sana: 2 stocks.
        $this->fakeBsale([$this->office(4, 'MIRADOR')], [
            $this->stock(1, 4, 979, 10),
            $this->stock(2, 4, 980, 20),
        ]);
        $this->sync();
        $this->assertSame(2, Stock::count());

        // Segundo run: stocks.json falla en la 2da página → excepción antes del delete.
        $this->failUrlAtOffset = ['stocks' => 50];
        $bigStocks = [];
        for ($i = 0; $i < 60; $i++) {
            $bigStocks[] = $this->stock(100 + $i, 4, 979, 1);
        }
        $this->fakeBsale([$this->office(4, 'MIRADOR')], $bigStocks);

        $abortó = false;
        try {
            $this->sync();
        } catch (\Throwable $e) {
            $abortó = true;
        }

        $this->assertTrue($abortó, 'El fallo de API debe propagar (el comando lo reporta como FAILURE).');
        $this->assertSame(2, Stock::count(), 'Un fallo a mitad NO debe borrar el stock existente.');
    }

    /**
     * El borrado de stale es por bodega vista: si una office desaparece de
     * offices.json, su stock NO se vacía (solo se purga lo obsoleto dentro de
     * bodegas que sí produjeron stock matcheado esta corrida).
     */
    public function test_delete_is_scoped_to_synced_bodegas(): void
    {
        $p1 = $this->producto(979);
        $p2 = $this->producto(980);
        $this->fakeBsale(
            [$this->office(4, 'MIRADOR'), $this->office(5, 'ABATE')],
            [$this->stock(1, 4, 979, 10), $this->stock(2, 5, 980, 20)],
        );
        $this->sync();
        $this->assertSame(2, Stock::count());

        // ABATE (office 5) desaparece de offices.json y de stocks.json; MIRADOR sigue.
        $this->fakeBsale([$this->office(4, 'MIRADOR')], [$this->stock(1, 4, 979, 10)]);
        $stats = $this->sync();

        $this->assertSame(0, $stats['eliminados']);
        $this->assertSame(2, Stock::count(), 'El stock de una bodega ausente de offices.json no debe vaciarse.');
    }

    public function test_inactive_office_is_mirrored_as_inactiva(): void
    {
        $this->producto(979);
        $this->fakeBsale([$this->office(8, 'CONCEPCIÓN', state: 1)], [$this->stock(1, 8, 979, 10)]);

        $this->sync();

        $this->assertFalse(Bodega::where('bsale_office_id', 8)->firstOrFail()->activa);
        $this->assertSame(1, Stock::count()); // el stock se espeja igual
    }
}
