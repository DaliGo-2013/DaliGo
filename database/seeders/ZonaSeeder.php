<?php

namespace Database\Seeders;

use App\Models\Zona;
use Illuminate\Database\Seeder;

/**
 * Zonas comerciales base (D-006, info de Hector 08-07). Idempotente: match por
 * nombre. Los limites exactos/comunas los confirma Luis (texto libre en
 * descripcion mientras tanto). Un vendedor por zona; el jefe reemplaza en
 * vacaciones (suplencia = reasignacion temporal de users.zona_id).
 */
class ZonaSeeder extends Seeder
{
    public function run(): void
    {
        $zonas = [
            ['nombre' => 'Santiago Norte', 'descripcion' => 'RM norte + Los Andes, San Felipe'],
            ['nombre' => 'Santiago Sur', 'descripcion' => 'RM sur + Melipilla'],
            ['nombre' => '6ª Región', 'descripcion' => 'Rancagua hasta Teno'],
            ['nombre' => '7ª Región', 'descripcion' => 'Curicó y Talca'],
        ];

        foreach ($zonas as $z) {
            Zona::updateOrCreate(
                ['nombre' => $z['nombre']],
                ['descripcion' => $z['descripcion'], 'activa' => true],
            );
        }
    }
}
