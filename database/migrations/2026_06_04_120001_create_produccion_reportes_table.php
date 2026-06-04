<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reporte de produccion del soplador contra su asignacion del dia.
     * Cantidades: primera (vendible), segunda (defecto leve), malo (reciclaje).
     * Flujo de estados: borrador -> enviado -> aprobado ; el jefe puede devolver.
     */
    public function up(): void
    {
        Schema::create('produccion_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_id')->unique()->constrained('produccion_asignaciones')->cascadeOnDelete();
            $table->foreignId('soplador_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('turno')->default('dia');
            $table->unsignedInteger('asignadas')->default(0); // snapshot de la asignacion
            $table->unsignedInteger('primera')->default(0);
            $table->unsignedInteger('segunda')->default(0);
            $table->unsignedInteger('malo')->default(0);
            $table->string('motivo')->nullable();   // justificacion del soplador si hay diferencia
            $table->text('obs')->nullable();
            $table->string('estado')->default('borrador'); // borrador | enviado | aprobado | devuelto
            $table->timestamp('enviado_at')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->string('motivo_ajuste')->nullable(); // si el jefe corrige cantidades
            $table->string('devuelto_motivo')->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_reportes');
    }
};
