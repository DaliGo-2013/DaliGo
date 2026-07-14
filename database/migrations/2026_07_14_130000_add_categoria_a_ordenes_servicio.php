<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categoría de cierre para las máquinas PROPIAS de la empresa (IMP. DALI) que
 * entran al taller para reacondicionar y revender: primera | segunda | desarme.
 * Solo aplica a esas máquinas (se decide en la etapa de reparación); para
 * clientes comunes queda null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ordenes_servicio', 'categoria')) {
            Schema::table('ordenes_servicio', function (Blueprint $table) {
                $table->string('categoria', 20)->nullable()->after('causa_falla');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ordenes_servicio', 'categoria')) {
            Schema::table('ordenes_servicio', function (Blueprint $table) {
                $table->dropColumn('categoria');
            });
        }
    }
};
