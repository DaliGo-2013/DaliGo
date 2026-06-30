<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite VARIAS producciones por (soplador, fecha, turno): se quita la
     * restriccion unica que forzaba un solo reporte por dia/turno. Cada "Asignar"
     * crea ahora una produccion nueva e independiente (ver ProduccionController::
     * asignarStore). Se deja un indice no unico para los lookups por soplador+fecha.
     */
    public function up(): void
    {
        Schema::table('produccion_asignaciones', function (Blueprint $table) {
            $table->dropUnique(['soplador_id', 'fecha', 'turno']);
            $table->index(['soplador_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('produccion_asignaciones', function (Blueprint $table) {
            $table->dropIndex(['soplador_id', 'fecha']);
            $table->unique(['soplador_id', 'fecha', 'turno']);
        });
    }
};
