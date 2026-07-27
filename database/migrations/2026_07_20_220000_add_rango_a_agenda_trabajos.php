<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rango del trabajo agendado: fecha_fin (para viajes de varios días — ej. Carlos
 * en Puerto Montt del 7 al 10) y hora_fin (para trabajos de día completo — ej.
 * instalación de 08:00 a 18:00). Ambos nullable: si van vacíos, el trabajo es de
 * un solo día / una sola franja, como hasta ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            if (! Schema::hasColumn('agenda_trabajos', 'fecha_fin')) {
                $table->date('fecha_fin')->nullable()->after('fecha');
            }
            if (! Schema::hasColumn('agenda_trabajos', 'hora_fin')) {
                $table->time('hora_fin')->nullable()->after('hora');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            foreach (['fecha_fin', 'hora_fin'] as $col) {
                if (Schema::hasColumn('agenda_trabajos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
