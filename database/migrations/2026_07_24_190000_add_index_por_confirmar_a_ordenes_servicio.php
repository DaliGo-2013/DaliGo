<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice para el conteo "por confirmar" (fuente qr/ruta + confirmada_at NULL):
 * lo usan el badge del menú (cada página), el poll de 25s del listado ST y el
 * dashboard — hasta ahora era full scan (hallazgo del research de badges).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->index(['fuente', 'confirmada_at'], 'ordenes_servicio_por_confirmar_index');
        });

        // Visibilidad de "Mis solicitudes" en el hub de la campanita: COUNT
        // por solicitante en cada página — merece su índice.
        Schema::table('aprobaciones', function (Blueprint $table) {
            $table->index('solicitante_id', 'aprobaciones_solicitante_index');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropIndex('ordenes_servicio_por_confirmar_index');
        });

        Schema::table('aprobaciones', function (Blueprint $table) {
            $table->dropIndex('aprobaciones_solicitante_index');
        });
    }
};
