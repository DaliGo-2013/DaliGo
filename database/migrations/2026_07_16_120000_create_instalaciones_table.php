<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de instalaciones del técnico industrial (Carlos Tablante): plasma su
 * Excel "INSTALACION DE MAQUINAS". Cada fila = una instalación/puesta en marcha
 * en terreno, con datos comerciales (vendedor, factura, forma de pago, días).
 * Es un LEDGER aparte de la agenda (agenda_trabajos) y del catálogo de tarifas
 * (servicios_terreno). Cliente denormalizado (como ordenes_servicio).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('instalaciones')) {
            return;
        }

        Schema::create('instalaciones', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->string('cliente_rut', 20)->nullable();
            $table->string('comuna_region')->nullable();
            $table->string('categoria')->index();           // lavadora | llenadora | planta
            $table->string('producto')->nullable();          // modelo instalado (texto libre)
            $table->boolean('instalacion')->default(false);  // SI/NO del Excel
            $table->boolean('puesta_en_marcha')->default(false);
            $table->unsignedSmallInteger('dias')->nullable();
            $table->string('vendedor')->nullable();          // texto libre (con sugerencias)
            $table->string('n_factura', 50)->nullable();
            $table->date('fecha_factura')->nullable();
            $table->string('forma_pago')->nullable();        // transferencia | efectivo | ...
            $table->date('fecha_pago')->nullable();
            $table->string('creado_por')->nullable();        // nombre de quien registró
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instalaciones');
    }
};
