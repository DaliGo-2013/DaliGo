<?php

namespace App\Console\Commands;

use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\StockSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Barre bodegas y stock de Bsale (solo lectura) y hace upsert en `bodegas` +
 * `stocks`. Idempotente; el borrado de stale tiene guard anti-vaciado.
 */
class BsaleSyncStock extends Command
{
    protected $signature = 'bsale:sync-stock';

    protected $description = 'Sincroniza bodegas y stock de Bsale → bodegas/stocks (solo lectura en Bsale).';

    public function handle(BsaleClient $client): int
    {
        if (! $client->hasToken()) {
            $this->error('Falta BSALE_ACCESS_TOKEN en .env (config services.bsale.token).');

            return self::FAILURE;
        }

        $this->info('Sincronizando bodegas y stock desde Bsale (solo lectura en Bsale; escribe solo BD local)…');

        try {
            $stats = (new StockSync($client))->run();
        } catch (Throwable $e) {
            $this->error('Sync abortada: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Bodegas', 'Stock creados', 'Actualizados', 'Eliminados', 'Omitidos', 'Errores'],
            [[$stats['bodegas'], $stats['creados'], $stats['actualizados'], $stats['eliminados'], $stats['omitidos'], count($stats['errores'])]],
        );

        if ($stats['omitidos'] > 0) {
            $this->comment("  · {$stats['omitidos']} stocks de variantes/bodegas sin espejo local (corre bsale:sync-catalog si parece mucho).");
        }

        foreach (array_slice($stats['errores'], 0, 20) as $err) {
            $ref = $err['office_id'] !== null ? "office {$err['office_id']}" : 'sync';
            $this->warn("  · {$ref}: {$err['error']}");
        }

        return self::SUCCESS;
    }
}
