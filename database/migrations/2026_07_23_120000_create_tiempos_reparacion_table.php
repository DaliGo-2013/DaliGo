<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de tiempos estándar de reparación ("Costos generales de
     * reparación"): cada trabajo del taller (Cambio de caldera, termostato,
     * celda peltier, etc.) lleva sus HORAS estándar, fijadas por jefatura. La
     * mano de obra de una orden = horas del trabajo × valor hora, y el técnico
     * NO la puede modificar. Editable desde la app; el seeder solo crea lo que
     * falte (no pisa las horas que jefatura ajuste). Idempotente.
     */
    public function up(): void
    {
        if (Schema::hasTable('tiempos_reparacion')) {
            return;
        }

        Schema::create('tiempos_reparacion', function (Blueprint $table) {
            $table->id();
            $table->string('trabajo', 191)->unique();   // coincide con respuestas_trabajo (config)
            $table->decimal('horas', 4, 1)->default(1);  // horas estándar (ej. 1, 1.5, 2)
            $table->string('grupo', 191)->nullable();    // rótulo del grupo (Reparada / Sin solución…)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiempos_reparacion');
    }
};
