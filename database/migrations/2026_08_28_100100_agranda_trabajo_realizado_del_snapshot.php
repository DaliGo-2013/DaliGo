<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El texto del trabajo ya no es UNA respuesta de la lista: desde el 28-08 el tecnico marca
 * varios trabajos y el texto se arma con todos ellos («cambio de llave de agua, cambio de
 * estanque, cambio de caldera y se agrega espigon — funciona normal»). Con 4 o 5 trabajos se
 * pasa de los 191 caracteres que tenia el snapshot de la cotizacion.
 *
 * POR QUE IMPORTA EL LARGO DE ESTA COLUMNA Y NO DE LA OTRA: `ordenes_servicio.trabajo_realizado`
 * ya es `text`, asi que el parte se guardaria igual; la que reventaba era esta, VARCHAR(191), y
 * lo hace al ENVIAR la cotizacion —o sea lejos de donde se escribio el texto— y SOLO en MySQL
 * (SQLite acepta un texto mas largo en un varchar, asi que en local y en los tests no se ve).
 * Es el mismo motivo por el que OrdenServicio::TRABAJO_MAX existe; ese tope sube junto con esto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orden_servicio_cotizaciones')) {
            return;
        }

        Schema::table('orden_servicio_cotizaciones', function (Blueprint $table) {
            $table->string('trabajo_realizado', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orden_servicio_cotizaciones')) {
            return;
        }

        // Ojo: volver a 191 TRUNCA los textos ya guardados que se pasen. Se deja el reverso
        // porque la migracion debe ser reversible, pero es un camino con perdida.
        Schema::table('orden_servicio_cotizaciones', function (Blueprint $table) {
            $table->string('trabajo_realizado', 191)->nullable()->change();
        });
    }
};
