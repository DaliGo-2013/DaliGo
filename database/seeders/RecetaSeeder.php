<?php

namespace Database\Seeders;

use App\Models\Receta;
use App\Models\TipoBotellon;
use Illuminate\Database\Seeder;

/**
 * Hipótesis [B] de la receta (PLAN-M11-FINAL §2): 1 preforma + 1 tapa =
 * 1 botellón, `confirmada=false`. La respuesta de Luis es un ajuste de
 * datos vía UI (patrón D-003, como la clasificación de bodegas), no de código.
 *
 * - NO crea productos: el componente nace SIN enlazar (`componente_id=null`)
 *   porque en un catálogo espejado de miles de SKUs adivinar la tapa concreta
 *   sería peor que declarar el hueco (el kardex ya degrada con gracia).
 * - firstOrCreate por [producto_id, rol]: idempotente y JAMÁS pisa una fila
 *   existente — confirmada o no, lo editado desde la UI se respeta.
 */
class RecetaSeeder extends Seeder
{
    public function run(): void
    {
        $botellones = TipoBotellon::whereNotNull('producto_id')->pluck('producto_id')->unique();

        foreach ($botellones as $productoId) {
            foreach (Receta::ROLES as $rol) {
                Receta::firstOrCreate(
                    ['producto_id' => $productoId, 'rol' => $rol],
                    ['cantidad' => 1, 'confirmada' => false],
                );
            }
        }
    }
}
