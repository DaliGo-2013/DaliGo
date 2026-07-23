<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Procedencia de la preforma del turno (saco o caja): permite rastrear en
     * qué formato llegó el material asignado. Nullable: asignaciones
     * históricas o sin dato. Idempotente.
     */
    public function up(): void
    {
        Schema::table('produccion_asignaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('produccion_asignaciones', 'procedencia')) {
                $table->string('procedencia', 20)->nullable()->after('preforma_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produccion_asignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('produccion_asignaciones', 'procedencia')) {
                $table->dropColumn('procedencia');
            }
        });
    }
};
