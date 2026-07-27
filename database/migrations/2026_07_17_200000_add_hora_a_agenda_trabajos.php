<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hora del trabajo agendado (para la vista calendario con franjas horarias).
 * Nullable: los trabajos viejos y las solicitudes del cliente (QR) no la tienen;
 * al agendar desde el calendario se llena con la hora del slot elegido.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agenda_trabajos', 'hora')) {
            Schema::table('agenda_trabajos', function (Blueprint $table) {
                $table->time('hora')->nullable()->after('fecha');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('agenda_trabajos', 'hora')) {
            Schema::table('agenda_trabajos', function (Blueprint $table) {
                $table->dropColumn('hora');
            });
        }
    }
};
