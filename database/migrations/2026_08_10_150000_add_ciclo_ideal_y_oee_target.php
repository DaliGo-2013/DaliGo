<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los dos datos que le faltaban al OEE (P-M11-11, PLAN-M11-FINAL F2):
 *
 * - `recetas.ciclo_ideal_seg`: segundos que tarda un ciclo de soplado del
 *   botellón (vive en la fila ROL_PREFORMA — la única obligatoria y
 *   permanente de la receta; la de tapa se borra cuando el botellón no
 *   lleva). NULL = «sin ciclo cargado»: el informe lo declara, jamás
 *   inventa un rendimiento. smallint y no tinyint: un ciclo puede exceder
 *   255 s.
 * - `maquinas.oee_target`: la meta de OEE por máquina en % (aporte B4 del
 *   benchmark — cada máquina declara su meta, no una global). NULL = sin
 *   meta; el informe pinta el OEE contra ella cuando existe.
 *
 * Aditiva e idempotente (guardas hasColumn, idioma de la migración de
 * paradas 2026_08_10_120000).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recetas', 'ciclo_ideal_seg')) {
            Schema::table('recetas', function (Blueprint $table) {
                $table->unsignedSmallInteger('ciclo_ideal_seg')->nullable()->after('confirmada');
            });
        }

        if (! Schema::hasColumn('maquinas', 'oee_target')) {
            Schema::table('maquinas', function (Blueprint $table) {
                $table->unsignedTinyInteger('oee_target')->nullable()->after('activa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recetas', 'ciclo_ideal_seg')) {
            Schema::table('recetas', fn (Blueprint $table) => $table->dropColumn('ciclo_ideal_seg'));
        }

        if (Schema::hasColumn('maquinas', 'oee_target')) {
            Schema::table('maquinas', fn (Blueprint $table) => $table->dropColumn('oee_target'));
        }
    }
};
