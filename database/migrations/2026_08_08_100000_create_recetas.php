<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receta paramétrica del botellón (P-M11-10, PLAN-M11-FINAL F1): cuántas
 * unidades de cada componente (preforma, tapa) consume UNA unidad del
 * producto terminado. El backflush del kardex la lee al aprobar un reporte:
 * consumo = (buenos + merma) × cantidad — la merma también consumió.
 *
 * `componente_id` es NULLABLE a propósito: la hipótesis [B] del seeder
 * (1 preforma + 1 tapa) no puede conocer el SKU concreto en un catálogo
 * espejado de miles; Luis enlaza el producto por UI (patrón D-003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            // El botellón (producto terminado del catálogo).
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            // 'preforma' | 'tapa' (constantes de Receta; no enum, MySQL 5.7-safe).
            $table->string('rol', 32);
            // El producto componente concreto; null = aún sin enlazar (el
            // movimiento de kardex sale sin producto, degradación con gracia).
            $table->foreignId('componente_id')->nullable()->constrained('productos')->nullOnDelete();
            // Unidades del componente por UNA unidad de botellón.
            $table->decimal('cantidad', 14, 4)->default(1);
            // D-003: la hipótesis del seeder nace false; guardar desde la UI
            // confirma. Es badge de calidad del dato, NO gate del backflush.
            $table->boolean('confirmada')->default(false);
            $table->timestamps();

            // Una fila por rol por botellón (seeder y CRUD upsertean contra esto).
            $table->unique(['producto_id', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};
