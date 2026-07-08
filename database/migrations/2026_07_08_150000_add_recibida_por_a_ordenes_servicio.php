<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre (snapshot) del encargado del mostrador que RECIBIO el equipo: en el
     * ingreso interno es quien registra; en el ingreso por QR es quien confirma
     * la recepcion. Snapshot igual que cliente_nombre (queda el nombre al momento
     * de recibir, aunque el usuario cambie despues). Nullable por las filas
     * previas y por los QR aun sin confirmar. Idempotente.
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_servicio', 'recibida_por')) {
                $table->string('recibida_por')->nullable()->after('confirmada_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('ordenes_servicio', 'recibida_por')) {
                $table->dropColumn('recibida_por');
            }
        });
    }
};
