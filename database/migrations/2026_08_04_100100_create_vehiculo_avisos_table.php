<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de avisos de vencimiento ya enviados (comando
 * `vehiculos:avisar-vencimientos`, que corre todos los días).
 *
 * Existe SOLO para no repetir el mismo aviso cada día. La clave única incluye
 * `vence` a propósito: cuando se renueva el documento la fecha cambia, así que
 * el próximo vencimiento vuelve a ser avisable sin tener que limpiar nada.
 * Sin `vence` en la clave, renovar un SOAP dejaría al vehículo mudo para
 * siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->string('documento', 40);   // clave del documento (rt_vence, soap_vence, …)
            $table->string('hito', 12);        // por_vencer | vencido
            $table->date('vence');
            $table->timestamp('avisado_at');

            $table->unique(['vehiculo_id', 'documento', 'hito', 'vence'], 'vehiculo_avisos_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_avisos');
    }
};
