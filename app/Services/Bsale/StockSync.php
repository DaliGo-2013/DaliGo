<?php

namespace App\Services\Bsale;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\Stock;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza bodegas (offices) y stock de Bsale hacia `bodegas` + `stocks`.
 *
 * Fase 1: espeja las offices (upsert por bsale_office_id). Fase 2: barre el
 * snapshot global de /stocks.json (NO es por bodega; son ~28k combinaciones
 * variante×office), matchea producto por bsale_variant_id y bodega por
 * office.id, y hace upsert por (bodega, producto).
 *
 * Borrado de stale (combinaciones que Bsale dejó de reportar): SOLO tras un
 * barrido COMPLETO y SOLO si vimos al menos una fila. Si `$vistos` queda vacío
 * (catálogo/bodegas desincronizados o respuesta anómala), NO se borra nada
 * — la lección del footgun de precios (whereNotIn([]) = 1=1 arrasa la tabla).
 * Un fallo de la API a mitad de paginación lanza excepción ANTES del delete
 * (sale del foreach), así que tampoco borra ante fallo parcial.
 */
class StockSync
{
    public function __construct(private BsaleClient $client) {}

    /**
     * @return array{bodegas:int,creados:int,actualizados:int,eliminados:int,omitidos:int,errores:array<int,array>}
     */
    public function run(): array
    {
        $stats = ['bodegas' => 0, 'creados' => 0, 'actualizados' => 0, 'eliminados' => 0, 'omitidos' => 0, 'errores' => []];

        // Fase 1: bodegas (offices). Sin audit por fila.
        $bodegaPorOffice = [];
        Bodega::withoutAuditing(function () use (&$stats, &$bodegaPorOffice) {
            foreach ($this->client->each('offices.json') as $o) {
                try {
                    $bodega = $this->upsertBodega($o);
                    $bodegaPorOffice[$bodega->bsale_office_id] = $bodega->id;
                    $stats['bodegas']++;
                } catch (Throwable $e) {
                    $stats['errores'][] = ['office_id' => $o['id'] ?? null, 'error' => $e->getMessage()];
                }
            }
        });

        // Fase 2: stock (snapshot global). Match producto por bsale_variant_id.
        $productoPorVariante = Producto::whereNotNull('bsale_variant_id')->pluck('id', 'bsale_variant_id');
        $vistos = [];          // stock.id procesados en esta corrida
        $bodegasVistas = [];   // bodega_id con >= 1 stock matcheado en esta corrida

        foreach ($this->client->each('stocks.json') as $s) {
            $officeId = isset($s['office']['id']) ? (int) $s['office']['id'] : null;
            $variantId = isset($s['variant']['id']) ? (int) $s['variant']['id'] : null;
            $bodegaId = $officeId !== null ? ($bodegaPorOffice[$officeId] ?? null) : null;
            $productoId = $variantId !== null ? ($productoPorVariante[$variantId] ?? null) : null;

            if ($bodegaId === null || $productoId === null) {
                // Office sin bodega local o variante sin producto local: se omite.
                $stats['omitidos']++;

                continue;
            }

            $stock = Stock::updateOrCreate(
                ['bodega_id' => $bodegaId, 'producto_id' => $productoId],
                [
                    'stock_real' => (float) ($s['quantity'] ?? 0),
                    'stock_reservado' => (float) ($s['quantityReserved'] ?? 0),
                    'stock_disponible' => (float) ($s['quantityAvailable'] ?? 0),
                    'bsale_stock_id' => isset($s['id']) ? (int) $s['id'] : null,
                ],
            );

            $stock->wasRecentlyCreated ? $stats['creados']++ : $stats['actualizados']++;
            $vistos[$stock->id] = true;
            $bodegasVistas[$bodegaId] = true;
        }

        // Borrado de stale ACOTADO a las bodegas que SÍ produjeron stock matcheado
        // esta corrida + GUARD anti-footgun ($vistos no vacío). Así, una bodega
        // ausente de offices.json (o cuyos productos no estén en el catálogo local)
        // NO se vacía: solo se purga lo obsoleto dentro de bodegas realmente vistas.
        if ($vistos !== []) {
            $stats['eliminados'] = Stock::whereIn('bodega_id', array_keys($bodegasVistas))
                ->whereNotIn('id', array_keys($vistos))
                ->delete();
        } elseif (Stock::exists()) {
            $stats['errores'][] = [
                'office_id' => null,
                'error' => '0 stocks mapeados pero hay stock local; se omite el borrado (catálogo/bodegas desincronizado).',
            ];
        }

        Log::info(sprintf(
            'bsale:sync-stock → %d bodegas, %d stock creados, %d actualizados, %d eliminados, %d omitidos, %d errores.',
            $stats['bodegas'], $stats['creados'], $stats['actualizados'], $stats['eliminados'], $stats['omitidos'], count($stats['errores']),
        ));

        return $stats;
    }

    private function upsertBodega(array $o): Bodega
    {
        $bsaleId = isset($o['id']) ? (int) $o['id'] : null;
        if ($bsaleId === null || $bsaleId === 0) {
            throw new \RuntimeException('Office sin id.');
        }

        $nombre = trim((string) ($o['name'] ?? ''));

        return Bodega::updateOrCreate(
            ['bsale_office_id' => $bsaleId],
            [
                'nombre' => $nombre !== '' ? mb_substr($nombre, 0, 191) : "Bodega {$bsaleId}",
                'direccion' => $this->limpiar($o['address'] ?? null),
                'comuna' => $this->limpiar($o['municipality'] ?? null),
                'ciudad' => $this->limpiar($o['city'] ?? null),
                'email' => $this->limpiar($o['email'] ?? null),
                'es_virtual' => (int) ($o['isVirtual'] ?? 0) === 1,
                'activa' => (int) ($o['state'] ?? 0) === 0,
                'bsale_default_price_list_id' => isset($o['defaultPriceList']) ? (int) $o['defaultPriceList'] : null,
            ],
        );
    }

    private function limpiar(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, 191);
    }
}
