<?php

namespace Tests\Feature\Bsale;

use App\Models\ListaPrecio;
use App\Models\Precio;
use App\Models\Producto;
use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\PriceListSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class BsalePreciosSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array> */
    private array $fakeListas = [];

    /** @var array<int,array<int,array>> details por bsale_price_list_id */
    private array $fakeDetails = [];

    private bool $httpFaked = false;

    /** Sobre paginado al estilo Bsale. */
    private function envelope(array $items, int $count, int $limit, int $offset): array
    {
        return ['href' => 'x', 'count' => $count, 'limit' => $limit, 'offset' => $offset, 'items' => $items, 'next' => null];
    }

    /**
     * Define listas y detalles faqueados. Registra el closure de Http::fake una
     * sola vez (lee el estado mutable) para poder "cambiar" datos entre syncs.
     */
    private function fakeBsale(array $listas, array $details = []): void
    {
        $this->fakeListas = $listas;
        $this->fakeDetails = $details;

        if ($this->httpFaked) {
            return;
        }
        $this->httpFaked = true;

        Http::fake(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $offset = (int) ($query['offset'] ?? 0);
            $limit = (int) ($query['limit'] ?? 50);

            // OJO: details ANTES que price_lists.json (ambas URLs contienen "price_lists").
            if (preg_match('#price_lists/(\d+)/details\.json#', $request->url(), $m)) {
                $items = $this->fakeDetails[(int) $m[1]] ?? [];

                return Http::response($this->envelope(array_slice($items, $offset, $limit), count($items), $limit, $offset));
            }

            if (str_contains($request->url(), 'price_lists.json')) {
                return Http::response($this->envelope(array_slice($this->fakeListas, $offset, $limit), count($this->fakeListas), $limit, $offset));
            }

            return Http::response([], 404);
        });
    }

    /**
     * Lista con el shape REAL de /price_lists.json (verificado contra la API:
     * los ids llegan como STRING y coin es {href,id} sin codigo de moneda).
     */
    private function bsaleLista(int $id, string $name, int $state = 0): array
    {
        return [
            'href' => "https://api.bsale.io/v1/price_lists/{$id}.json",
            'id' => (string) $id,
            'name' => $name,
            'description' => null,
            'state' => $state,
            'base' => null,
            'coin' => ['href' => 'https://api.bsale.io/v1/coins/1.json', 'id' => '1'],
            'details' => ['href' => "https://api.bsale.io/v1/price_lists/{$id}/details.json"],
        ];
    }

    /** Detail con el shape REAL (variantValue float largo; variant.id string). */
    private function bsaleDetail(int $detailId, int $variantId, float $neto, float $conIva): array
    {
        return [
            'href' => "https://api.bsale.io/v1/price_lists/1/details/{$detailId}.json",
            'id' => $detailId,
            'variantValue' => $neto,
            'variantValueWithTaxes' => $conIva,
            'variant' => ['href' => "https://api.bsale.io/v1/variants/{$variantId}.json", 'id' => (string) $variantId],
        ];
    }

    private function producto(int $variantId): Producto
    {
        return Producto::factory()->create(['bsale_variant_id' => $variantId]);
    }

    private function sync(): array
    {
        return (new PriceListSync(new BsaleClient('https://api.bsale.io/v1', 'fake-token')))->run();
    }

    public function test_maps_listas_and_precios_correctly(): void
    {
        $producto = $this->producto(979);
        $this->fakeBsale(
            [$this->bsaleLista(15, 'COQUIMBO-1')],
            [15 => [$this->bsaleDetail(45802, 979, 0.840336134453782, 1.0)]],
        );

        $stats = $this->sync();

        $this->assertSame(1, $stats['listas']);
        $this->assertSame(1, $stats['creados']);

        $lista = ListaPrecio::where('bsale_price_list_id', 15)->firstOrFail();
        $this->assertSame('COQUIMBO-1', $lista->nombre);
        $this->assertSame(ListaPrecio::COIN_CLP, $lista->bsale_coin_id);
        $this->assertTrue($lista->activa);
        $this->assertNull($lista->canal);

        $precio = Precio::where('lista_precio_id', $lista->id)->where('producto_id', $producto->id)->firstOrFail();
        $this->assertSame('0.8403', $precio->precio_neto);   // redondeado a 4 decimales
        $this->assertSame('1.0000', $precio->precio_con_iva);
        $this->assertSame(45802, $precio->bsale_detail_id);
    }

    public function test_resync_updates_without_duplicating(): void
    {
        $producto = $this->producto(979);
        $this->fakeBsale(
            [$this->bsaleLista(15, 'COQUIMBO-1')],
            [15 => [$this->bsaleDetail(45802, 979, 1000.0, 1190.0)]],
        );
        $this->sync();

        // Bsale cambia el valor (mismo detail).
        $this->fakeBsale(
            [$this->bsaleLista(15, 'COQUIMBO-1')],
            [15 => [$this->bsaleDetail(45802, 979, 2000.0, 2380.0)]],
        );
        $stats = $this->sync();

        $this->assertSame(1, $stats['actualizados']);
        $this->assertSame(0, $stats['creados']);
        $this->assertSame(1, Precio::count());
        $this->assertSame('2380.0000', Precio::firstOrFail()->precio_con_iva);
    }

    public function test_preserves_local_canal_on_resync(): void
    {
        $this->fakeBsale([$this->bsaleLista(15, 'COQUIMBO-1')]);
        $this->sync();

        ListaPrecio::where('bsale_price_list_id', 15)->update(['canal' => 'mayorista']);

        $this->sync();

        $this->assertSame('mayorista', ListaPrecio::where('bsale_price_list_id', 15)->value('canal'));
    }

    public function test_unknown_variant_is_skipped(): void
    {
        // Detail de una variante SIN producto local: se omite, no se inventa producto.
        $this->fakeBsale(
            [$this->bsaleLista(15, 'COQUIMBO-1')],
            [15 => [$this->bsaleDetail(45802, 999999, 1000.0, 1190.0)]],
        );

        $stats = $this->sync();

        $this->assertSame(1, $stats['omitidos']);
        $this->assertSame(0, Precio::count());
        $this->assertSame(0, Producto::count());
    }

    public function test_inactive_lists_mirror_header_but_skip_details(): void
    {
        $this->producto(979);
        $this->fakeBsale(
            [$this->bsaleLista(15, 'AFTAJUNIO21', state: 1)],
            [15 => [$this->bsaleDetail(45802, 979, 1000.0, 1190.0)]],
        );

        $stats = $this->sync();

        $lista = ListaPrecio::where('bsale_price_list_id', 15)->firstOrFail();
        $this->assertFalse($lista->activa);          // cabecera espejada
        $this->assertSame(0, Precio::count());       // sin valores: promo muerta
        $this->assertSame(0, $stats['creados']);
    }

    public function test_deletes_stale_prices(): void
    {
        $p1 = $this->producto(979);
        $p2 = $this->producto(980);
        $this->fakeBsale(
            [$this->bsaleLista(15, 'COQUIMBO-1')],
            [15 => [
                $this->bsaleDetail(45802, 979, 1000.0, 1190.0),
                $this->bsaleDetail(45803, 980, 2000.0, 2380.0),
            ]],
        );
        $this->sync();
        $this->assertSame(2, Precio::count());

        // Bsale quita el producto 980 de la lista: el espejo debe borrarlo
        // (un precio obsoleto induce a cotizar mal).
        $this->fakeBsale(
            [$this->bsaleLista(15, 'COQUIMBO-1')],
            [15 => [$this->bsaleDetail(45802, 979, 1000.0, 1190.0)]],
        );
        $stats = $this->sync();

        $this->assertSame(1, $stats['eliminados']);
        $this->assertSame(1, Precio::count());
        $this->assertSame($p1->id, Precio::firstOrFail()->producto_id);
        $this->assertNotSame($p2->id, Precio::firstOrFail()->producto_id);
    }

    public function test_paginates_details_across_two_pages(): void
    {
        $details = [];
        for ($i = 1; $i <= 60; $i++) {
            $this->producto(1000 + $i);
            $details[] = $this->bsaleDetail(50000 + $i, 1000 + $i, 1000.0, 1190.0);
        }
        $this->fakeBsale([$this->bsaleLista(15, 'GRANDE')], [15 => $details]);

        $stats = $this->sync();

        $this->assertSame(60, $stats['creados']); // 50 + 10: dos paginas
        $this->assertSame(60, Precio::count());
    }

    public function test_no_per_row_audits(): void
    {
        $this->producto(979);
        $this->fakeBsale(
            [$this->bsaleLista(15, 'COQUIMBO-1')],
            [15 => [$this->bsaleDetail(45802, 979, 1000.0, 1190.0)]],
        );

        $this->sync();

        $this->assertSame(0, Audit::where('auditable_type', ListaPrecio::class)->count());
    }
}
