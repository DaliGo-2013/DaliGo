<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repuestos usados en un trabajo de la agenda de terreno (servicio industrial).
 * El técnico industrial los registra al marcar el trabajo como "Realizado". Un
 * trabajo tiene 0..N repuestos. Sin precios: al informe industrial le importa el
 * USO en números (unidades por repuesto). Espeja `orden_servicio_repuestos` del
 * taller, sin las columnas de facturación. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agenda_trabajo_repuestos')) {
            return;
        }

        Schema::create('agenda_trabajo_repuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_trabajo_id')->constrained('agenda_trabajos')->cascadeOnDelete();
            $table->string('nombre', 191);
            $table->unsignedInteger('cantidad')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_trabajo_repuestos');
    }
};
