<?php

use App\Models\OrdenServicio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codigo unico e impredecible para cada orden (reemplaza al folio correlativo
 * #000123, que era enumerable). VARCHAR(32) unico e indexado. Se backfillea a
 * las ordenes existentes con un codigo unico cada una.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ordenes_servicio', 'codigo')) {
            Schema::table('ordenes_servicio', function (Blueprint $table) {
                $table->string('codigo', 32)->nullable()->unique()->after('id');
            });
        }

        // Backfill: cada orden sin codigo recibe uno unico (saveQuietly para no
        // disparar auditoria/eventos durante la migracion).
        OrdenServicio::whereNull('codigo')->get()->each(function (OrdenServicio $orden) {
            $orden->forceFill(['codigo' => OrdenServicio::generarCodigoUnico()])->saveQuietly();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ordenes_servicio', 'codigo')) {
            Schema::table('ordenes_servicio', function (Blueprint $table) {
                $table->dropUnique(['codigo']);
                $table->dropColumn('codigo');
            });
        }
    }
};
