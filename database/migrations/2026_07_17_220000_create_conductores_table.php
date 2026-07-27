<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conductores (choferes que retiran en ruta para el ingreso por lote). Antes
 * eran una lista fija en config; ahora se administran desde la app (agregar /
 * editar / activar-desactivar). Sin borrar: un conductor que ya no maneja se
 * desactiva (los lotes históricos guardan el nombre denormalizado). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conductores')) {
            return;
        }

        Schema::create('conductores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conductores');
    }
};
