<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos de respaldo del estado fisico del equipo al ingresarlo (rayones/golpes).
 * Tabla hija de ordenes_servicio (mismo patron que orden_servicio_repuestos):
 * borra en cascada. La `ruta` apunta a un archivo en el disco PRIVADO `local`
 * (storage/app/private/ordenes-servicio/fotos), servido solo con sesion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orden_servicio_fotos')) {
            return;
        }

        Schema::create('orden_servicio_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->cascadeOnDelete();
            $table->string('ruta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_servicio_fotos');
    }
};
