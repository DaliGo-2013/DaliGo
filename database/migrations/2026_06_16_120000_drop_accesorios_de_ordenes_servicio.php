<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se quita "accesorios recibidos" del ingreso: lo que haya se anota en
     * observaciones (decision del flujo del taller). La tabla ya esta desplegada,
     * por eso va en migracion propia.
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn('accesorios');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->text('accesorios')->nullable();
        });
    }
};
