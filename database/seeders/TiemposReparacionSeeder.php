<?php

namespace Database\Seeders;

use App\Models\TiempoReparacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Siembra el catálogo de tiempos estándar de reparación desde la lista fija de
 * "Trabajo realizado" (config/servicio_tecnico.php). Las horas son un ESTIMADO
 * inicial por tipo de trabajo; jefatura las calibra desde la app. Idempotente
 * y NO pisa las horas ajustadas (firstOrCreate por trabajo).
 */
class TiemposReparacionSeeder extends Seeder
{
    public function run(): void
    {
        $grupos = config('servicio_tecnico.respuestas_trabajo', []);

        foreach ($grupos as $grupo => $trabajos) {
            foreach ($trabajos as $trabajo) {
                TiempoReparacion::firstOrCreate(
                    ['trabajo' => $trabajo],
                    ['horas' => $this->horasEstimadas($trabajo), 'grupo' => $grupo, 'activo' => true],
                );
            }
        }
    }

    /**
     * Estimado inicial de horas según el tipo de trabajo (jefatura lo ajusta).
     * Trabajos rápidos 0,5 h; los que implican desarmar/cambiar piezas grandes
     * 1,5 h; el resto 1 h.
     */
    private function horasEstimadas(string $trabajo): float
    {
        $t = Str::lower($trabajo);

        $rapidos = ['desbloquea', 'manguera', 'cable/conexión', 'cambio de filtro', 'se desarma', 'verifica buen funcionamiento'];
        foreach ($rapidos as $k) {
            if (Str::contains($t, $k)) {
                return 0.5;
            }
        }

        $largos = ['caldera', 'peltier', 'placa eléctrica', 'bomba de agua'];
        foreach ($largos as $k) {
            if (Str::contains($t, $k)) {
                return 1.5;
            }
        }

        return 1.0;
    }
}
