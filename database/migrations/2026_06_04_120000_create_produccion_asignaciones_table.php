<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asignacion diaria de produccion: el Jefe de Bodega asigna una cantidad
     * de preformas a un soplador para una fecha y turno.
     */
    public function up(): void
    {
        Schema::create('produccion_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soplador_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('turno')->default('dia'); // dia | noche
            $table->unsignedInteger('asignadas')->default(0);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['soplador_id', 'fecha', 'turno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_asignaciones');
    }
};
