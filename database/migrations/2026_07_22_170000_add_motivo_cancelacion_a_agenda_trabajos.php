<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motivo del rechazo/cancelación de una solicitud o trabajo de terreno (ej.
     * técnico de vacaciones, equipo de otra marca, atraso de pagos). Se muestra
     * en el correo al cliente y en el aviso interno. Idempotente.
     */
    public function up(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            if (! Schema::hasColumn('agenda_trabajos', 'motivo_cancelacion')) {
                $table->string('motivo_cancelacion', 191)->nullable()->after('notas_tecnico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            if (Schema::hasColumn('agenda_trabajos', 'motivo_cancelacion')) {
                $table->dropColumn('motivo_cancelacion');
            }
        });
    }
};
