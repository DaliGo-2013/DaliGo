<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DESPACHOS-v1 · P-DSP-05: la red ESTRUCTURAL de la idempotencia de entregas.
 *
 * P-DSP-03 dejó `entrega_uuid` con un index normal; el check de aplicación no
 * basta solo (dos requests de la cola offline drenando en paralelo pasan ambas
 * el pre-check antes de que ninguna escriba — patrón bitácora 2026-07-02: el
 * unique es la red, el check es la cara amable). Precedentes: `lote_uuid`
 * unique en lotes_servicio, unique compuesto [reporte_id, cliente_uuid] en
 * produccion_registros.
 *
 * NO se cambia el tipo de la columna (string 191): alterar tipos en MySQL 5.7
 * en caliente es riesgo de deploy; nullable + unique admite múltiples NULL en
 * MySQL 5.7 y SQLite (los despachos sin entrega no chocan). La columna no
 * respalda ninguna FK, así que soltar el index normal es seguro (el gotcha
 * 1553 de la bitácora 2026-06-30 no aplica).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->dropIndex(['entrega_uuid']);
            $table->unique('entrega_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->dropUnique(['entrega_uuid']);
            $table->index('entrega_uuid');
        });
    }
};
