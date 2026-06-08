<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas que aporta la sincronizacion con Bsale: barcode (codigo de barras
     * de la variante) y bsale_product_type_id (id de la categoria en Bsale).
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('barcode')->nullable()->index()->after('sku');
            $table->unsignedBigInteger('bsale_product_type_id')->nullable()->index()->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropIndex(['bsale_product_type_id']);
            $table->dropColumn(['barcode', 'bsale_product_type_id']);
        });
    }
};
