<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enlaza cada orden a su lote de ingreso (nullable: las órdenes de mostrador
     * y QR quedan sin lote). Indexado para listar/contar por lote. Idempotente.
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_servicio', 'lote_id')) {
                $table->foreignId('lote_id')->nullable()->after('sucursal_id')
                    ->constrained('lotes_servicio')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('ordenes_servicio', 'lote_id')) {
                $table->dropConstrainedForeignKey('lote_id');
            }
        });
    }
};
