<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HISTORIAL SIMULADO VS. REAL (lote 4 del simulador de carga).
 *
 * POR QUÉ ES LA PIEZA QUE FALTABA. El motor calcula por rejilla exacta y avisa en todos
 * lados que su número es un TECHO: la estiba real tiene amarres, hileras giradas y gente
 * que necesita pasar. `CalculoDeCarga::conFactor()` existe desde el primer día para
 * castigar ese techo… y nunca se usó, porque el factor **se calibra contando UNA carga
 * real** y no había dónde anotarla.
 *
 * El 11-08-2026 quedó demostrado lo que cuesta no tenerla: el dueño reportó 480 botellones
 * acostados en el HD35, el cálculo daba 360, y para hacer cerrar ese número se dedujo un
 * ancho de 204 cm que sobrevivió cuatro días hasta que la huincha dio 200. Con esta tabla,
 * ese 480 habría entrado como UN DATO —fecha, camión, producto, estiba— al lado de su
 * simulado, en vez de convertirse en una corrección de medidas.
 *
 * Se guarda lo MEDIDO, no lo derivado: el factor sale de dividir y no se persiste, porque
 * un número calculado guardado es un número que se desactualiza en silencio cuando cambia
 * la fórmula.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cargas_reales')) {
            return;
        }

        Schema::create('cargas_reales', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');

            // Contra QUÉ se comparó. Son los del catálogo del simulador, no los de la
            // flota (§0): el historial tiene que hablar el mismo idioma que la pantalla
            // que se está calibrando.
            $table->foreignId('camion_simulacion_id')->constrained('camiones_simulacion')->cascadeOnDelete();
            $table->foreignId('tipo_bulto_id')->constrained('tipos_bulto')->cascadeOnDelete();
            // La estiba con la que se cargó. Sin esto el registro no sirve: en el HD35 la
            // misma bolsa da 420 de pie y 360 acostada, así que un «entraron 400» sin
            // decir cómo iba no se puede comparar contra nada.
            $table->string('estiba', 16)->default('auto');

            // LOS DOS NÚMEROS, en UNIDADES sueltas — el idioma del vendedor (§3).
            $table->unsignedInteger('simulado');
            $table->unsignedInteger('real');

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('observaciones', 300)->nullable();
            $table->timestamps();

            // La consulta que importa es «¿qué se midió para ESTA combinación?», que es la
            // que la pantalla del simulador hace en cada cálculo.
            $table->index(['camion_simulacion_id', 'tipo_bulto_id', 'estiba'], 'cargas_reales_combinacion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_reales');
    }
};
