<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maquinas sopladoras de preformas, asociadas a una sucursal.
     * Solo nombre + sucursal: su proposito es atribuir la produccion
     * (registros) a una maquina para metricas por maquina.
     */
    public function up(): void
    {
        Schema::create('maquinas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // restrictOnDelete: el borrado de sucursales ya esta guardado en su
            // controller; esto es el cinturon por si se borra por otra via.
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            // El mismo nombre puede repetirse entre sucursales, no dentro de una.
            $table->unique(['sucursal_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquinas');
    }
};
