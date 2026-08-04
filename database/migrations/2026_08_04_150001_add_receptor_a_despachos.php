<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receptor de la entrega (R13, PLAN-DESPACHOS-V2 §2): nombre + RUT +
 * relación con el cliente (empresa compradora, conserje, otro). Las columnas
 * nacen aquí junto al resto del modelo de datos de F1; la PWA las exige
 * junto a firma+foto recién en P-DSP-09 — mismo criterio que estado_cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->string('receptor_nombre', 191)->nullable()->after('entrega_observacion');
            $table->string('receptor_rut', 12)->nullable()->after('receptor_nombre');
            $table->string('receptor_relacion', 32)->nullable()->after('receptor_rut');
        });
    }

    public function down(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->dropColumn(['receptor_nombre', 'receptor_rut', 'receptor_relacion']);
        });
    }
};
