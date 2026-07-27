<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autorización de la reparación tras el pago (P-M12-02): cuando el cliente
 * aceptó la cotización, ventas coordina el pago (forma + comprobante opcional +
 * nota) y AUTORIZA → el técnico procede. Los datos viven en la cotización
 * aceptada (es lo que se cobró). El comprobante va al disco PRIVADO `local`
 * (ruta), servido con sesión — es un dato sensible (transferencia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicio_cotizaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('orden_servicio_cotizaciones', 'pago_forma')) {
                $table->string('pago_forma', 30)->nullable()->after('respuesta_ip'); // sala_ventas|transferencia|efectivo|al_retiro
            }
            if (! Schema::hasColumn('orden_servicio_cotizaciones', 'pago_comprobante_ruta')) {
                $table->string('pago_comprobante_ruta', 191)->nullable()->after('pago_forma');
            }
            if (! Schema::hasColumn('orden_servicio_cotizaciones', 'pago_nota')) {
                $table->string('pago_nota', 1000)->nullable()->after('pago_comprobante_ruta');
            }
            if (! Schema::hasColumn('orden_servicio_cotizaciones', 'autorizada_at')) {
                $table->timestamp('autorizada_at')->nullable()->after('pago_nota');
            }
            if (! Schema::hasColumn('orden_servicio_cotizaciones', 'autorizada_por')) {
                $table->foreignId('autorizada_por')->nullable()->after('autorizada_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicio_cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('orden_servicio_cotizaciones', 'autorizada_por')) {
                $table->dropConstrainedForeignId('autorizada_por');
            }
            foreach (['autorizada_at', 'pago_nota', 'pago_comprobante_ruta', 'pago_forma'] as $col) {
                if (Schema::hasColumn('orden_servicio_cotizaciones', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
