<?php

namespace App\Console\Commands;

use App\Models\ProduccionAsignacion;
use App\Models\ProduccionMovimiento;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

/**
 * Borra TODOS los datos de flujo del modulo Produccion (asignaciones, reportes,
 * tandas y kardex local) para resetear las pruebas. Es seguro mientras el
 * modulo no tenga uso real: el kardex es 100% local (nada se escribe en Bsale,
 * BsaleClient solo lee) y el catalogo (maquinas, tipos de botellon, productos)
 * queda intacto. Solo on-demand: JAMAS agendarlo ni meterlo a deploy.sh.
 */
class ProduccionLimpiarPruebas extends Command
{
    protected $signature = 'produccion:limpiar-pruebas {--force : No pedir confirmación}';

    protected $description = 'Borra todos los datos de prueba del módulo Producción (asignaciones, reportes, tandas y kardex local); el catálogo no se toca';

    public function handle(): int
    {
        $conteos = [
            'Asignaciones' => ProduccionAsignacion::count(),
            'Reportes' => ProduccionReporte::count(),
            'Tandas (registros)' => ProduccionRegistro::count(),
            'Movimientos de kardex' => ProduccionMovimiento::count(),
            'Audits de reportes' => Audit::where('auditable_type', ProduccionReporte::class)->count(),
        ];

        if (array_sum($conteos) === 0) {
            $this->info('El módulo Producción ya está vacío; nada que limpiar.');

            return self::SUCCESS;
        }

        $this->table(['Qué se borrará', 'Filas'], collect($conteos)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->line('El catálogo (máquinas, tipos de botellón, productos) y Bsale NO se tocan.');

        if (! $this->option('force') && ! $this->confirm('¿Borrar TODOS los datos del flujo de producción?')) {
            $this->info('Cancelado; no se borró nada.');

            return self::SUCCESS;
        }

        // Orden hijo -> padre, explicito (sin depender de cascadas), en una
        // transaccion: o se limpia todo o no se limpia nada.
        DB::transaction(function () {
            ProduccionMovimiento::query()->delete();
            ProduccionRegistro::query()->delete();
            // Sin esto quedarian audits huerfanos apuntando a reportes borrados.
            Audit::where('auditable_type', ProduccionReporte::class)->delete();
            ProduccionReporte::query()->delete();
            ProduccionAsignacion::query()->delete();
        });

        $this->info('Módulo Producción limpio: '.array_sum($conteos).' filas eliminadas.');

        return self::SUCCESS;
    }
}
