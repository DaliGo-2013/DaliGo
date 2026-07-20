<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre del CONDUCTOR que retira el lote en ruta (elegido de una lista fija en
 * config). `conductor_id` ya guarda al usuario que registró el lote; esto guarda
 * quién físicamente lo retiró (el chofer, que no es un usuario del sistema).
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lotes_servicio', 'conductor_nombre')) {
            return;
        }

        Schema::table('lotes_servicio', function (Blueprint $table) {
            $table->string('conductor_nombre')->nullable()->after('conductor_id');
        });
    }

    public function down(): void
    {
        Schema::table('lotes_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('lotes_servicio', 'conductor_nombre')) {
                $table->dropColumn('conductor_nombre');
            }
        });
    }
};
