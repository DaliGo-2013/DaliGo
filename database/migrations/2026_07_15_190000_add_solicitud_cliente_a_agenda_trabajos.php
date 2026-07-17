<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitudes de visita industrial hechas por el CLIENTE (QR): entran a la
     * agenda como estado 'solicitado' SIN fecha (la fecha real la pone quien
     * coordina) y con una fecha PREFERIDA opcional como referencia. Por eso
     * `fecha` pasa a nullable. Idempotente.
     */
    public function up(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            if (! Schema::hasColumn('agenda_trabajos', 'fecha_preferida')) {
                $table->date('fecha_preferida')->nullable()->after('fecha');
            }
        });

        // Cambio de nulabilidad (seguro de re-ejecutar: dejar nullable dos veces
        // no falla). Laravel 11+ soporta change() nativo en MySQL 5.7 y SQLite.
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            $table->date('fecha')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            if (Schema::hasColumn('agenda_trabajos', 'fecha_preferida')) {
                $table->dropColumn('fecha_preferida');
            }
        });
    }
};
