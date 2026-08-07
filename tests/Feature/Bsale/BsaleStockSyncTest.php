<?php

namespace Tests\Feature\Bsale;

use App\Models\Bodega;
use App\Models\Notificacion;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\StockSync;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

    // ── M04-F1 · adopción de bodegas nuevas + clasificación local (P-M04-12) ──

    /**
     * Candado 2 del dictado v36 (el MÁS importante del lote): el sync horario
     * PISA los campos espejados de Bsale pero JAMÁS la capa local editada
     * desde la app — si la pisara, cada clasificación viviría ≤15 minutos.
     */
    public function test_sync_no_pisa_la_clasificacion_local_editada(): void
    {
        $this->producto(979);
        $this->fakeBsale([$this->office(4, 'MIRADOR')], [$this->stock(1, 4, 979, 10)]);
        $this->sync();

        $sucursal = Sucursal::factory()->create();
        $bodega = Bodega::where('bsale_office_id', 4)->firstOrFail();
        $bodega->update([
            'sucursal_id' => $sucursal->id,
            'proposito' => 'fisica',
            'en_operacion' => false,
            'clasificacion_confirmada' => true,
            'alias' => 'La central',
        ]);

        // Bsale renombra la office: el espejo debe reflejarlo…
        $this->fakeBsale([$this->office(4, 'MIRADOR CENTRAL')], [$this->stock(1, 4, 979, 10)]);
        $this->sync();

        $bodega->refresh();
        $this->assertSame('MIRADOR CENTRAL', $bodega->nombre, 'El espejo sigue siendo espejo.');
        // …sin tocar ni un campo local.
        $this->assertSame($sucursal->id, $bodega->sucursal_id);
        $this->assertSame('fisica', $bodega->proposito);
        $this->assertFalse($bodega->en_operacion);
        $this->assertTrue($bodega->clasificacion_confirmada);
        $this->assertSame('La central', $bodega->alias);
    }

    public function test_office_nueva_notifica_a_quienes_administran_sucursales_y_a_nadie_mas(): void
    {
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $administra = User::factory()->create();
        $administra->assignRole('admin');
        $soloMira = User::factory()->create();
        $soloMira->givePermissionTo('manage productos');

        $this->fakeBsale([$this->office(20, 'BODEGA NUEVA DEL SUR')], []);
        $stats = $this->sync();

        $this->assertSame(1, $stats['nuevas']);

        $bodega = Bodega::where('bsale_office_id', 20)->firstOrFail();
        $this->assertFalse($bodega->clasificacion_confirmada, 'Nace por clasificar (default de la migración).');
        $this->assertNull($bodega->proposito);

        $filas = Notificacion::where('evento', 'bodega.nueva')->where('canal', Notificacion::CANAL_DATABASE);
        $this->assertSame(1, (clone $filas)->where('user_id', $administra->id)->count());
        $this->assertSame(0, (clone $filas)->where('user_id', $soloMira->id)->count(),
            'Ver el inventario no es administrar la estructura: sin aviso.');
    }

    public function test_sync_dos_veces_no_duplica_el_aviso_de_bodega_nueva(): void
    {
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $administra = User::factory()->create();
        $administra->assignRole('admin');

        $this->fakeBsale([$this->office(20, 'BODEGA NUEVA DEL SUR')], []);
        $this->sync();
        $stats = $this->sync(); // la corrida siguiente del cron

        $this->assertSame(0, $stats['nuevas'], 'La 2ª corrida ACTUALIZA la bodega, no la crea.');
        $this->assertSame(1, Notificacion::where('evento', 'bodega.nueva')
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->where('user_id', $administra->id)
            ->count(), 'El aviso es UNA sola vez por bodega.');
    }

    public function test_sin_destinatarios_la_sync_no_revienta_ni_registra(): void
    {
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class); // el permiso existe, nadie lo tiene

        $this->fakeBsale([$this->office(20, 'BODEGA NUEVA DEL SUR')], []);
        $stats = $this->sync();

        $this->assertSame(1, $stats['nuevas']);
        $this->assertSame(0, Notificacion::where('evento', 'bodega.nueva')->count());
    }

    // ── M04-F2 · el sync CIERRA las bajas pendientes y vigila el stock nuevo ──

    /**
     * Arma el escenario F2: bodega espejada (office 9) con UN producto en
     * $cantidad, en baja `pendiente_traslado` con su orden (foto = 40).
     * Devuelve [orden, solicitante].
     */
    private function bajaPendiente(float $cantidadFoto = 40): array
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $solicitante = User::factory()->create();
        $solicitante->assignRole('admin');

        $producto = $this->producto(979);
        $bodega = Bodega::factory()->clasificada()->create([
            'bsale_office_id' => 9,
            'nombre' => 'SERAFIN ZAMORA',
            'estado_baja' => Bodega::BAJA_PENDIENTE_TRASLADO,
        ]);
        \App\Models\Stock::factory()->create([
            'bodega_id' => $bodega->id, 'producto_id' => $producto->id,
            'stock_real' => $cantidadFoto, 'stock_reservado' => 0, 'stock_disponible' => $cantidadFoto,
        ]);

        $orden = \App\Models\BodegaTraslado::factory()->create([
            'bodega_id' => $bodega->id,
            'solicitante_id' => $solicitante->id,
            'solicitante_nombre' => $solicitante->name,
        ]);
        $orden->items()->create([
            'producto_id' => $producto->id, 'nombre' => 'Botellón 20L', 'sku' => 'BOT-20',
            'cantidad' => $cantidadFoto,
        ]);

        return [$orden, $solicitante];
    }

    /** Candado 3 (dictado v38): el cierre automático, simulado 2× (MUTADO). */
    public function test_sync_con_stock_cero_completa_la_baja_sola_y_avisa_una_vez(): void
    {
        Queue::fake();
        [$orden, $solicitante] = $this->bajaPendiente(40);

        // Bsale confirma el traslado: el snapshot trae la combinación EN CERO.
        $this->fakeBsale([$this->office(9, 'SERAFIN ZAMORA')], [$this->stock(1, 9, 979, 0)]);
        $stats = $this->sync();

        $this->assertSame(1, $stats['bajas_completadas']);
        $orden->refresh();
        $this->assertSame(\App\Models\BodegaTraslado::COMPLETADO, $orden->estado);
        $this->assertNotNull($orden->completado_at);
        $bodega = $orden->bodega->fresh();
        $this->assertSame(Bodega::BAJA_DADA_DE_BAJA, $bodega->estado_baja);
        $this->assertFalse($bodega->en_operacion);
        $this->assertSame(1, Notificacion::where('evento', 'bodega.baja_completada')
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->where('user_id', $solicitante->id)->count());

        // La corrida siguiente del cron: nada que hacer, nada que repetir.
        $stats = $this->sync();
        $this->assertSame(0, $stats['bajas_completadas'], 'La orden completada no se vuelve a mirar (idempotencia por estado).');
        $this->assertSame(1, Notificacion::where('evento', 'bodega.baja_completada')->where('canal', Notificacion::CANAL_DATABASE)->count());
    }

    /** Candado 4 (dictado v38): stock nuevo avisa UNA vez y NO revive (MUTADO). */
    public function test_stock_nuevo_en_bodega_en_baja_avisa_una_vez_y_no_la_revive(): void
    {
        Queue::fake();
        [$orden, $solicitante] = $this->bajaPendiente(40);

        // Llegó MÁS stock que la foto (40 → 50): alguien siguió recibiendo ahí.
        $this->fakeBsale([$this->office(9, 'SERAFIN ZAMORA')], [$this->stock(1, 9, 979, 50)]);
        $stats = $this->sync();

        $this->assertSame(1, $stats['avisos_stock_baja']);
        $orden->refresh();
        $this->assertSame(\App\Models\BodegaTraslado::PENDIENTE, $orden->estado, 'La orden sigue pendiente.');
        $this->assertNotNull($orden->aviso_stock_nuevo_at);
        $this->assertSame(Bodega::BAJA_PENDIENTE_TRASLADO, $orden->bodega->fresh()->estado_baja,
            'La bodega NO revive sola: sigue en baja.');
        $this->assertSame(1, Notificacion::where('evento', 'bodega.stock_en_baja')
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->where('user_id', $solicitante->id)->count());

        // El cron vuelve a correr con el mismo stock: sin segundo aviso.
        $stats = $this->sync();
        $this->assertSame(0, $stats['avisos_stock_baja'], 'El aviso es UNA sola vez por orden.');
        $this->assertSame(1, Notificacion::where('evento', 'bodega.stock_en_baja')->where('canal', Notificacion::CANAL_DATABASE)->count());
    }

    /** Drenar NO es stock nuevo: bajar de la foto no dispara el aviso. */
    public function test_drenar_el_stock_no_dispara_el_aviso_de_stock_nuevo(): void
    {
        Queue::fake();
        [$orden] = $this->bajaPendiente(40);

        // El traslado va a medias: 40 → 15 (por DEBAJO de la foto).
        $this->fakeBsale([$this->office(9, 'SERAFIN ZAMORA')], [$this->stock(1, 9, 979, 15)]);
        $stats = $this->sync();

        $this->assertSame(0, $stats['avisos_stock_baja']);
        $this->assertSame(0, $stats['bajas_completadas']);
        $this->assertNull($orden->fresh()->aviso_stock_nuevo_at);
        $this->assertSame(0, Notificacion::where('evento', 'bodega.stock_en_baja')->count());
    }

    /** Solicitante eliminado → el aviso cae a quienes administran sucursales. */
    public function test_sin_solicitante_el_aviso_del_cierre_cae_a_manage_sucursales(): void
    {
        Queue::fake();
        [$orden, $solicitante] = $this->bajaPendiente(40);
        $otroAdmin = User::factory()->create();
        $otroAdmin->assignRole('admin');
        $solicitante->delete(); // nullOnDelete deja la orden sin usuario

        $this->fakeBsale([$this->office(9, 'SERAFIN ZAMORA')], [$this->stock(1, 9, 979, 0)]);
        $stats = $this->sync();

        $this->assertSame(1, $stats['bajas_completadas']);
        $this->assertSame(1, Notificacion::where('evento', 'bodega.baja_completada')
            ->where('canal', Notificacion::CANAL_DATABASE)
            ->where('user_id', $otroAdmin->id)->count());
    }
}
