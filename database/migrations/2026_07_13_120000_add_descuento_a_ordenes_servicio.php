<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descuento opcional sobre una reparacion cobrable: porcentaje (10/15/20) y el
 * motivo que lo justifica (cliente grande / negociacion / demora). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_servicio', 'descuento_pct')) {
                $table->unsignedTinyInteger('descuento_pct')->default(0)->after('mano_obra');
            }
            if (! Schema::hasColumn('ordenes_servicio', 'descuento_motivo')) {
                $table->string('descuento_motivo', 30)->nullable()->after('descuento_pct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            foreach (['descuento_pct', 'descuento_motivo'] as $col) {
                if (Schema::hasColumn('ordenes_servicio', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
