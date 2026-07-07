<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotencia de la cola offline (spike P-SPK-02): cuando el soplador
     * registra una tanda SIN señal, el cliente genera un UUID y la encola en
     * IndexedDB; al volver la conexion el drenado la reenvia. Sin este candado,
     * un reintento del drenado (respuesta perdida por corte) crearia la tanda
     * dos veces.
     *
     * - nullable a proposito: el camino nativo (con señal, submit normal) NO
     *   manda uuid y esos registros quedan con NULL; MySQL 5.7 y SQLite permiten
     *   multiples NULL en un indice unique, asi que no chocan entre si.
     * - unique COMPUESTO [reporte_id, cliente_uuid]: "esta tanda, en este
     *   reporte, una sola vez" (el reporte_id ya viaja en la URL del endpoint).
     *   char(36) = 36 < 191, sin problema con el limite de indice de utf8mb4/5.7.
     */
    public function up(): void
    {
        Schema::table('produccion_registros', function (Blueprint $table) {
            $table->char('cliente_uuid', 36)->nullable()->after('reporte_id');
            $table->unique(['reporte_id', 'cliente_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('produccion_registros', function (Blueprint $table) {
            $table->dropUnique(['reporte_id', 'cliente_uuid']);
            $table->dropColumn('cliente_uuid');
        });
    }
};
