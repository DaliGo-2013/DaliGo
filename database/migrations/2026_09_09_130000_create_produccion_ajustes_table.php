<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambios del jefe sobre un reporte de producción, una fila POR CAMPO que
     * cambió (dueño 09-09): el soplador tiene que ver en sus 45 días que se
     * modificó, quién, qué ítem y cuánto; y toda producción corregida lleva
     * etiqueta «Modificado» con antes/después en el detalle.
     *
     * Tabla propia y no `audits`: los audits mezclan al recálculo del
     * soplador y al aprobar(), y su user_id es quien APLICÓ (el admin si el
     * ajuste superó el umbral), no quien lo pidió. Acá `responsable_id` es el
     * solicitante (el jefe que editó) y `autorizado_por` solo se llena cuando
     * lo aplicó otro. Idempotente; FKs nullables con nullOnDelete para que
     * borrar un usuario no se lleve la historia.
     */
    public function up(): void
    {
        if (Schema::hasTable('produccion_ajustes')) {
            return;
        }

        Schema::create('produccion_ajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('produccion_reportes')->cascadeOnDelete();
            $table->foreignId('aprobacion_id')->nullable()->constrained('aprobaciones')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('campo', 20);
            $table->unsignedInteger('antes');
            $table->unsignedInteger('despues');
            $table->string('motivo', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_ajustes');
    }
};
