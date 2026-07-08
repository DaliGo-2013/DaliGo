<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Falla reportada por el TECNICO: notas adicionales sobre la falla que el
     * cliente no indico / se le paso, agregadas por el tecnico. Se guarda APARTE
     * de `falla_reportada` (las palabras del cliente) para no mezclarlas ni
     * alterarlas. Nullable: el tecnico la agrega solo si hace falta. Idempotente.
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_servicio', 'falla_tecnico')) {
                $table->text('falla_tecnico')->nullable()->after('falla_reportada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('ordenes_servicio', 'falla_tecnico')) {
                $table->dropColumn('falla_tecnico');
            }
        });
    }
};
