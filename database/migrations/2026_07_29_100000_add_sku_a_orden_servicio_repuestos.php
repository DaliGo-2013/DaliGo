<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SKU del repuesto usado en una reparación (M05 · facturación).
 *
 * Por qué hace falta: la regla 4 de Contabilidad (28-jul-2026) dice que los
 * repuestos se facturan **con su código del catálogo**. Hoy
 * `orden_servicio_repuestos` guarda solo el nombre en texto libre: el
 * autocompletado del técnico YA busca en el catálogo y hasta muestra el SKU en la
 * lista de sugerencias, pero al elegir uno se queda solo con el nombre y el
 * precio — el código se descarta. Sin él no se puede armar la línea del documento
 * tributario como la pide Contabilidad.
 *
 * Nullable a propósito: un repuesto escrito a mano (que no está en el catálogo)
 * es un caso legítimo y frecuente, y esas líneas van al documento como glosa
 * libre. Null significa "no vino del catálogo", no "falta el dato".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orden_servicio_repuestos', 'sku')) {
            return;
        }

        Schema::table('orden_servicio_repuestos', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orden_servicio_repuestos', 'sku')) {
            return;
        }

        Schema::table('orden_servicio_repuestos', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }
};
