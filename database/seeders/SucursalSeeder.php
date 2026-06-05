<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    /**
     * Sucursales base de DALI. Idempotente (firstOrCreate por codigo):
     * es seguro re-ejecutarlo; no duplica ni pisa cambios hechos desde la UI.
     */
    public function run(): void
    {
        $sucursales = [
            ['codigo' => 'MIRADOR', 'nombre' => 'Mirador', 'es_central' => true],
            ['codigo' => 'COQUIMBO', 'nombre' => 'Coquimbo', 'es_central' => false],
            ['codigo' => 'ABATE-MOLINA', 'nombre' => 'Abate Molina', 'es_central' => false],
            ['codigo' => 'BUZETA', 'nombre' => 'Buzeta', 'es_central' => false],
        ];

        foreach ($sucursales as $s) {
            Sucursal::firstOrCreate(
                ['codigo' => $s['codigo']],
                ['nombre' => $s['nombre'], 'es_central' => $s['es_central'], 'activa' => true],
            );
        }
    }
}
