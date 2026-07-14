<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ruta" como opción de recepción: cuando el equipo lo recibe el conductor en
 * ruta (no en una sucursal física), se guarda la ciudad/localidad aquí en vez
 * de sucursal_id. Así la info "RUTA X" (Rancagua, Los Andes, Melipilla…) queda
 * estructurada y buscable. sucursal_id y ruta son mutuamente excluyentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ordenes_servicio', 'ruta')) {
            Schema::table('ordenes_servicio', function (Blueprint $table) {
                $table->string('ruta', 120)->nullable()->after('sucursal_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ordenes_servicio', 'ruta')) {
            Schema::table('ordenes_servicio', function (Blueprint $table) {
                $table->dropColumn('ruta');
            });
        }
    }
};
