<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preformas dañadas: preforma que se rompió antes de soplar (nunca llegó a
     * botellón). Es una cuarta categoria que suma al total producido, igual que
     * primera/segunda/malo, para que la diferencia con las asignadas se pueda
     * cerrar con numeros. Vive en registros (por tanda) y reportes (totales
     * denormalizados via ProduccionReporte::recalcularDesdeRegistros()).
     */
    public function up(): void
    {
        Schema::table('produccion_registros', function (Blueprint $table) {
            $table->unsignedInteger('danada')->default(0)->after('malo');
        });

        Schema::table('produccion_reportes', function (Blueprint $table) {
            $table->unsignedInteger('danada')->default(0)->after('malo');
        });
    }

    public function down(): void
    {
        Schema::table('produccion_registros', function (Blueprint $table) {
            $table->dropColumn('danada');
        });

        Schema::table('produccion_reportes', function (Blueprint $table) {
            $table->dropColumn('danada');
        });
    }
};
