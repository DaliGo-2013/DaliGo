<?php

namespace Database\Seeders;

use App\Models\PlanHito;
use App\Support\PlanProyecto;
use Illuminate\Database\Seeder;

/**
 * Siembra los hitos re-baselinados del plan (PlanProyecto::HITOS) la primera
 * vez. firstOrCreate por clave → idempotente y NO pisa lo editado desde /plan:
 * si el gerente corre una fecha o marca uno cumplido, el deploy no lo revierte.
 */
class PlanHitosSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlanProyecto::HITOS as $hito) {
            PlanHito::firstOrCreate(
                ['clave' => $hito['key']],
                ['etiqueta' => $hito['label'], 'fecha' => $hito['fecha'], 'cumplido' => $hito['cumplido']],
            );
        }
    }
}
