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
     * asignarStore).
     *
     * Orden CRITICO en MySQL: la FK de soplador_id se apoya en el indice unico
     * [soplador_id, fecha, turno] (soplador_id es su columna lider). Soltar ese
     * indice sin otro que cubra la FK falla con error 1553 ("needed in a foreign
     * key constraint"). Por eso se crea PRIMERO un indice de cobertura
     * [soplador_id, fecha] (prefijo izquierdo) y recien despues se suelta el
     * unico. SQLite (tests) no valida esto, pero MySQL (prod) si.
     * Idempotente: seguro re-correr tras un deploy a medias.
     */
    public function up(): void
    {
        if (! Schema::hasIndex('produccion_asignaciones', ['soplador_id', 'fecha'])) {
            Schema::table('produccion_asignaciones', function (Blueprint $table) {
                $table->index(['soplador_id', 'fecha']);
            });
        }

        if (Schema::hasIndex('produccion_asignaciones', ['soplador_id', 'fecha', 'turno'], 'unique')) {
            Schema::table('produccion_asignaciones', function (Blueprint $table) {
                $table->dropUnique(['soplador_id', 'fecha', 'turno']);
            });
        }
    }

    public function down(): void
    {
        // Reverso: re-crear el unico (vuelve a cubrir la FK de soplador_id) antes
        // de soltar el indice de cobertura.
        if (! Schema::hasIndex('produccion_asignaciones', ['soplador_id', 'fecha', 'turno'], 'unique')) {
            Schema::table('produccion_asignaciones', function (Blueprint $table) {
                $table->unique(['soplador_id', 'fecha', 'turno']);
            });
        }

        if (Schema::hasIndex('produccion_asignaciones', ['soplador_id', 'fecha'])) {
            Schema::table('produccion_asignaciones', function (Blueprint $table) {
                $table->dropIndex(['soplador_id', 'fecha']);
            });
        }
    }
};
