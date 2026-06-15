<?php

namespace Database\Seeders;

use App\Models\TipoBotellon;
use Illuminate\Database\Seeder;

class TipoBotellonSeeder extends Seeder
{
    /**
     * Tipos base de botellon, factorizados de la planilla papel "Comprobante
     * entrega de produccion" (la calidad 1a/2a/danada NO es tipo: la capturan
     * los contadores del reporte). Idempotente: firstOrCreate por `codigo`
     * (clave estable aunque el admin renombre desde la UI). Seguro re-ejecutar
     * en cada deploy; no pisa renombres ni tipos creados desde la UI.
     */
    public function run(): void
    {
        $tipos = [
            ['codigo' => 'AZUL-20L', 'nombre' => 'Azul 20L s/manilla'],
            ['codigo' => 'AZUL-20L-MANILLA', 'nombre' => 'Azul 20L c/manilla'],
            ['codigo' => 'AZUL-10L-RETORNABLE', 'nombre' => 'Azul 10L retornable'],
            ['codigo' => 'INCOLORO-10L-RETORNABLE', 'nombre' => 'Incoloro 10L retornable'],
        ];

        foreach ($tipos as $tipo) {
            TipoBotellon::firstOrCreate(
                ['codigo' => $tipo['codigo']],
                ['nombre' => $tipo['nombre'], 'activo' => true],
            );
        }
    }
}
