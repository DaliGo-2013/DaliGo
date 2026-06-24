<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Condicion de la orden: garantia | reparacion (antes era garantia | boleta).
     * Cuando es garantia, se respalda con el documento de compra (factura/boleta)
     * y su fecha: la garantia dura 6 meses DESDE la compra. Si al ingresar el
     * equipo ya pasaron los 6 meses, no aplica garantia (debe cobrarse).
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->string('garantia_doc_tipo')->nullable()->after('facturacion');   // factura | boleta
            $table->string('garantia_doc_numero')->nullable()->after('garantia_doc_tipo');
            $table->date('garantia_doc_fecha')->nullable()->after('garantia_doc_numero'); // fecha de compra
        });

        // Datos existentes: 'boleta' pasa a ser 'reparacion' (misma semantica: se cobra).
        DB::table('ordenes_servicio')->where('facturacion', 'boleta')->update(['facturacion' => 'reparacion']);
    }

    public function down(): void
    {
        DB::table('ordenes_servicio')->where('facturacion', 'reparacion')->update(['facturacion' => 'boleta']);

        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn(['garantia_doc_tipo', 'garantia_doc_numero', 'garantia_doc_fecha']);
        });
    }
};
