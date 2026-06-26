<?php

namespace Database\Seeders;

use App\Models\Maquina;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class MaquinaSeeder extends Seeder
{
    /**
     * Maquinas sopladoras base de las sucursales que soplan (Mirador y Coquimbo,
     * segun la biblia). Idempotente (firstOrCreate por nombre+sucursal): seguro
     * re-ejecutar en cada deploy; no pisa renombres ni maquinas creadas por UI.
     */
    public function run(): void
    {
        $porSucursal = [
            'MIRADOR' => ['Sopladora 1', 'Sopladora 2'],
            'COQUIMBO' => ['Sopladora 1'],
        ];

        foreach ($porSucursal as $codigo => $maquinas) {
            $sucursal = Sucursal::where('codigo', $codigo)->first();

            if (! $sucursal) {
                continue;
            }

            foreach ($maquinas as $nombre) {
                Maquina::firstOrCreate(
                    ['nombre' => $nombre, 'sucursal_id' => $sucursal->id],
                    ['activa' => true],
                );
            }
        }
    }
}
