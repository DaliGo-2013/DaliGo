<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respaldo digital de los documentos del vehículo (pedido del dueño 11-08-2026):
 * la foto del permiso de circulación / SOAP / revisión técnica, para que el
 * conductor la muestre desde el teléfono si lo controlan en ruta.
 *
 * CADA SUBIDA ES UNA FILA y nada se pisa: el vigente es el más nuevo por
 * (vehículo, documento) y los anteriores quedan como historial — «que se pueda
 * ver todas las veces que uno quiera como respaldo». El archivo vive en
 * storage/ (privado, fuera del docroot y fuera del repo público, D-012); acá
 * solo va su ruta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            // La clave del mapa Vehiculo::DOCUMENTOS ('soap_vence', 'rt_vence'...):
            // una fuente única para fechas, avisos y ahora respaldos.
            $table->string('documento', 40);
            $table->string('ruta');
            $table->unsignedInteger('tamano_kb');
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // La consulta de siempre: el vigente de cada documento del vehículo.
            $table->index(['vehiculo_id', 'documento', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_documentos');
    }
};
