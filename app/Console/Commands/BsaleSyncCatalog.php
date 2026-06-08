<?php

namespace App\Console\Commands;

use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\CatalogSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Barre el catalogo de Bsale (solo lectura) y hace upsert en `productos`,
 * preservando los campos locales (peso/dimensiones, marca, atributos, descripcion).
 * Idempotente. La automatizacion (cron/webhooks) va en incrementos posteriores.
 */
class BsaleSyncCatalog extends Command
{
    protected $signature = 'bsale:sync-catalog';

    protected $description = 'Sincroniza el catálogo de Bsale → productos (solo lectura en Bsale; preserva campos locales).';

    public function handle(BsaleClient $client): int
    {
        if (! $client->hasToken()) {
            $this->error('Falta BSALE_ACCESS_TOKEN en .env (config services.bsale.token).');

            return self::FAILURE;
        }

        $this->info('Sincronizando catálogo desde Bsale (solo lectura en Bsale; escribe solo BD local)…');

        try {
            $stats = (new CatalogSync($client))->run();
        } catch (Throwable $e) {
            $this->error('Sync abortada: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Creados', 'Actualizados', 'Adoptados', 'Omitidos', 'Errores'],
            [[$stats['creados'], $stats['actualizados'], $stats['adoptados'], $stats['omitidos'], count($stats['errores'])]],
        );

        foreach (array_slice($stats['errores'], 0, 20) as $err) {
            $this->warn("  · variante {$err['variant_id']} / sku {$err['sku']}: {$err['error']}");
        }

        return self::SUCCESS;
    }
}
