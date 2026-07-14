<?php

namespace Database\Seeders;

use App\Models\Aprobacion;
use App\Models\ReglaAprobacion;
use Illuminate\Database\Seeder;

class ReglasAprobacionSeeder extends Seeder
{
    /**
     * Reglas base del motor de aprobaciones (M14). Idempotente (firstOrCreate
     * por tipo_accion): nunca pisa una regla ajustada desde la BD. v1 siembra
     * UNA regla — ajuste de reporte de producción → aprueba `admin` (Luis),
     * sin cadena de escalamiento (admin es el tope; se estrena con M04).
     * Los consumidores futuros (M04/M05/M07/M13) agregan aquí su regla al
     * integrarse, junto con su handler y su tipo en Aprobacion::TIPOS_ACCION.
     */
    public function run(): void
    {
        ReglaAprobacion::firstOrCreate(
            ['tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE],
            [
                'descripcion' => 'Ajuste de reporte de producción sobre el umbral de unidades',
                'activa' => true,
                'umbral_config' => 'umbral_ajuste_produccion_unidades',
                'rol_aprobador' => 'admin',
                'rol_escalamiento' => null,
            ],
        );
    }
}
