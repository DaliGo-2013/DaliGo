<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos base de botellon que el soplador selecciona al registrar produccion.
     * La calidad (primera/segunda/malo) NO va aqui: la capturan los contadores
     * del reporte; "2DA" y "FORMA DANADA" de la planilla papel salen del cruce
     * tipo x calidad. `codigo` es la clave estable del seeder (idempotencia
     * aunque el admin renombre el tipo desde la UI).
     */
    public function up(): void
    {
        Schema::create('tipos_botellon', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_botellon');
    }
};
