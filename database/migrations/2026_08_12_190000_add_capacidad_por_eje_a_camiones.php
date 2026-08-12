<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CUÁNTO AGUANTA CADA EJE (pedido del dueño 12-08-2026: «decime cuánto aguanta cada
 * eje y si me pasé, para evitar una multa, que salga un mensaje en rojo»).
 *
 * `RepartoPorEje` ya dice cuánto le TOCA a cada eje. Con estos dos números puede decir
 * además si se PASÓ, que es lo que evita la multa: en la balanza no se pesa el camión
 * entero, se pesa eje por eje. Un camión por debajo de su carga útil total puede tener
 * el eje trasero pasado y lo paga igual.
 *
 * NULL = NO CARGADO, y entonces no hay aviso: solo se muestra el reparto. No se siembra
 * ningún valor por defecto, y esto es deliberado aunque deje la función esperando el
 * dato. El límite que manda es el MENOR entre dos cosas:
 *
 *  · el máximo LEGAL por tipo de eje (lo que mira la balanza), y
 *  · el máximo del FABRICANTE para ese eje en particular.
 *
 * Sembrar el legal «para que funcione» daría verde a un camión chico con el eje pasado
 * de fábrica; sembrar el del fabricante de memoria es peor todavía. Los dos números
 * están escritos en el PADRÓN / la revisión técnica de cada vehículo, que es la misma
 * fuente que usa quien fiscaliza. De ahí se cargan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camiones_simulacion', function (Blueprint $table) {
            $table->unsignedInteger('eje_delantero_max_kg')->nullable()->after('eje_trasero_cm');
            $table->unsignedInteger('eje_trasero_max_kg')->nullable()->after('eje_delantero_max_kg');
        });
    }

    public function down(): void
    {
        Schema::table('camiones_simulacion', function (Blueprint $table) {
            $table->dropColumn(['eje_delantero_max_kg', 'eje_trasero_max_kg']);
        });
    }
};
