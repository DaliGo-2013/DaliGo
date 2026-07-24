<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disponibilidad que escribe el cliente al pedir una visita/revisión de
     * terreno: cuándo puede y cuándo no (ej. "fines de semana no", "después de
     * las 15 h", "el taller cierra a las 18 h"). Texto libre opcional; lo usa
     * quien coordina para fijar la fecha. Idempotente.
     */
    public function up(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            if (! Schema::hasColumn('agenda_trabajos', 'disponibilidad')) {
                $table->text('disponibilidad')->nullable()->after('descripcion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            if (Schema::hasColumn('agenda_trabajos', 'disponibilidad')) {
                $table->dropColumn('disponibilidad');
            }
        });
    }
};
