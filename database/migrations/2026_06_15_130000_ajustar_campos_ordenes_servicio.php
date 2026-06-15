<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajustes al formulario de Servicio Tecnico segun el flujo real del taller:
     * - se quita `marca`: todas las maquinas son Dali (no se reciben otras marcas).
     * - se quita `tecnico_id`: el responsable es siempre el mismo (Fernando), no
     *   hace falta registrarlo por orden.
     * - se agrega `producto_id`: el "codigo" es el producto Dali del catalogo (SKU).
     * - se agrega `facturacion`: garantia (no se cobra) | boleta (se cobra reparacion).
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn('marca');
            $table->dropConstrainedForeignId('tecnico_id');

            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('facturacion')->nullable();  // garantia | boleta
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
            $table->dropColumn('facturacion');

            $table->string('marca')->nullable();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
