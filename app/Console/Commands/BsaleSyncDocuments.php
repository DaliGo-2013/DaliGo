<?php

namespace App\Console\Commands;

use App\Services\Bsale\BsaleClient;
use App\Services\Bsale\DocumentSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Espejo read-only de los documentos de venta de Bsale → `documentos_venta`
 * (+ detalles). Opera por ventana `emissiondaterange` (nunca backfill completo:
 * producción tiene ~676k documentos). Idempotente; sin fase de delete.
 */
class BsaleSyncDocuments extends Command
{
    protected $signature = 'bsale:sync-documents';

    protected $description = 'Sincroniza documentos de venta de Bsale → documentos_venta (ventana por fecha; solo lectura en Bsale).';

    public function handle(BsaleClient $client): int
    {
        if (! $client->hasToken()) {
            $this->error('Falta BSALE_ACCESS_TOKEN en .env (config services.bsale.token).');

            return self::FAILURE;
        }

        $this->info('Sincronizando documentos de venta desde Bsale (solo lectura en Bsale; escribe solo BD local)…');

        try {
            $stats = (new DocumentSync($client))->run();
        } catch (Throwable $e) {
            $this->error('Sync abortada: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Creados', 'Actualizados', 'Detalles', 'Omitidos', 'Errores'],
            [[$stats['creados'], $stats['actualizados'], $stats['detalles'], $stats['omitidos'], count($stats['errores'])]],
        );

        foreach (array_slice($stats['errores'], 0, 20) as $err) {
            $this->warn("  · documento {$err['document_id']} / folio {$err['folio']}: {$err['error']}");
        }

        return self::SUCCESS;
    }
}
