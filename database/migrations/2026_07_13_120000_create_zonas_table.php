<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogo de zonas comerciales (D-006). Simple, sin CRM: un vendedor
     * atiende una zona (users.zona_id) y la zona del cliente se hereda de su
     * vendedor, salvo que el cliente tenga una zona EXPLICITA que la
     * sobreescriba (clientes.zona_id — ajuste del dueno, "siempre hay
     * excepciones"). Alimenta la hoja de ruta por zona de DESPACHOS-v1.
     */
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');            // VARCHAR(191) por defaultStringLength
            $table->string('descripcion')->nullable(); // comunas/regiones (texto libre)
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
