<?php

namespace App\Console\Commands;

use App\Services\Aprobaciones\Aprobaciones;
use Illuminate\Console\Command;

/**
 * Escala las solicitudes de aprobacion pendientes sin respuesta (M14,
 * P-M14-04): pasado N minutos (`aprobacion_escala_minutos`), la solicitud
 * pasa al `rol_escalamiento` de su regla y se re-notifica. La logica vive en
 * Aprobaciones::escalarVencidas() (lock + re-check por fila); aqui solo el
 * cascaron artisan que el scheduler dispara en la grilla de 15 min (I-01).
 */
class AprobacionesEscalar extends Command
{
    protected $signature = 'aprobaciones:escalar';

    protected $description = 'Escala al siguiente rol las solicitudes de aprobación pendientes que superaron el plazo sin respuesta.';

    public function handle(Aprobaciones $aprobaciones): int
    {
        $escaladas = $aprobaciones->escalarVencidas();

        $this->info("Solicitudes escaladas: {$escaladas}.");

        return self::SUCCESS;
    }
}
