<?php

namespace App\Console\Commands;

use App\Services\Produccion\CorteSic;
use Illuminate\Console\Command;

/**
 * Corte SIC de produccion (P-M11-21): cascaron artisan que el scheduler
 * dispara cada 2 horas (grilla I-01). La logica vive en CorteSic — el service
 * decide por CONDICION (¿hay un turno activo con reportes que evaluar?), no
 * por cadencia, asi que correrlo fuera de horario es un no-op silencioso.
 */
class ProduccionCorteSic extends Command
{
    protected $signature = 'produccion:corte-sic {--dry-run : Muestra lo que avisaría, sin registrar ni enviar}';

    protected $description = 'Proyecta la producción del turno vs meta y avisa al jefe si va en riesgo (SIC cada 2 h)';

    public function handle(CorteSic $corte): int
    {
        $seco = (bool) $this->option('dry-run');

        $filas = $corte->ejecutar($seco);

        if ($filas === []) {
            $this->info('Sin cortes: ningún turno activo con reportes que evaluar.');

            return self::SUCCESS;
        }

        $this->table(['Soplador', 'Turno', 'Producido', 'Meta', 'Proyección', 'Acción'], $filas);
        $this->info($seco
            ? 'Simulación: no se registró ni se envió nada.'
            : 'Corte registrado.');

        return self::SUCCESS;
    }
}
