<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de servicios de terreno (técnico industrial): mantenciones,
     * reparaciones e instalaciones de plantas de osmosis, llenadoras y
     * lavadoras, con su tarifa en UF, duración y detalle de qué incluye.
     * Editable desde la app (el seeder solo crea lo que falte, no pisa
     * los valores que el equipo actualice). Idempotente.
     */
    public function up(): void
    {
        if (Schema::hasTable('servicios_terreno')) {
            return;
        }

        Schema::create('servicios_terreno', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->decimal('valor_uf', 6, 2)->nullable();   // tarifa neta en UF
            $table->string('duracion')->nullable();          // "1 día", "1/2 día", "1/2 mañana"
            $table->text('incluye')->nullable();             // qué incluye el servicio
            $table->text('observaciones')->nullable();       // ej. "no incluye cabezal/estanque"
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios_terreno');
    }
};
