<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DESPACHOS-v1 · P-DSP-04: qué quedó pendiente cuando la entrega es PARCIAL.
 * El estado `entrega_parcial` ya existía (P-DSP-03) pero no había dónde dejar
 * el saldo, y el dictado exige que "el saldo quede visible": sin esta columna
 * el parcial es un estado ciego (nadie sabe qué falta entregar).
 *
 * 191 y no 255: el proyecto fija Schema::defaultStringLength(191)
 * (AppServiceProvider, límite de índice de InnoDB en MySQL 5.7 con utf8mb4).
 * Un string más largo pasa en SQLite —no valida el largo— y tumba el deploy
 * en MySQL: es exactamente el incidente I-07 del 27-07.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->string('entrega_observacion', 191)->nullable()->after('entregado_at');
        });
    }

    public function down(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->dropColumn('entrega_observacion');
        });
    }
};
