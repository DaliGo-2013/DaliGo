<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros (tandas) de produccion dentro de un reporte: cada "Agregar"
     * del soplador inserta una fila nueva (append-only, sin upsert) con la
     * maquina y el tipo de botellon. Los totales del reporte se mantienen
     * denormalizados via ProduccionReporte::recalcularDesdeRegistros().
     *
     * maquina_id / tipo_botellon_id son nullable a proposito:
     * - el backfill de reportes pre-existentes no conoce maquina ni tipo;
     * - en la transicion post-deploy puede no haber maquinas creadas aun;
     * - nullOnDelete conserva el historial si se elimina el catalogo.
     */
    public function up(): void
    {
        Schema::create('produccion_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('produccion_reportes')->cascadeOnDelete();
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->nullOnDelete();
            $table->foreignId('tipo_botellon_id')->nullable()->constrained('tipos_botellon')->nullOnDelete();
            $table->unsignedInteger('primera')->default(0);
            $table->unsignedInteger('segunda')->default(0);
            $table->unsignedInteger('malo')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_registros');
    }
};
