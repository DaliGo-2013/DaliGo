<?php

namespace App\Console\Commands;

use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\ClientSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Barre los clientes activos de Bsale (solo lectura) y hace upsert en `clientes`,
 * preservando los campos locales (segmento, notas, vendedor asignado).
 * Idempotente. La automatizacion (cron/webhooks) va en incrementos posteriores.
 */
class BsaleSyncClients extends Command
{
    protected $signature = 'bsale:sync-clients';

    protected $description = 'Sincroniza los clientes de Bsale → clientes (solo lectura en Bsale; preserva campos locales).';

    public function handle(BsaleClient $client): int
    {
        if (! $client->hasToken()) {
            $this->error('Falta BSALE_ACCESS_TOKEN en .env (config services.bsale.token).');

            return self::FAILURE;
        }

        $this->info('Sincronizando clientes desde Bsale (solo lectura en Bsale; escribe solo BD local)…');

        try {
            $stats = (new ClientSync($client))->run();
        } catch (Throwable $e) {
            $this->error('Sync abortada: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Creados', 'Actualizados', 'Adoptados', 'Duplicados RUT', 'Errores'],
            [[$stats['creados'], $stats['actualizados'], $stats['adoptados'], $stats['duplicados'], count($stats['errores'])]],
        );

        if ($stats['duplicados'] > 0) {
            $this->line("  {$stats['duplicados']} cliente(s) omitido(s) por RUT ya existente en otra ficha — esperado (Bsale trae varios registros por RUT), no es error.");
        }

        foreach (array_slice($stats['errores'], 0, 20) as $err) {
            $this->warn("  · cliente {$err['client_id']} / rut {$err['rut']}: {$err['error']}");
        }

        return self::SUCCESS;
    }
}
