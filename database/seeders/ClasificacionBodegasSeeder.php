<?php

namespace Database\Seeders;

use App\Models\Bodega;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

/**
 * Pre-carga INICIAL de la clasificación de bodegas (M04-F1) con el veredicto
 * del anexo 2 de D-003 (Excel conjunto Luis/Ricardo, 2026-08-06): 5 viven,
 * 6 mueren, 5 quedan [B] con pregunta en curso. Después del primer run manda
 * la UI: una fila con `clasificacion_confirmada = true` NO se toca jamás
 * (corrección de rumbo del dueño 06-ago — nada congelado en seeder).
 *
 * Matchea por `bsale_office_id` (catastro verificado en
 * docs/qa/INFRA/2026-07-02--INFRA--duplicados-variantid-catastro-bodegas.md),
 * NUNCA por nombre: un rename en Bsale no puede desviar la clasificación.
 * Si la bodega no existe localmente (BD fresca sin sync) la fila se salta:
 * las bodegas solo nacen del sync, este seeder solo las clasifica.
 *
 * Las [B] llevan la MEJOR HIPÓTESIS del anexo con confirmada=false → badge
 * «por confirmar» hasta que un humano guarde su ficha. Las muertas quedan
 * `en_operacion=false` (fuera de selectores operativos vía enOperacion());
 * su baja FORMAL (estado_baja) es de F2, por el flujo real, no por seeder.
 * `activa`/`es_virtual` son espejo de Bsale y no se tocan.
 */
class ClasificacionBodegasSeeder extends Seeder
{
    public function run(): void
    {
        $sucursales = Sucursal::pluck('id', 'codigo');

        // bsale_office_id => [codigo sucursal|null, proposito, en_operacion, confirmada]
        $veredictos = [
            // ✅ VIVEN (anexo 2: veredicto directo del dueño/Luis).
            4 => ['MIRADOR', 'fisica', true, true],               // MIRADOR (central)
            6 => ['COQUIMBO', 'fisica', true, true],              // COQUIMBO
            5 => ['ABATE-MOLINA', 'fisica', true, true],          // ABATE MOLINA
            7 => ['BUZETA', 'fisica', true, true],                // BUZETA (almacenaje masivo)
            16 => [null, 'virtual_operativa', true, true],        // BODEGA MERMAS (transversal)
            // ⏳ [B] — hipótesis del anexo, confirmada=false (pregunta en ronda 2).
            10 => ['MIRADOR', 'insumos', true, false],            // BODEGA SANTA ROSA (¿central de insumos?)
            14 => ['MIRADOR', 'taller', true, false],             // BODEGA SERVICIO TECNICO (¿cuál de las 2 ST queda?)
            1 => [null, 'taller', true, false],                   // SERVICIO TECNICO (no parecen duplicadas)
            11 => [null, 'virtual_operativa', true, false],       // RESERVA SUCURSALES (¿se mantiene?)
            15 => ['ABATE-MOLINA', 'transito', true, false],      // CONTENEDORES (¿dónde entra la importación si muere?)
            // ❌ MUEREN — fuera de operación; la baja formal la ejecuta F2.
            13 => [null, 'cerrada', false, true],                 // CERTIFICACIONES
            9 => [null, 'cerrada', false, true],                  // SERAFIN ZAMORA
            8 => [null, 'cerrada', false, true],                  // CONCEPCIÓN
            12 => [null, 'cerrada', false, true],                 // VIÑA DEL MAR
            2 => [null, 'cerrada', false, true],                  // ABATE PRUEBA
            3 => [null, 'cerrada', false, true],                  // COQUIMBO PRUEBA
        ];

        foreach ($veredictos as $officeId => [$codigo, $proposito, $enOperacion, $confirmada]) {
            $bodega = Bodega::where('bsale_office_id', $officeId)->first();

            if (! $bodega || $bodega->clasificacion_confirmada) {
                continue;
            }

            $bodega->update([
                // Si la sucursal aún no existe se clasifica igual, sin asignar
                // (precedente MaquinaSeeder: el seeder no revienta por orden).
                'sucursal_id' => $codigo !== null ? ($sucursales[$codigo] ?? null) : null,
                'proposito' => $proposito,
                'en_operacion' => $enOperacion,
                'clasificacion_confirmada' => $confirmada,
            ]);
        }
    }
}
