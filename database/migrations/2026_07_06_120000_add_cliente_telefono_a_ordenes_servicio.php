<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Telefono de contacto del cliente, snapshot en la orden igual que
     * cliente_nombre/cliente_rut: sirve para avisarle cuando el equipo esta
     * listo aunque no exista en el catalogo de Clientes. Nullable por las
     * filas previas y porque no todo ingreso de mostrador lo trae.
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->string('cliente_telefono', 30)->nullable()->after('cliente_rut');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn('cliente_telefono');
        });
    }
};
