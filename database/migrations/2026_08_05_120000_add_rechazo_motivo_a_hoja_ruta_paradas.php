<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El motivo del rechazo en puerta (R15, P-DSP-09). PLAN-DESPACHOS-V2 §2 no
 * enumeró la columna, pero «rechazo con motivo» exige persistirlo: es lo que
 * el jefe de despacho lee en el aviso y lo que alimenta la decisión de
 * devolución (M13) — micro-decisión anotada en el parte de P-DSP-09.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hoja_ruta_paradas', function (Blueprint $table) {
            $table->string('rechazo_motivo', 191)->nullable()->after('resultado');
        });
    }

    public function down(): void
    {
        Schema::table('hoja_ruta_paradas', function (Blueprint $table) {
            $table->dropColumn('rechazo_motivo');
        });
    }
};
