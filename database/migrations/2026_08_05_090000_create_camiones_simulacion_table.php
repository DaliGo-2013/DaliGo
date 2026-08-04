<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Camiones del SIMULADOR de carga — catálogo propio, separado de la flota.
 *
 * Decisión del dueño (05-08-2026): el simulador NO se engancha a los vehículos
 * de la flota. Son dos preguntas distintas — la flota administra documentos y
 * vencimientos por PATENTE; el simulador necesita cajas de carga TIPO («un
 * HD35», «el contenedor de 40'») sin importar cuál de los dos HD35 sale hoy.
 *
 * Y la lección operativa que motivó el cambio: la versión enganchada a la flota
 * dependía de cargar medidas por phpMyAdmin (un paso manual que nunca ocurrió,
 * y producción mostró «falta medir» para todo). Este catálogo se SIEMBRA
 * (CamionesSimulacionSeeder) y el deploy lo crea solo: cero pasos manuales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camiones_simulacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            // Medidas ÚTILES de la caja, por dentro, en CENTÍMETROS ENTEROS
            // (regla del motor: nunca metros con coma flotante).
            $table->unsignedSmallInteger('largo_cm');
            $table->unsignedSmallInteger('ancho_cm');
            $table->unsignedSmallInteger('alto_cm');
            $table->unsignedInteger('peso_max_kg')->nullable();   // null = sin dato, no limita
            $table->unsignedSmallInteger('pasillo_cm')->default(0);
            $table->boolean('activo')->default(true);
            $table->string('notas', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camiones_simulacion');
    }
};
