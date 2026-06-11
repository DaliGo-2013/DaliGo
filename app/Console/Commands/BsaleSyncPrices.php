<?php

namespace App\Console\Commands;

use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\PriceListSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Barre las listas de precios de Bsale (solo lectura) y hace upsert en
 * `listas_precios` + `precios`, preservando el campo local `canal`. Detalles
 * solo de listas activas; los precios que Bsale ya no manda se eliminan.
 * Idempotente. La automatizacion (cron/webhooks) va en incrementos posteriores.
 */
class BsaleSyncPrices extends Command
{
    protected $signature = 'bsale:sync-prices';

    protected $description = 'Sincroniza las listas de precios de Bsale → listas_precios/precios (solo lectura en Bsale; preserva el canal local).';

    public function handle(BsaleClient $client): int
    {
        if (! $client->hasToken()) {
            $this->error('Falta BSALE_ACCESS_TOKEN en .env (config services.bsale.token).');

            return self::FAILURE;
        }

        $this->info('Sincronizando listas de precios desde Bsale (solo lectura en Bsale; escribe solo BD local)…');

        try {
            $stats = (new PriceListSync($client))->run();
        } catch (Throwable $e) {
            $this->error('Sync abortada: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Listas', 'Precios creados', 'Actualizados', 'Eliminados', 'Omitidos', 'Errores'],
            [[$stats['listas'], $stats['creados'], $stats['actualizados'], $stats['eliminados'], $stats['omitidos'], count($stats['errores'])]],
        );

        if ($stats['omitidos'] > 0) {
            $this->comment("  · {$stats['omitidos']} valores de variantes sin producto local (corre bsale:sync-catalog si parece mucho).");
        }

        foreach (array_slice($stats['errores'], 0, 20) as $err) {
            $this->warn("  · lista {$err['lista_id']}: {$err['error']}");
        }

        return self::SUCCESS;
    }
}
