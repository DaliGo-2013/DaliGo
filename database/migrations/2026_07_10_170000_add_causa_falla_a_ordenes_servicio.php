<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Causa de la falla que diagnostica el TECNICO al reparar: mal uso del
     * cliente, desgaste por uso normal o falla de fabrica (ver
     * OrdenServicio::CAUSAS_FALLA). Nullable: es opcional y las ordenes viejas
     * quedan "sin determinar". Indicador clave para el informe (capacitacion al
     * cliente). VARCHAR(20) alcanza para las claves y es seguro en indices
     * MySQL 5.7 (aunque aqui no se indexa). Idempotente.
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_servicio', 'causa_falla')) {
                $table->string('causa_falla', 20)->nullable()->after('falla_tecnico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('ordenes_servicio', 'causa_falla')) {
                $table->dropColumn('causa_falla');
            }
        });
    }
};
