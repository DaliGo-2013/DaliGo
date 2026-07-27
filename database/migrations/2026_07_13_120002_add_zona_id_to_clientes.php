<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zona EXPLICITA del cliente (ajuste del dueno 2026-07-13, "siempre hay
     * excepciones"). Si esta seteada, GANA sobre la zona heredada del vendedor
     * (Cliente::zonaEfectiva). Sin setear = null = hereda de vendedor->zona.
     * Nullable; onDelete set null. Es campo LOCAL: la sync de Bsale nunca lo
     * pisa (no viaja en el espejo).
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('zona_id')->nullable()->after('vendedor_nombre')->constrained('zonas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zona_id');
        });
    }
};
