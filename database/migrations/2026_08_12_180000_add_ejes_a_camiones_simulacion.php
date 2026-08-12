<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DÓNDE ESTÁN LOS EJES, para poder repartir el peso de la carga entre ellos
 * (lote 5, pedido del dueño; los datos llegaron el 12-08-2026).
 *
 * DOS NÚMEROS Y UNA SOLA REFERENCIA. Todo el simulador mide desde el FRENTE DE LA
 * CAJA DE CARGA —es el x = 0 del motor y del visor— así que los ejes se anotan
 * contra ese mismo punto. Mezclar referencias (uno desde el paragolpes, otro desde
 * la cabina) es la forma segura de que el brazo de palanca salga mal y nadie lo note.
 *
 *  · `entre_ejes_cm`  — distancia entre el eje delantero y el trasero.
 *  · `eje_trasero_cm` — del frente de la caja al centro del eje trasero.
 *
 * El eje DELANTERO no se guarda: sale de restar los dos, y casi siempre da negativo
 * porque está adelante del arranque de la caja (debajo de la cabina). Guardarlo
 * aparte sería un tercer número que puede contradecir a los otros dos.
 *
 * NULL = NO MEDIDO, y así se muestra. No hay valor por defecto ni estimación: un
 * reparto de peso inventado es peor que no tener la función — sobre esto se decide
 * si un camión sale con multa o sin dirección. Es la misma regla que dejó las jaulas
 * de máquinas sin sembrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camiones_simulacion', function (Blueprint $table) {
            $table->unsignedSmallInteger('entre_ejes_cm')->nullable()->after('pasillo_cm');
            $table->unsignedSmallInteger('eje_trasero_cm')->nullable()->after('entre_ejes_cm');
        });
    }

    public function down(): void
    {
        Schema::table('camiones_simulacion', function (Blueprint $table) {
            $table->dropColumn(['entre_ejes_cm', 'eje_trasero_cm']);
        });
    }
};
